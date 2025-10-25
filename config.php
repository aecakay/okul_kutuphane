<?php
// Veritabanı Bağlantı Bilgileri
define('DB_SERVER', 'localhost');
define('DB_NAME', 'okul_kutuphane'); 
define('DB_USERNAME', 'kutuphane_user'); 
define('DB_PASSWORD', 'x.^9e4CYKju#ervC');

// Bağlantıyı oluşturma
$mysqli = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Bağlantıyı kontrol etme
if($mysqli === false){
    die("HATA: Veritabanı bağlantısı kurulamadı. " . $mysqli->connect_error);
}

// Türkçe karakter sorunu yaşamamak için
$mysqli->set_charset("utf8mb4");
?>