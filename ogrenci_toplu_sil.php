<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';
session_start();

$response = ['success' => false, 'message' => 'Bilinmeyen bir hata oluştu.'];

// 1. Yetki Kontrolü (okul_id dahil)
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["okul_id"])) {
    $response['message'] = 'Yetkisiz erişim.';
    http_response_code(403);
    echo json_encode($response);
    exit;
}

// Oturumdan okul ID'sini al
$okul_id = $_SESSION["okul_id"];

// 2. Gelen Veriyi Kontrol Et
if (!isset($_POST['idler'])) {
    $response['message'] = 'Silinecek öğrenci seçilmedi.';
    echo json_encode($response);
    exit;
}

$idler = json_decode($_POST['idler']);

if (!is_array($idler) || empty($idler)) {
    $response['message'] = 'Geçersiz veri formatı.';
    echo json_encode($response);
    exit;
}

$sanitized_idler = array_filter($idler, 'is_numeric');
if(count($sanitized_idler) !== count($idler)){
    $response['message'] = 'Geçersiz öğrenci ID\'leri içeriyor.';
    echo json_encode($response);
    exit;
}
$placeholders = implode(',', array_fill(0, count($sanitized_idler), '?'));
$types = str_repeat('i', count($sanitized_idler));

$mysqli->begin_transaction();

try {
    // 3. Silme Kontrolü: Seçilen öğrencilerden herhangi birinin iade etmediği kitabı var mı? (Sadece o okulda)
    $silinemeyen_ogrenciler = [];
    $sql_check = "SELECT DISTINCT O.ad, O.soyad 
                  FROM islemler I
                  JOIN ogrenciler O ON I.ogrenci_id = O.id
                  WHERE I.ogrenci_id IN ($placeholders) AND I.okul_id = ? AND I.iade_tarihi IS NULL";

    if ($stmt_check = $mysqli->prepare($sql_check)) {
        // Parametreleri birleştir: öğrenci ID'leri + okul_id
        $params = array_merge($sanitized_idler, [$okul_id]);
        $types_check = $types . 'i';
        $stmt_check->bind_param($types_check, ...$params);
        $stmt_check->execute();
        $stmt_check->store_result();
        
        if ($stmt_check->num_rows > 0) {
            $stmt_check->bind_result($ad, $soyad);
            while($stmt_check->fetch()){
                $silinemeyen_ogrenciler[] = $ad . ' ' . $soyad;
            }
            $stmt_check->close();
            throw new Exception('HATA: Bazı öğrenciler iade etmedikleri kitaplar olduğu için silinemedi: ' . implode(', ', $silinemeyen_ogrenciler));
        }
        $stmt_check->close();
    } else {
        throw new Exception('Veritabanı kontrol hatası: ' . $mysqli->error);
    }

    // 4. Silme İşlemi (Sadece o okuldan)
    $sql_delete = "DELETE FROM ogrenciler WHERE id IN ($placeholders) AND okul_id = ?";
    if ($stmt_delete = $mysqli->prepare($sql_delete)) {
        // Parametreleri birleştir: öğrenci ID'leri + okul_id
        $params_delete = array_merge($sanitized_idler, [$okul_id]);
        $types_delete = $types . 'i';
        $stmt_delete->bind_param($types_delete, ...$params_delete);
        
        if ($stmt_delete->execute()) {
            $etkilenen_satir = $stmt_delete->affected_rows;
            if ($etkilenen_satir > 0) {
                $response['success'] = true;
                $response['message'] = $etkilenen_satir . ' öğrenci başarıyla silindi.';
            } else {
                throw new Exception('Seçilen öğrenciler bulunamadı veya bu öğrencileri silme yetkiniz yok.');
            }
        } else {
            throw new Exception('Silme işlemi sırasında bir veritabanı hatası oluştu.');
        }
        $stmt_delete->close();
    } else {
        throw new Exception('Silme sorgusu hazırlanamadı: ' . $mysqli->error);
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