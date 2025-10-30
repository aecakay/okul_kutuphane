<?php
// Oturumu başlat
session_start();

// Öğrenciye ait tüm oturum değişkenlerini temizle
$_SESSION = array();

// Oturumu sonlandır
session_destroy();

// Öğrenci giriş sayfasına yönlendir
header("location: ogrenci_giris.php");
exit;
?>