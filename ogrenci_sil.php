<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';
session_start();

$response = ['success' => false, 'message' => 'Bilinmeyen bir hata oluştu.'];

// Yetki Kontrolü
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["okul_id"])) {
    $response['message'] = 'Yetkisiz erişim.';
    http_response_code(403);
    echo json_encode($response);
    exit;
}

$okul_id = $_SESSION["okul_id"];
$ogrenci_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($ogrenci_id <= 0) {
    $response['message'] = 'Geçersiz öğrenci ID\'si.';
    echo json_encode($response);
    exit;
}

$mysqli->begin_transaction();

try {
    // 1. ADIM: Öğrencinin işlem geçmişi var mı diye kontrol et
    $sql_check = "SELECT id FROM islemler WHERE ogrenci_id = ? AND okul_id = ? LIMIT 1";
    $stmt_check = $mysqli->prepare($sql_check);
    $stmt_check->bind_param("ii", $ogrenci_id, $okul_id);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        // Eğer işlem geçmişi varsa, silme ve kullanıcıyı bilgilendir
        $stmt_check->close();
        throw new Exception('Bu öğrencinin kitap alma/verme geçmişi bulunduğu için silinemez. Öğrenci kaydını silmek için önce işlem geçmişini temizlemeniz gerekir.');
    }
    $stmt_check->close();

    // 2. ADIM: Öğrencinin rezervasyon kaydı var mı diye kontrol et
    $sql_rez_check = "SELECT id FROM rezervasyonlar WHERE ogrenci_id = ? AND okul_id = ? LIMIT 1";
    $stmt_rez_check = $mysqli->prepare($sql_rez_check);
    $stmt_rez_check->bind_param("ii", $ogrenci_id, $okul_id);
    $stmt_rez_check->execute();
    $stmt_rez_check->store_result();

    if($stmt_rez_check->num_rows > 0){
        // Rezervasyonları sil
        $stmt_rez_check->close();
        $sql_delete_rez = "DELETE FROM rezervasyonlar WHERE ogrenci_id = ? AND okul_id = ?";
        $stmt_delete_rez = $mysqli->prepare($sql_delete_rez);
        $stmt_delete_rez->bind_param("ii", $ogrenci_id, $okul_id);
        $stmt_delete_rez->execute();
        $stmt_delete_rez->close();
    } else {
        $stmt_rez_check->close();
    }


    // 3. ADIM: İşlem geçmişi yoksa, öğrenciyi sil
    $sql_delete = "DELETE FROM ogrenciler WHERE id = ? AND okul_id = ?";
    $stmt_delete = $mysqli->prepare($sql_delete);
    $stmt_delete->bind_param("ii", $ogrenci_id, $okul_id);
    
    if ($stmt_delete->execute()) {
        if ($stmt_delete->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = 'Öğrenci başarıyla silindi.';
        } else {
            throw new Exception('Öğrenci bulunamadı veya silme yetkiniz yok.');
        }
    } else {
        throw new Exception('Öğrenci silinirken bir veritabanı hatası oluştu.');
    }
    $stmt_delete->close();
    
    $mysqli->commit();

} catch (Exception $e) {
    $mysqli->rollback();
    $response['message'] = $e->getMessage();
}

$mysqli->close();
echo json_encode($response);
exit;
?>