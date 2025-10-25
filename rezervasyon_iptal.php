<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';
session_start();

$response = ['success' => false, 'message' => 'Bilinmeyen bir hata oluştu.'];

// 1. Yetki Kontrolü
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["okul_id"])) {
    $response['message'] = 'Bu işlemi yapmak için yönetici girişi yapmalısınız.';
    http_response_code(403);
    echo json_encode($response);
    exit;
}

$okul_id = $_SESSION["okul_id"];
$rez_id = isset($_POST['rezervasyon_id']) ? (int)$_POST['rezervasyon_id'] : 0;

if ($rez_id <= 0) {
    $response['message'] = 'Geçersiz rezervasyon ID\'si.';
    echo json_encode($response);
    exit;
}

// 2. Rezervasyonu 'bekliyor' veya 'bildirildi' durumundan 'iptal' durumuna güncelle (Sadece o okula aitse)
$sql = "UPDATE rezervasyonlar SET durum = 'iptal' WHERE id = ? AND okul_id = ? AND (durum = 'bekliyor' OR durum = 'bildirildi')";
if($stmt = $mysqli->prepare($sql)) {
    $stmt->bind_param("ii", $rez_id, $okul_id);
    if($stmt->execute()) {
        if($stmt->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = 'Rezervasyon başarıyla iptal edildi.';
        } else {
            $response['message'] = 'Rezervasyon bulunamadı, bu okula ait değil veya zaten işlenmiş.';
        }
    } else {
        $response['message'] = 'Veritabanı hatası: ' . $stmt->error;
    }
    $stmt->close();
} else {
    $response['message'] = 'Sorgu hazırlanamadı: ' . $mysqli->error;
}

$mysqli->close();
echo json_encode($response);
exit;
?>