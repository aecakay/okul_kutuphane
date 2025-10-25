<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["okul_id"])) {
    http_response_code(403);
    echo json_encode(['error' => 'Yetkisiz erişim.']);
    exit;
}

$okul_id = $_SESSION["okul_id"];
$ogrenci_id = isset($_GET['ogrenci_id']) ? (int)$_GET['ogrenci_id'] : 0;

if ($ogrenci_id <= 0) {
    echo json_encode([]);
    exit;
}

$kitaplar = [];
$sql = "SELECT i.id AS islem_id, i.kitap_id, k.kitap_adi
        FROM islemler i
        JOIN kitaplar k ON i.kitap_id = k.id
        WHERE i.ogrenci_id = ? AND i.okul_id = ? AND i.iade_tarihi IS NULL
        ORDER BY k.kitap_adi ASC";

if ($stmt = $mysqli->prepare($sql)) {
    $stmt->bind_param("ii", $ogrenci_id, $okul_id);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($islem_id, $kitap_id, $kitap_adi);
    while ($stmt->fetch()) {
        $kitaplar[] = ['islem_id' => $islem_id, 'kitap_id' => $kitap_id, 'kitap_adi' => $kitap_adi];
    }
    $stmt->close();
}

$mysqli->close();
echo json_encode($kitaplar);
exit;
?>