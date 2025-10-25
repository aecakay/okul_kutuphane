<?php
// GÜVENLİK UYARISI: Bu dosya, yeni bir okul ve yönetici oluşturmak için kullanılır.
// İşiniz bittikten sonra bu dosyayı sunucudan SİLMENİZ ÖNEMLE TAVSİYE EDİLİR.

require_once "config.php";

// ------------ DEĞİŞTİRİLECEK ALANLAR ------------
// 1. OLUŞTURULACAK YENİ OKULUN BİLGİLERİ
$yeni_okul_adi = "İkinci Okul Adı Buraya";
$yeni_okul_subdomain = "ikinciokul"; // (Örn: ikinciokul.site.com için) Benzersiz olmalı, boşluk ve Türkçe karakter içermemeli.

// 2. YENİ OKULA AİT İLK YÖNETİCİNİN BİLGİLERİ
$yeni_yonetici_kullanici_adi = "ikinci_admin";
$yeni_yonetici_sifre = "YoneticiIcin_CokGuv_enliSifre123";
// ------------------------------------------------


// Kontroller
if (empty($yeni_okul_adi) || empty($yeni_okul_subdomain) || empty($yeni_yonetici_kullanici_adi)) {
    die("HATA: Lütfen dosyanın içindeki okul ve yönetici bilgilerini doldurun.");
}
if (strlen($yeni_yonetici_sifre) < 6) {
    die("HATA: Lütfen en az 6 karakterli bir yönetici şifresi belirleyin.");
}

$mysqli->begin_transaction();

try {
    // Adım 1: Yeni okulu `okullar` tablosuna ekle
    $sql_okul = "INSERT INTO okullar (okul_adi, subdomain) VALUES (?, ?)";
    if ($stmt_okul = $mysqli->prepare($sql_okul)) {
        $stmt_okul->bind_param("ss", $yeni_okul_adi, $yeni_okul_subdomain);
        if (!$stmt_okul->execute()) {
            throw new Exception("Okul oluşturulamadı: " . $stmt_okul->error);
        }
        // Yeni oluşturulan okulun ID'sini al
        $yeni_okul_id = $stmt_okul->insert_id;
        $stmt_okul->close();
    } else {
        throw new Exception("Okul sorgusu hazırlanamadı: " . $mysqli->error);
    }

    // Adım 2: Yeni yöneticiyi, yeni okulun ID'si ile `yoneticiler` tablosuna ekle
    $hashed_sifre = password_hash($yeni_yonetici_sifre, PASSWORD_DEFAULT);
    $sql_yonetici = "INSERT INTO yoneticiler (okul_id, kullanici_adi, sifre) VALUES (?, ?, ?)";
    if ($stmt_yonetici = $mysqli->prepare($sql_yonetici)) {
        $stmt_yonetici->bind_param("iss", $yeni_okul_id, $yeni_yonetici_kullanici_adi, $hashed_sifre);
        if (!$stmt_yonetici->execute()) {
            throw new Exception("Yönetici oluşturulamadı: " . $stmt_yonetici->error);
        }
        $stmt_yonetici->close();
    } else {
        throw new Exception("Yönetici sorgusu hazırlanamadı: " . $mysqli->error);
    }

    // Her şey yolundaysa, işlemi onayla
    $mysqli->commit();

    echo "<h1>İşlem Başarıyla Tamamlandı!</h1>";
    echo "<p>Okul Adı: '<strong>" . htmlspecialchars($yeni_okul_adi) . "</strong>' (ID: {$yeni_okul_id})</p>";
    echo "<p>Yönetici Kullanıcı Adı: '<strong>" . htmlspecialchars($yeni_yonetici_kullanici_adi) . "</strong>'</p>";
    echo "<p>Yeni yönetici, belirtilen şifre ile artık sisteme giriş yapabilir.</p>";
    echo "<p style='color:red; font-weight:bold;'>GÜVENLİK UYARISI: Lütfen işiniz bittikten sonra bu dosyayı (`okul_ekle.php`) sunucudan SİLİN!</p>";

} catch (Exception $e) {
    // Bir hata olursa, tüm işlemleri geri al
    $mysqli->rollback();
    echo "<h1>HATA!</h1>";
    echo "<p>İşlem sırasında bir hata oluştu ve tüm değişiklikler geri alındı.</p>";
    echo "<p><strong>Hata Detayı:</strong> " . $e->getMessage() . "</p>";
    echo "<p>Bu hata genellikle aynı 'subdomain' veya 'yönetici kullanıcı adı'nı tekrar kullanmaya çalıştığınızda oluşur.</p>";
}

$mysqli->close();
?>