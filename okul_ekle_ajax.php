<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';
session_start();

$response = ['success' => false, 'message' => 'Bilinmeyen bir hata oluştu.'];

// 1. GÜVENLİK KONTROLÜ: Sadece "admin" kullanıcısı bu işlemi yapabilir.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["username"] !== 'admin') {
    $response['message'] = 'Bu işlemi yapma yetkiniz yok.';
    http_response_code(403); // Forbidden
    echo json_encode($response);
    exit;
}

// 2. GELEN VERİLERİ KONTROL ET VE TEMİZLE
$yeni_okul_adi = trim($_POST['yeni_okul_adi'] ?? '');
$yeni_okul_subdomain = trim($_POST['yeni_okul_subdomain'] ?? '');
$yeni_yonetici_kullanici_adi = trim($_POST['yeni_yonetici_kullanici_adi'] ?? '');
$yeni_yonetici_sifre = $_POST['yeni_yonetici_sifre'] ?? '';

if (empty($yeni_okul_adi) || empty($yeni_okul_subdomain) || empty($yeni_yonetici_kullanici_adi)) {
    $response['message'] = 'Lütfen tüm zorunlu alanları doldurun.';
    echo json_encode($response);
    exit;
}
if (strlen($yeni_yonetici_sifre) < 6) {
    $response['message'] = 'Yönetici şifresi en az 6 karakter olmalıdır.';
    echo json_encode($response);
    exit;
}
if (!preg_match('/^[a-z0-9-]+$/', $yeni_okul_subdomain)) {
    $response['message'] = 'Alt alan adı (subdomain) sadece küçük harf, rakam ve tire (-) içerebilir.';
    echo json_encode($response);
    exit;
}


// 3. VERİTABANI İŞLEMLERİ
$mysqli->begin_transaction();

try {
    // Adım A: Yeni okulu `okullar` tablosuna ekle
    $sql_okul = "INSERT INTO okullar (okul_adi, subdomain) VALUES (?, ?)";
    if ($stmt_okul = $mysqli->prepare($sql_okul)) {
        $stmt_okul->bind_param("ss", $yeni_okul_adi, $yeni_okul_subdomain);
        if (!$stmt_okul->execute()) {
            // Benzersiz anahtar hatası (duplicate subdomain)
            if ($mysqli->errno == 1062) {
                throw new Exception("Bu alt alan adı (subdomain) zaten kullanılıyor. Lütfen farklı bir tane seçin.");
            }
            throw new Exception("Okul oluşturulamadı: " . $stmt_okul->error);
        }
        // Yeni oluşturulan okulun ID'sini al
        $yeni_okul_id = $stmt_okul->insert_id;
        $stmt_okul->close();
    } else {
        throw new Exception("Okul sorgusu hazırlanamadı: " . $mysqli->error);
    }

    // Adım B: Yeni yöneticiyi, yeni okulun ID'si ile `yoneticiler` tablosuna ekle
    $hashed_sifre = password_hash($yeni_yonetici_sifre, PASSWORD_DEFAULT);
    $sql_yonetici = "INSERT INTO yoneticiler (okul_id, kullanici_adi, sifre) VALUES (?, ?, ?)";
    if ($stmt_yonetici = $mysqli->prepare($sql_yonetici)) {
        $stmt_yonetici->bind_param("iss", $yeni_okul_id, $yeni_yonetici_kullanici_adi, $hashed_sifre);
        if (!$stmt_yonetici->execute()) {
             // Benzersiz anahtar hatası (duplicate username)
            if ($mysqli->errno == 1062) {
                throw new Exception("Bu yönetici kullanıcı adı zaten kullanılıyor. Lütfen farklı bir tane seçin.");
            }
            throw new Exception("Yönetici oluşturulamadı: " . $stmt_yonetici->error);
        }
        $stmt_yonetici->close();
    } else {
        throw new Exception("Yönetici sorgusu hazırlanamadı: " . $mysqli->error);
    }

    // Her şey yolundaysa, işlemi onayla
    $mysqli->commit();

    $response['success'] = true;
    $response['message'] = "Okul ve yönetici başarıyla oluşturuldu! Sayfa yenileniyor...";

} catch (Exception $e) {
    // Bir hata olursa, tüm işlemleri geri al
    $mysqli->rollback();
    $response['message'] = 'HATA: ' . $e->getMessage();
}

$mysqli->close();
echo json_encode($response);
exit;
?>