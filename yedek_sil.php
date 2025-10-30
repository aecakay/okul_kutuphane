<?php
session_start();
// Güvenlik: Sadece ana admin bu işlemi yapabilir
if(!isset($_SESSION["username"]) || $_SESSION["username"] !== 'admin'){
    $_SESSION['toast_message'] = ['success' => false, 'message' => 'Bu işlemi sadece ana yönetici yapabilir.'];
    header("Location: ayarlar.php");
    exit;
}

if(isset($_POST['delete_backup']) && !empty($_POST['backup_file'])){
    $backup_file_name = basename($_POST['backup_file']);
    $backup_file_path = __DIR__ . '/yedekler/' . $backup_file_name;
    
    // Güvenlik ve dosya varlığı kontrolü
    if (strpos($backup_file_name, '/') !== false || strpos($backup_file_name, '\\') !== false || !file_exists($backup_file_path)) {
        $_SESSION['toast_message'] = ['success' => false, 'message' => 'Geçersiz veya bulunamayan yedek dosyası.'];
    } else {
        if(unlink($backup_file_path)){
            $_SESSION['toast_message'] = ['success' => true, 'message' => 'Yedek dosyası başarıyla silindi: ' . $backup_file_name];
        } else {
            $_SESSION['toast_message'] = ['success' => false, 'message' => 'Yedek dosyası silinirken bir hata oluştu.'];
        }
    }
} else {
    $_SESSION['toast_message'] = ['success' => false, 'message' => 'Geçersiz istek.'];
}

header("Location: ayarlar.php");
exit;
?>