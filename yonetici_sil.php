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

$silinecek_user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$mevcut_user_id = $_SESSION["id"];
$mevcut_okul_id = $_SESSION["okul_id"];
$is_super_admin = $_SESSION["username"] === 'admin';

if ($silinecek_user_id <= 0) {
    $response['message'] = 'Geçersiz kullanıcı ID\'si.';
    echo json_encode($response);
    exit;
}

// 2. KENDİNİ SİLME KONTROLÜ
if ($silinecek_user_id === $mevcut_user_id) {
    $response['message'] = 'HATA: Kendi hesabınızı silemezsiniz.';
    echo json_encode($response);
    exit;
}

$mysqli->begin_transaction();

try {
    // 3. Silme Güvenliği Kontrolü
    if ($is_super_admin) {
        // Süper admin (admin): Sadece 'admin' olmayan herhangi bir kullanıcıyı silebilir.
        $sql_delete = "DELETE FROM yoneticiler WHERE id = ? AND kullanici_adi != 'admin'";
        $stmt_delete = $mysqli->prepare($sql_delete);
        $stmt_delete->bind_param("i", $silinecek_user_id);
    } else {
        // Normal yönetici: Sadece kendi okullarındaki (aynı okul_id) ve kendileri olmayan kullanıcıları silebilir.
        $sql_delete = "DELETE FROM yoneticiler WHERE id = ? AND okul_id = ? AND kullanici_adi != 'admin'";
        $stmt_delete = $mysqli->prepare($sql_delete);
        $stmt_delete->bind_param("ii", $silinecek_user_id, $mevcut_okul_id);
    }

    if ($stmt_delete->execute()) {
        if ($stmt_delete->affected_rows > 0) {
            $mysqli->commit();
            $response['success'] = true;
            $response['message'] = 'Yönetici hesabı başarıyla silindi.';
        } else {
            throw new Exception('Kullanıcı bulunamadı, "admin" hesabı silinemez veya bu işlemi yapmaya yetkiniz yok.');
        }
        $stmt_delete->close();
    } else {
        throw new Exception('Veritabanı hatası: ' . $stmt_delete->error);
    }

} catch (Exception $e) {
    $mysqli->rollback();
    $response['message'] = $e->getMessage();
}

$mysqli->close();
echo json_encode($response);
exit;
?>