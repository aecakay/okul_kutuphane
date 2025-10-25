<?php
// Bu dosya AJAX isteklerine cevap verir
require_once "config.php";
session_start();

// Güvenlik: Giriş yapılmamışsa veya okul ID'si yoksa işlemi durdur
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["okul_id"])){
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Yetkisiz erişim.']);
    exit;
}

// Oturumdan mevcut okulun ID'sini al
$okul_id = $_SESSION["okul_id"];
$results = [];
$query = isset($_GET['term']) ? trim($_GET['term']) : '';

if (!empty($query)) {
    $search_term = '%' . $query . '%';

    // SQL SORGUSU GÜNCELLENDİ: Sadece o okula ait öğrencileri arar
    $sql = "SELECT id, ogrenci_no, ad, soyad, sinif
            FROM ogrenciler
            WHERE okul_id = ? AND (ogrenci_no LIKE ? OR ad LIKE ? OR soyad LIKE ?)
            ORDER BY ogrenci_no + 0 ASC
            LIMIT 10";

    if($stmt = $mysqli->prepare($sql)){
        // bind_param GÜNCELLENDİ: "isss" oldu (okul_id eklendi)
        $stmt->bind_param("isss", $okul_id, $search_term, $search_term, $search_term);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($id, $ogrenci_no, $ad, $soyad, $sinif);

        while ($stmt->fetch()) {
            $results[] = [
                'id' => $id,
                'label' => $ogrenci_no . ' - ' . $ad . ' ' . $soyad . ($sinif ? ' (' . $sinif . ')' : ''),
                'value' => $ad . ' ' . $soyad // Seçildiğinde input'a sadece Ad Soyad yazılacak
            ];
        }
        $stmt->close();
    }
}

$mysqli->close();
header('Content-Type: application/json; charset=utf-8');
echo json_encode($results);
exit;
?>