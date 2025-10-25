<?php
// Bu dosya AJAX isteklerine cevap verir, HTML içermez.
require_once "config.php";
// Oturum ve giriş kontrolü
session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["okul_id"])){
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Yetkisiz erişim.']);
    exit;
}

// Oturumdan mevcut okulun ID'sini al
$okul_id = $_SESSION["okul_id"];

$results = [];
$query = isset($_GET['term']) ? trim($_GET['term']) : '';

// Arama terimi en az 1 karakter olmalı
if (!empty($query)) {
    $search_term = '%' . $query . '%';

    // SORGUSU GÜNCELLENDİ: Artık sadece o okula ait tüm kitapları arar.
    // "raftaki_adet > 0" koşulu bu dosyada yoktur.
    $sql = "SELECT id, kitap_adi, yazar
            FROM kitaplar
            WHERE okul_id = ? AND (kitap_adi LIKE ? OR yazar LIKE ? OR isbn LIKE ?)
            ORDER BY kitap_adi
            LIMIT 10";

    if($stmt = $mysqli->prepare($sql)){
        // bind_param GÜNCELLENDİ: "isss" oldu (okul_id eklendi)
        $stmt->bind_param("isss", $okul_id, $search_term, $search_term, $search_term);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($id, $kitap_adi, $yazar);

        while ($stmt->fetch()) {
            // Sonuçları JSON için uygun bir diziye ekle
            $results[] = [
                'id' => $id, // Kitap ID'si
                'label' => $kitap_adi . ' - ' . $yazar, // Listede görünecek metin
                'value' => $kitap_adi // Seçildiğinde input'a yazılacak metin
            ];
        }
        $stmt->close();
    }
}

$mysqli->close();

// Sonuçları JSON formatında ekrana bas
header('Content-Type: application/json; charset=utf-8');
echo json_encode($results);
exit;
?>