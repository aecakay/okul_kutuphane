<?php
session_start();
// Güvenlik: Giriş yapılmamışsa veya okul ID'si yoksa işlemi durdur
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["okul_id"])){
    header("location: login.php");
    exit;
}
require_once "config.php";

// Oturumdan mevcut okulun ID'sini al
$okul_id = $_SESSION["okul_id"];

$type = isset($_GET['type']) ? $_GET['type'] : '';
$filename = "export_" . date('Y-m-d') . ".csv";
$sql = "";
$columns = [];
$params = [$okul_id];
$types = "i";

if ($type === 'kitaplar') {
    $filename = "kitap_listesi_" . date('Y-m-d') . ".csv";
    // SQL SORGUSU GÜNCELLENDİ: Sadece o okula ait kitapları seçer
    $sql = "SELECT kitap_adi, yazar, isbn, basim_yili, sayfa_sayisi, kapak_url, toplam_adet FROM kitaplar WHERE okul_id = ? ORDER BY kitap_adi";
    $columns = ['Kitap Adi', 'Yazar', 'ISBN', 'Basim Yili', 'Sayfa Sayisi', 'Kapak URL', 'Toplam Adet'];
}
elseif ($type === 'ogrenciler') {
    $filename = "ogrenci_listesi_" . date('Y-m-d') . ".csv";
    // SQL SORGUSU GÜNCELLENDİ: Sadece o okula ait öğrencileri seçer
    $sql = "SELECT ogrenci_no, ad, soyad, sinif FROM ogrenciler WHERE okul_id = ? ORDER BY ogrenci_no + 0 ASC";
    $columns = ['Ogrenci No', 'Ad', 'Soyad', 'Sinif'];
}
else {
    die("Geçersiz dışa aktarma türü.");
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$output = fopen('php://output', 'w');

// UTF-8 BOM (Excel uyumluluğu için)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Sütun başlıklarını yaz
fputcsv($output, $columns);

// Verileri güvenli bir şekilde çek ve yaz
if ($stmt = $mysqli->prepare($sql)) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, $row);
        }
    }
    $stmt->close();
}

$mysqli->close();
fclose($output);
exit;
?>