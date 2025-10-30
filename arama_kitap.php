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

    // SQL SORGUSU GÜNCELLENDİ: Sadece o okula ait ve raftaki kitapları arar
    $sql = "SELECT id, kitap_adi, yazar, raftaki_adet
            FROM kitaplar
            WHERE 
                okul_id = ? 
                AND (kitap_adi LIKE ? OR yazar LIKE ? OR isbn LIKE ?) 
                AND raftaki_adet > 0
            ORDER BY kitap_adi
            LIMIT 10";

    if($stmt = $mysqli->prepare($sql)){
        // bind_param GÜNCELLENDİ: "isss" oldu (okul_id eklendi)
        $stmt->bind_param("isss", $okul_id, $search_term, $search_term, $search_term);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($id, $kitap_adi, $yazar, $raftaki_adet);

        while ($stmt->fetch()) {
            $results[] = [
                'id' => $id,
                'label' => $kitap_adi . ' (' . $yazar . ') - Stok: ' . $raftaki_adet,
                'value' => $kitap_adi // Seçildiğinde input'a sadece Kitap Adı yazılacak
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