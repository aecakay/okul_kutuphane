<?php
// GÜVENLİK UYARISI: Bu dosya, yeni bir yönetici oluşturmak için kullanılır.
// İşiniz bittikten sonra bu dosyayı sunucudan silmeniz önemle tavsiye edilir.

require_once "config.php";

// ------------ DEĞİŞTİRİLECEK ALANLAR ------------
// 1. Yeni yöneticinin atanacağı okulun ID'sini buraya yazın.
// phpMyAdmin'den `okullar` tablosuna bakarak ID'yi öğrenebilirsiniz. (Genellikle ilk okul için 1'dir)
$okul_id_atanacak = 1;

// 2. Yeni yöneticinin bilgilerini girin.
$kullanici_adi = "yeni_admin";
$sifre = "CokGuv_enliBirSifre123";
// ------------------------------------------------


// Kontroller
if ($okul_id_atanacak <= 0) {
    die("HATA: Lütfen dosyanın içindeki \$okul_id_atanacak değişkenine geçerli bir okul ID'si girin.");
}
if (strlen($sifre) < 6) {
    die("HATA: Lütfen en az 6 karakterli bir şifre belirleyin.");
}

// Yeni şifreyi güvenli bir şekilde hash'le
$hashed_sifre = password_hash($sifre, PASSWORD_DEFAULT);

// Veritabanına yeni yöneticiyi ekle
$sql = "INSERT INTO yoneticiler (okul_id, kullanici_adi, sifre) VALUES (?, ?, ?)";

if($stmt = $mysqli->prepare($sql)){
    $stmt->bind_param("iss", $okul_id_atanacak, $kullanici_adi, $hashed_sifre);
    
    if($stmt->execute()){
        echo "<h1>İşlem Başarılı!</h1>";
        echo "<p>Kullanıcı adı '<strong>" . htmlspecialchars($kullanici_adi) . "</strong>' olan yeni yönetici, okul ID'si <strong>" . $okul_id_atanacak . "</strong> olan okula başarıyla eklendi.</p>";
        echo "<p style='color:red; font-weight:bold;'>GÜVENLİK UYARISI: Lütfen işiniz bittikten sonra bu dosyayı (`yonetici_olustur.php`) sunucudan silin!</p>";
    } else{
        echo "<h1>Hata!</h1>";
        echo "<p>Yönetici oluşturulurken bir hata oluştu: " . $stmt->error . "</p>";
        echo "<p>Bu hata genellikle aynı kullanıcı adının daha önce kaydedilmiş olmasından kaynaklanır.</p>";
    }
    $stmt->close();
} else {
    echo "<h1>Hata!</h1>";
    echo "<p>SQL sorgusu hazırlanamadı: " . $mysqli->error . "</p>";
}
$mysqli->close();
?>