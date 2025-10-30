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
$kitap_id = isset($_POST['selected_kitap_id']) ? (int)$_POST['selected_kitap_id'] : 0;
$ogrenci_id = isset($_POST['selected_ogrenci_id']) ? (int)$_POST['selected_ogrenci_id'] : 0;

if ($kitap_id <= 0 || $ogrenci_id <= 0) {
    $response['message'] = 'HATA: Geçerli bir kitap ve öğrenci seçilmelidir.';
    echo json_encode($response);
    exit;
}

$mysqli->begin_transaction();

try {
    $bugun = date('Y-m-d');

    // 2. YASAK KONTROLÜ (Sadece o okula ait öğrenci için)
    $sql_yasak_check = "SELECT kitap_almasi_yasak_tarih FROM ogrenciler WHERE id = ? AND okul_id = ?";
    $stmt_yasak = $mysqli->prepare($sql_yasak_check);
    $stmt_yasak->bind_param("ii", $ogrenci_id, $okul_id);
    $stmt_yasak->execute();
    $stmt_yasak->store_result();
    if($stmt_yasak->num_rows == 0) {
        throw new Exception("HATA: Seçilen öğrenci bu okula ait değil.");
    }
    $stmt_yasak->bind_result($yasak_bitis_tarihi);
    $stmt_yasak->fetch();
    $stmt_yasak->close();

    if ($yasak_bitis_tarihi && strtotime($yasak_bitis_tarihi) >= strtotime($bugun)) {
        throw new Exception('HATA: Öğrenci, ' . date("d.m.Y", strtotime($yasak_bitis_tarihi)) . ' tarihine kadar kitap alma yasağı altındadır.');
    }
    
    // 3. ÖDÜNÇ LİMİT KONTROLÜ (Sadece o okula ait işlemler için)
    $sql_limit = "SELECT 
                    (SELECT ayar_degeri FROM ayarlar WHERE ayar_adi = 'odunc_kitap_limiti' AND okul_id = ?) AS limit_degeri,
                    (SELECT COUNT(id) FROM islemler WHERE ogrenci_id = ? AND okul_id = ? AND iade_tarihi IS NULL) AS mevcut_kitap_sayisi";

    $stmt_limit = $mysqli->prepare($sql_limit);
    $stmt_limit->bind_param("iii", $okul_id, $ogrenci_id, $okul_id);
    $stmt_limit->execute();
    $stmt_limit->store_result();
    $stmt_limit->bind_result($limit_degeri, $mevcut_kitap_sayisi);
    $stmt_limit->fetch();
    $stmt_limit->close();
    $limit_degeri = (int)($limit_degeri ?? 3); // Varsayılan 3
    
    if ($mevcut_kitap_sayisi >= $limit_degeri) {
         throw new Exception('HATA: Öğrenci, maksimum ödünç limitine (' . $limit_degeri . ' kitap) ulaşmıştır.');
    }

    // 4. STOK KONTROLÜ (Sadece o okula ait kitap için)
    $sql_stok_check = "SELECT raftaki_adet FROM kitaplar WHERE id = ? AND okul_id = ?";
    $stmt_check = $mysqli->prepare($sql_stok_check);
    $stmt_check->bind_param("ii", $kitap_id, $okul_id);
    $stmt_check->execute();
    $stmt_check->store_result();
     if($stmt_check->num_rows == 0) {
        throw new Exception("HATA: Seçilen kitap bu okula ait değil.");
    }
    $stmt_check->bind_result($raftaki_adet);
    $stmt_check->fetch();
    $stmt_check->close();

    if ($raftaki_adet <= 0) {
        throw new Exception('HATA: Seçilen kitabın rafta kopyası bulunmamaktadır.');
    }

    // 5. İŞLEMİ KAYDET (DİNAMİK ÖDÜNÇ SÜRESİ İLE)
    
    // --- DÜZELTME BAŞLANGICI ---
    // Ayarlardan ödünç süresini çek
    $sql_iade_suresi = "SELECT ayar_degeri FROM ayarlar WHERE ayar_adi = 'varsayilan_iade_suresi_gun' AND okul_id = ?";
    $stmt_sure = $mysqli->prepare($sql_iade_suresi);
    $stmt_sure->bind_param("i", $okul_id);
    $stmt_sure->execute();
    $stmt_sure->bind_result($iade_suresi_db);
    $stmt_sure->fetch();
    $stmt_sure->close();
    
    // Eğer ayar bulunamazsa, varsayılan olarak 14 gün kullan
    $varsayilan_iade_suresi_gun = !empty($iade_suresi_db) ? (int)$iade_suresi_db : 14;
    // --- DÜZELTME SONU ---

    $odunc_tarihi = date('Y-m-d');
    $son_iade_tarihi_db = date('Y-m-d', strtotime($odunc_tarihi . " + " . $varsayilan_iade_suresi_gun . " days"));
    
    $sql1 = "INSERT INTO islemler (okul_id, kitap_id, ogrenci_id, odunc_tarihi, son_iade_tarihi) VALUES (?, ?, ?, ?, ?)";
    $stmt1 = $mysqli->prepare($sql1);
    $stmt1->bind_param("iiiss", $okul_id, $kitap_id, $ogrenci_id, $odunc_tarihi, $son_iade_tarihi_db);
    $stmt1->execute();
    $stmt1->close();

    // 6. Stok güncelle
    $sql2 = "UPDATE kitaplar SET raftaki_adet = raftaki_adet - 1 WHERE id = ? AND okul_id = ?";
    $stmt2 = $mysqli->prepare($sql2);
    $stmt2->bind_param("ii", $kitap_id, $okul_id);
    $stmt2->execute();
    $stmt2->close();
    
    $mysqli->commit();
    $response['success'] = true;
    $response['message'] = 'Kitap başarıyla ödünç verildi. Son iade tarihi: ' . date("d.m.Y", strtotime($son_iade_tarihi_db));

} catch (Exception $e) {
    $mysqli->rollback();
    $response['message'] = $e->getMessage();
}

$mysqli->close();
echo json_encode($response);
exit;
?>