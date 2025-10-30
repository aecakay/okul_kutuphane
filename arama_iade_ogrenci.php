<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';
session_start();

$response = [];

// Güvenlik: Giriş yapılmamışsa veya okul ID'si yoksa işlemi durdur
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["okul_id"])) {
    echo json_encode(['error' => 'Yetkisiz erişim.']);
    exit;
}

$okul_id = $_SESSION["okul_id"];
$arama_terimi = isset($_GET['term']) ? trim($_GET['term']) : '';

if (empty($arama_terimi)) {
    echo json_encode($response);
    exit;
}

// SQL SORGUSU: Sadece üzerinde aktif ödünç kaydı (I.iade_tarihi IS NULL) olan öğrencileri getir.
$sql = "SELECT 
            O.id, O.ogrenci_no, O.ad, O.soyad, O.sinif, COUNT(I.id) as odunc_sayisi
        FROM ogrenciler O
        JOIN islemler I ON O.id = I.ogrenci_id
        WHERE O.okul_id = ? AND I.iade_tarihi IS NULL
        AND (O.ogrenci_no LIKE ? OR O.ad LIKE ? OR O.soyad LIKE ?)
        GROUP BY O.id
        ORDER BY O.ad
        LIMIT 10";

$stmt = $mysqli->prepare($sql);
if ($stmt === false) {
    $error = $mysqli->error;
    $mysqli->close();
    echo json_encode(['error' => "SQL Hazırlık Hatası: " . $error]);
    exit;
}

$arama_wildcard = "%{$arama_terimi}%";
$stmt->bind_param("isss", $okul_id, $arama_wildcard, $arama_wildcard, $arama_wildcard);
$stmt->execute();

// --- get_result() Yerine Uyumlu Kod ---
$stmt->store_result();
// 6 sütunu bağla
$stmt->bind_result($col_id, $col_ogrenci_no, $col_ad, $col_soyad, $col_sinif, $col_odunc_sayisi);

while ($stmt->fetch()) {
    $label = htmlspecialchars($col_ad . ' ' . $col_soyad) . ' (' . htmlspecialchars($col_ogrenci_no) . ') - ' . htmlspecialchars($col_sinif) . ' [' . $col_odunc_sayisi . ' kitap]';
    $response[] = [
        'id' => (int)$col_id,
        'value' => htmlspecialchars($col_ogrenci_no),
        'label' => $label,
        'odunc_sayisi' => (int)$col_odunc_sayisi
    ];
}
// --- DEĞİŞİKLİK SONU ---

$stmt->close();
$mysqli->close();

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>