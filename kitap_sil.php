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
$sil_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($sil_id <= 0) {
    $response['message'] = 'Geçersiz kitap ID\'si.';
    echo json_encode($response);
    exit;
}

$mysqli->begin_transaction();
try {
    // 2. Silme Kontrolü: Kitap o okula ait mi ve ödünçte mi?
    $sql_check = "SELECT toplam_adet, raftaki_adet FROM kitaplar WHERE id = ? AND okul_id = ?";
    if ($stmt_check = $mysqli->prepare($sql_check)) {
        $stmt_check->bind_param("ii", $sil_id, $okul_id);
        $stmt_check->execute();
        $stmt_check->store_result();
        if ($stmt_check->num_rows > 0) {
            $stmt_check->bind_result($toplam_adet, $raftaki_adet);
            $stmt_check->fetch();
            if ($toplam_adet > $raftaki_adet) {
                throw new Exception('HATA: Bu kitaba ait ödünçte kopyalar bulunduğu için silinemez.');
            }
        } else {
            throw new Exception('Kitap bulunamadı veya bu kitabı silme yetkiniz yok.');
        }
        $stmt_check->close();
    }
    
    // 3. Silme İşlemi
    $sql_delete = "DELETE FROM kitaplar WHERE id = ? AND okul_id = ?";
    if ($stmt_delete = $mysqli->prepare($sql_delete)) {
        $stmt_delete->bind_param("ii", $sil_id, $okul_id);
        $stmt_delete->execute();
        if ($stmt_delete->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = 'Kitap başarıyla silindi.';
        } else {
            throw new Exception('Silinecek kitap bulunamadı.');
        }
        $stmt_delete->close();
    }
    
    $mysqli->commit();

} catch (Exception $e) {
    $mysqli->rollback();
    $response['message'] = $e->getMessage();
}

$mysqli->close();
echo json_encode($response);
exit;
?>