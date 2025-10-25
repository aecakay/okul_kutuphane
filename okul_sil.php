<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';
session_start();

$response = ['success' => false, 'message' => 'Bilinmeyen bir hata oluştu.'];

// 1. GÜVENLİK KONTROLÜ: Sadece "admin" kullanıcısı bu işlemi yapabilir ve kendisiyle aynı ID'yi silemez.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["username"] !== 'admin') {
    $response['message'] = 'Bu işlemi yapmaya yetkiniz yok.';
    http_response_code(403);
    echo json_encode($response);
    exit;
}

$silinecek_okul_id = isset($_POST['okul_id']) ? (int)$_POST['okul_id'] : 0;
$mevcut_okul_id = $_SESSION["okul_id"];

if ($silinecek_okul_id <= 1 || $silinecek_okul_id === $mevcut_okul_id) {
    // 1 ID'li varsayılan okulu veya aktif olarak giriş yapılan okulu silmeyi engelleriz.
    $response['message'] = 'HATA: Ana okulu veya şu anda aktif olan okulu silemezsiniz.';
    echo json_encode($response);
    exit;
}

$mysqli->begin_transaction();

try {
    // 2. KRİTİK KONTROL: OKULA AİT ÖDÜNÇTE KİTAP VAR MI?
    $sql_check_islemler = "SELECT COUNT(id) FROM islemler WHERE okul_id = ? AND iade_tarihi IS NULL";
    if ($stmt_check = $mysqli->prepare($sql_check_islemler)) {
        $stmt_check->bind_param("i", $silinecek_okul_id);
        $stmt_check->execute();
        $stmt_check->bind_result($aktif_islem_sayisi);
        $stmt_check->fetch();
        $stmt_check->close();

        if ($aktif_islem_sayisi > 0) {
            throw new Exception("HATA: Okula ait {$aktif_islem_sayisi} adet ödünçte kitap bulunmaktadır. Tüm kitaplar iade edilmeden okul silinemez.");
        }
    } else {
        throw new Exception("Veritabanı kontrol sorgusu hazırlanamadı.");
    }
    
    // 3. ÇOKLU SİLME İŞLEMİ (CASCADE DELETE)
    // Önce bağımlı tablolar, sonra ana kayıtlar silinir.
    $tablolar = [
        'rezervasyonlar', 
        'islemler', 
        'kitaplar', 
        'ogrenciler', 
        'yoneticiler',
        'ayarlar' // Bu okulla ilgili ayarları sil
    ];
    
    foreach ($tablolar as $tablo) {
        $sql_delete = "DELETE FROM {$tablo} WHERE okul_id = ?";
        if ($stmt_delete = $mysqli->prepare($sql_delete)) {
            $stmt_delete->bind_param("i", $silinecek_okul_id);
            $stmt_delete->execute();
            $stmt_delete->close();
        } else {
            throw new Exception("HATA: '{$tablo}' tablosu silme sorgusu hazırlanamadı.");
        }
    }

    // 4. SON OLARAK OKULU SİL
    $sql_delete_okul = "DELETE FROM okullar WHERE id = ?";
    if ($stmt_delete_okul = $mysqli->prepare($sql_delete_okul)) {
        $stmt_delete_okul->bind_param("i", $silinecek_okul_id);
        $stmt_delete_okul->execute();
        if ($stmt_delete_okul->affected_rows == 0) {
            throw new Exception("Okul bulunamadı veya silinemedi.");
        }
        $stmt_delete_okul->close();
    }
    
    $mysqli->commit();
    $response['success'] = true;
    $response['message'] = 'Okul ve tüm verileri başarıyla silinmiştir.';

} catch (Exception $e) {
    $mysqli->rollback();
    $response['message'] = $e->getMessage();
}

$mysqli->close();
echo json_encode($response);
exit;