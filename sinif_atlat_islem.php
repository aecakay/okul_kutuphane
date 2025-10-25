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

$mysqli->begin_transaction();
$toplam_etkilenen_kayit = 0; // HATA DÜZELTİLDİ: Değişken doğru isimle başlatıldı
$mezun_sayisi = 0;
$hata_var = false;

try {
    $mezuniyet_sinifi = 12; 

    // 2. O okula ait tüm mevcut sınıfları çek
    $siniflar = [];
    $sql_siniflar = "SELECT DISTINCT sinif FROM ogrenciler WHERE okul_id = ? AND sinif IS NOT NULL AND sinif != ''";
    if ($stmt_sinif = $mysqli->prepare($sql_siniflar)) {
        $stmt_sinif->bind_param("i", $okul_id);
        $stmt_sinif->execute();
        $result = $stmt_sinif->get_result();
        while ($row = $result->fetch_assoc()) {
            $siniflar[] = $row['sinif'];
        }
        $stmt_sinif->close();
    }

    // 3. Her bir sınıf için güncelleme yap
    foreach ($siniflar as $mevcut_sinif) {
        $yeni_sinif = null;
        $is_mezun = false;

        if (preg_match('/^\D*(\d+)/', $mevcut_sinif, $matches)) {
            $mevcut_sayi = (int)$matches[1];
            $harf_kismi = preg_replace('/^\D*\d+/', '', $mevcut_sinif);

            if ($mevcut_sayi >= $mezuniyet_sinifi) {
                $is_mezun = true;
            } else {
                $yeni_sinif = ($mevcut_sayi + 1) . $harf_kismi;
            }
        } else {
             continue; 
        }

        // 4. Veritabanında güncelleme sorgusunu çalıştır (sadece o okul için)
        if ($is_mezun) {
            $sql_update = "UPDATE ogrenciler SET sinif = 'MEZUN' WHERE sinif = ? AND okul_id = ?";
        } else {
            $sql_update = "UPDATE ogrenciler SET sinif = ? WHERE sinif = ? AND okul_id = ?";
        }

        if ($stmt = $mysqli->prepare($sql_update)) {
            if ($is_mezun) {
                $stmt->bind_param("si", $mevcut_sinif, $okul_id);
            } else {
                $stmt->bind_param("ssi", $yeni_sinif, $mevcut_sinif, $okul_id);
            }
            
            if ($stmt->execute()) {
                $toplam_etkilenen_kayit += $stmt->affected_rows;
                if ($is_mezun) {
                    $mezun_sayisi += $stmt->affected_rows;
                }
            } else {
                throw new Exception("Sorgu hatası: " . $stmt->error);
            }
            $stmt->close();
        } else {
            throw new Exception("Sorgu hazırlanamadı: " . $mysqli->error);
        }
    }
    
    // 5. İşlemi Sonuçlandır
    if (!$hata_var) {
        $mysqli->commit();
        $response['success'] = true;
        $response['message'] = "Sınıf atlatma işlemi tamamlandı. Toplam {$toplam_etkilenen_kayit} öğrenci güncellendi. ({$mezun_sayisi} öğrenci mezun oldu.)";
    } else {
        $mysqli->rollback();
    }

} catch (Exception $e) {
    $mysqli->rollback();
    $response['message'] = 'Kritik HATA: ' . $e->getMessage();
}

$mysqli->close();
echo json_encode($response);
exit;
?>