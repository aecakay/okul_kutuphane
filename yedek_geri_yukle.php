<?php
session_start();
// Güvenlik: Sadece ana admin bu işlemi yapabilir
if(!isset($_SESSION["username"]) || $_SESSION["username"] !== 'admin'){
    $_SESSION['toast_message'] = ['success' => false, 'message' => 'Bu işlemi sadece ana yönetici yapabilir.'];
    header("Location: ayarlar.php");
    exit;
}
require_once "config.php";

if(isset($_POST['restore_backup']) && !empty($_POST['backup_file'])){
    $backup_file_name = basename($_POST['backup_file']);
    $backup_file_path = __DIR__ . '/yedekler/' . $backup_file_name;

    // Güvenlik: Dosya adında tehlikeli karakterler var mı kontrol et ve dosyanın varlığını onayla
    if (strpos($backup_file_name, '/') !== false || strpos($backup_file_name, '\\') !== false || !file_exists($backup_file_path)) {
        $_SESSION['toast_message'] = ['success' => false, 'message' => 'Geçersiz veya bulunamayan yedek dosyası.'];
        header("Location: ayarlar.php");
        exit;
    }

    // SQL dosyasının içeriğini oku
    $sql_commands = file_get_contents($backup_file_path);

    // Önce mevcut tüm tabloları sil (DİKKAT: TEHLİKELİ İŞLEM)
    $mysqli->query('SET foreign_key_checks = 0');
    $tables = ['okullar', 'yoneticiler', 'ayarlar', 'islemler', 'kitaplar', 'ogrenciler', 'rezervasyonlar'];
    foreach($tables as $table){
        $mysqli->query('DROP TABLE IF EXISTS '.$table);
    }
    $mysqli->query('SET foreign_key_checks = 1');

    // Yedekten gelen SQL komutlarını çalıştır
    if ($mysqli->multi_query($sql_commands)) {
        // multi_query'den sonra bağlantıyı temizle
        while ($mysqli->next_result()) {
            if ($result = $mysqli->store_result()) {
                $result->free();
            }
        }
        $_SESSION['toast_message'] = ['success' => true, 'message' => 'Veritabanı başarıyla geri yüklendi: ' . $backup_file_name];
    } else {
        $_SESSION['toast_message'] = ['success' => false, 'message' => 'Geri yükleme sırasında bir hata oluştu: ' . $mysqli->error];
    }
} else {
    $_SESSION['toast_message'] = ['success' => false, 'message' => 'Geçersiz istek.'];
}

$mysqli->close();
header("Location: ayarlar.php");
exit;
?>