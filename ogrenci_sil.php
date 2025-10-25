<?php
// Bu dosya sadece AJAX isteklerine cevap verir.
header('Content-Type: application/json; charset=utf-8');

require_once 'config.php';
session_start();

$response = ['success' => false, 'message' => 'Bilinmeyen bir hata oluştu.'];

// 1. Yetki Kontrolü (okul_id dahil)
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["okul_id"])) {
    $response['message'] = 'Yetkisiz erişim. Lütfen tekrar giriş yapın.';
    http_response_code(403);
    echo json_encode($response);
    exit;
}

// Oturumdan okul ID'sini al
$okul_id = $_SESSION["okul_id"];

// 2. Gelen Veriyi Kontrol Et
if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    $response['message'] = 'Geçersiz öğrenci ID\'si gönderildi.';
    echo json_encode($response);
    exit;
}

$sil_id = (int)$_POST['id'];

$mysqli->begin_transaction();

try {
    // 3. Silme Kontrolü: Öğrencinin iade etmediği kitabı var mı? (Sadece o okulda)
    $sql_check = "SELECT id FROM islemler WHERE ogrenci_id = ? AND okul_id = ? AND iade_tarihi IS NULL LIMIT 1";
    if ($stmt_check = $mysqli->prepare($sql_check)) {
        $stmt_check->bind_param("ii", $sil_id, $okul_id);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $stmt_check->close();
            throw new Exception('HATA: Bu öğrencinin iade etmediği kitaplar bulunduğu için silinemez.');
        }
        $stmt_check->close();
    } else {
        throw new Exception('Veritabanı kontrol hatası: ' . $mysqli->error);
    }

    // 4. Silme İşlemi (Sadece o okuldan)
    // Bu, bir yöneticinin başka okulun öğrencisini silmesini engeller.
    $sql_delete = "DELETE FROM ogrenciler WHERE id = ? AND okul_id = ?";
    if ($stmt_delete = $mysqli->prepare($sql_delete)) {
        $stmt_delete->bind_param("ii", $sil_id, $okul_id);
        if ($stmt_delete->execute()) {
            if ($stmt_delete->affected_rows > 0) {
                $response['success'] = true;
                $response['message'] = 'Öğrenci başarıyla silindi.';
            } else {
                // Silinecek öğrenci bulunamadı (ya ID yanlış ya da başka bir okula ait)
                throw new Exception('Öğrenci bulunamadı veya bu öğrenciyi silme yetkiniz yok.');
            }
        } else {
            throw new Exception('Silme işlemi sırasında bir veritabanı hatası oluştu.');
        }
        $stmt_delete->close();
    } else {
        throw new Exception('Silme sorgusu hazırlanamadı: ' . $mysqli->error);
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