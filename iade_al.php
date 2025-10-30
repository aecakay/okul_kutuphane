<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';
session_start();

$response = ['success' => false, 'message' => 'Bilinmeyen bir hata oluştu.'];

// 1. Yetki Kontrolü
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["okul_id"])) {
    $response['message'] = 'Yetkisiz erişim.';
    http_response_code(403);
    echo json_encode($response);
    exit;
}

$okul_id = $_SESSION["okul_id"];
$islem_id = isset($_POST['islem_id']) ? (int)$_POST['islem_id'] : 0;
$kitap_id = isset($_POST['kitap_id']) ? (int)$_POST['kitap_id'] : 0;

if ($islem_id <= 0 || $kitap_id <= 0) {
    $response['message'] = 'HATA: Geçersiz işlem veya kitap ID\'si gönderildi.';
    echo json_encode($response);
    exit;
}

$mysqli->begin_transaction();

try {
    $tarih = date('Y-m-d');
    $bugun_timestamp = strtotime($tarih);
    $ek_mesaj = "";

    // 2. İade işlemini gerçekleştirmeden önce, öğrenci ve gecikme bilgisini kontrol et (Sadece o okul için)
    $sql_check_gecikme = "SELECT ogrenci_id, son_iade_tarihi FROM islemler WHERE id = ? AND kitap_id = ? AND okul_id = ? AND iade_tarihi IS NULL";
    $stmt_check = $mysqli->prepare($sql_check_gecikme);
    $stmt_check->bind_param("iii", $islem_id, $kitap_id, $okul_id);
    $stmt_check->execute();
    $stmt_check->store_result();
    
    if ($stmt_check->num_rows == 0) {
         $stmt_check->close();
         throw new Exception('HATA: Bu işlem bulunamadı, bu okula ait değil veya zaten iade edilmiş.');
    }
    
    $stmt_check->bind_result($ogrenci_id, $son_iade_tarihi);
    $stmt_check->fetch();
    $stmt_check->close();

    // 3. GEÇİKME HESAPLAMA VE CEZA UYGULAMA MANTIĞI
    $gecikme_gun = 0;
    if ($son_iade_tarihi && strtotime($son_iade_tarihi) < $bugun_timestamp) {
        $gecikme_gun = floor(($bugun_timestamp - strtotime($son_iade_tarihi)) / (60 * 60 * 24));
    }

    if ($gecikme_gun > 0) {
        // Ayarları çek (Sadece o okul için)
        $ayarlar = [];
        $sql_ayarlar = "SELECT ayar_adi, ayar_degeri FROM ayarlar WHERE okul_id = ?";
        $stmt_ayarlar = $mysqli->prepare($sql_ayarlar);
        $stmt_ayarlar->bind_param("i", $okul_id);
        $stmt_ayarlar->execute();
        // DÜZELTME: get_result() yerine uyumlu yöntem kullanıldı
        $stmt_ayarlar->store_result();
        $stmt_ayarlar->bind_result($ayar_adi, $ayar_degeri);
        while ($stmt_ayarlar->fetch()) { $ayarlar[$ayar_adi] = $ayar_degeri; }
        $stmt_ayarlar->close();
        
        $gunluk_ceza = (float)($ayarlar['gunluk_ceza_miktari'] ?? 0.00);
        $ceza_tipi_para_aktif = ($ayarlar['ceza_tipi_para'] ?? '0') == '1';
        $ceza_tipi_yasak_aktif = ($ayarlar['ceza_tipi_yasak'] ?? '0') == '1';
        $yasak_gun_sayisi = (int)($ayarlar['yasak_gun_sayisi'] ?? '7');
        
        $ek_mesaj .= " Kitap {$gecikme_gun} gün gecikmiştir.";

        // Ceza Tipi 1: Para Cezası
        if ($ceza_tipi_para_aktif && $gunluk_ceza > 0) {
            $toplam_ceza = $gecikme_gun * $gunluk_ceza;
            $ek_mesaj .= " Para cezası: " . number_format($toplam_ceza, 2, ',', '.') . " TL.";
        }
        
        // Ceza Tipi 2: Kitap Alma Yasağı
        if ($ceza_tipi_yasak_aktif && $yasak_gun_sayisi > 0) {
            $sql_ogr_yasak = "SELECT kitap_almasi_yasak_tarih FROM ogrenciler WHERE id = ? AND okul_id = ?";
            $stmt_ogr = $mysqli->prepare($sql_ogr_yasak); $stmt_ogr->bind_param("ii", $ogrenci_id, $okul_id); $stmt_ogr->execute();
            $stmt_ogr->bind_result($mevcut_yasak_tarihi); $stmt_ogr->fetch(); $stmt_ogr->close();

            $baslangic_tarihi = (!empty($mevcut_yasak_tarihi) && strtotime($mevcut_yasak_tarihi) > $bugun_timestamp) 
                                ? strtotime($mevcut_yasak_tarihi) : $bugun_timestamp;
            $yeni_yasak_bitis = date('Y-m-d', $baslangic_tarihi + ($yasak_gun_sayisi * 24 * 60 * 60));
            
            $sql_yasak_update = "UPDATE ogrenciler SET kitap_almasi_yasak_tarih = ? WHERE id = ? AND okul_id = ?";
            $stmt_yasak = $mysqli->prepare($sql_yasak_update); $stmt_yasak->bind_param("sii", $yeni_yasak_bitis, $ogrenci_id, $okul_id);
            $stmt_yasak->execute(); $stmt_yasak->close();
            
            $ek_mesaj .= " Öğrenciye {$yasak_gun_sayisi} günlük kitap alma yasağı uygulandı (Bitiş: " . date("d.m.Y", strtotime($yeni_yasak_bitis)) . ").";
        }
    }

    // 4. İade işlemini tamamla (Kitap ID'si kontrolü eklendi)
    $sql1 = "UPDATE islemler SET iade_tarihi = ? WHERE id = ? AND kitap_id = ? AND okul_id = ?";
    $stmt1 = $mysqli->prepare($sql1);
    $stmt1->bind_param("siii", $tarih, $islem_id, $kitap_id, $okul_id);
    $stmt1->execute();
    $stmt1->close();
    
    // Stok güncelle
    $sql2 = "UPDATE kitaplar SET raftaki_adet = raftaki_adet + 1 WHERE id = ? AND okul_id = ?";
    $stmt2 = $mysqli->prepare($sql2);
    $stmt2->bind_param("ii", $kitap_id, $okul_id);
    $stmt2->execute();
    $stmt2->close();
    
    $response['success'] = true;
    $response['message'] = 'Kitap başarıyla iade alındı.' . $ek_mesaj;

    // 5. Rezervasyon kontrolü
    $sql_rez_check = "SELECT R.id, O.ad, O.soyad FROM rezervasyonlar R JOIN ogrenciler O ON R.ogrenci_id = O.id WHERE R.kitap_id = ? AND R.okul_id = ? AND R.durum = 'bekliyor' ORDER BY R.rezervasyon_tarihi ASC LIMIT 1";
    if ($stmt_rez = $mysqli->prepare($sql_rez_check)) {
        $stmt_rez->bind_param("ii", $kitap_id, $okul_id);
        $stmt_rez->execute();
        $stmt_rez->store_result();
        if($stmt_rez->num_rows > 0) {
            $stmt_rez->bind_result($rezervasyon_id, $ogrenci_ad, $ogrenci_soyad); $stmt_rez->fetch();
            $sql_update_rez = "UPDATE rezervasyonlar SET durum = 'bildirildi' WHERE id = ? AND okul_id = ?";
            if($stmt_update = $mysqli->prepare($sql_update_rez)){
                $stmt_update->bind_param("ii", $rezervasyon_id, $okul_id);
                $stmt_update->execute(); $stmt_update->close();
                $response['message'] .= " Sıradaki öğrenci (" . htmlspecialchars($ogrenci_ad . ' ' . $ogrenci_soyad) . ") için rezervasyon ayrıldı.";
            }
        }
        $stmt_rez->close();
    }
    
    $mysqli->commit();

} catch (Exception $e) {
    $mysqli->rollback();
    $response['message'] = $e->getMessage();
}

$mysqli->close();
echo json_encode($response);
exit;