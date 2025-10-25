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

// 2. Gelen Veriyi Kontrol Et
$idler = json_decode($_POST['idler'] ?? '[]');
if (!is_array($idler) || empty($idler)) {
    $response['message'] = 'Geçersiz veri formatı.';
    echo json_encode($response);
    exit;
}
$sanitized_idler = array_filter($idler, 'is_numeric');
$placeholders = implode(',', array_fill(0, count($sanitized_idler), '?'));
$types = str_repeat('i', count($sanitized_idler));

$mysqli->begin_transaction();
try {
    // 3. Silme Kontrolü: Seçilen kitaplardan herhangi biri ödünçte mi?
    $silinemeyen_kitaplar = [];
    $sql_check = "SELECT kitap_adi FROM kitaplar WHERE id IN ($placeholders) AND okul_id = ? AND raftaki_adet < toplam_adet";
    if ($stmt_check = $mysqli->prepare($sql_check)) {
        $params = array_merge($sanitized_idler, [$okul_id]);
        $types_check = $types . 'i';
        $stmt_check->bind_param($types_check, ...$params);
        $stmt_check->execute();
        $stmt_check->store_result();
        if ($stmt_check->num_rows > 0) {
            $stmt_check->bind_result($kitap_adi);
            while($stmt_check->fetch()){ $silinemeyen_kitaplar[] = $kitap_adi; }
            throw new Exception('HATA: Bazı kitaplar ödünçte olduğu için silinemedi: ' . implode(', ', $silinemeyen_kitaplar));
        }
        $stmt_check->close();
    }

    // 4. Silme İşlemi
    $sql_delete = "DELETE FROM kitaplar WHERE id IN ($placeholders) AND okul_id = ?";
    if ($stmt_delete = $mysqli->prepare($sql_delete)) {
        $params_delete = array_merge($sanitized_idler, [$okul_id]);
        $types_delete = $types . 'i';
        $stmt_delete->bind_param($types_delete, ...$params_delete);
        $stmt_delete->execute();
        $etkilenen_satir = $stmt_delete->affected_rows;
        if ($etkilenen_satir > 0) {
            $response['success'] = true;
            $response['message'] = $etkilenen_satir . ' kitap başarıyla silindi.';
        } else {
            throw new Exception('Seçilen kitaplar bulunamadı veya bu kitapları silme yetkiniz yok.');
        }
        $stmt_delete->close();
    }
    
    $mysqli->commit();
} catch(Exception $e) {
    $mysqli->rollback();
    $response['message'] = $e->getMessage();
}

$mysqli->close();
echo json_encode($response);
exit;
?>