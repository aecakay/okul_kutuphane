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

    // SORGUSU GÜNCELLENDİ: Sadece o okula ait ve iade edilmemiş işlemleri ara
    $sql = "SELECT
                i.id AS islem_id,
                i.kitap_id,
                k.kitap_adi,
                o.ad,
                o.soyad,
                o.ogrenci_no
            FROM islemler i
            JOIN kitaplar k ON i.kitap_id = k.id
            JOIN ogrenciler o ON i.ogrenci_id = o.id
            WHERE 
                i.okul_id = ? 
                AND i.iade_tarihi IS NULL
                AND (o.ogrenci_no LIKE ? OR o.ad LIKE ? OR o.soyad LIKE ?)
            ORDER BY o.ogrenci_no ASC, k.kitap_adi ASC";

    if($stmt = $mysqli->prepare($sql)){
        $stmt->bind_param("isss", $okul_id, $search_term, $search_term, $search_term);
        $stmt->execute();
        $stmt->store_result(); // HATA ÇÖZÜMÜ: Sonucu belleğe al

        // HATA ÇÖZÜMÜ: Sonuçları tek tek bind et ve çek
        $stmt->bind_result($islem_id, $kitap_id, $kitap_adi, $ad, $soyad, $ogrenci_no);

        while ($stmt->fetch()) {
             $label_text = $ogrenci_no . ' - ' . $ad . ' ' . $soyad . ' | Kitap: ' . $kitap_adi;
             $results[] = [
                'islem_id' => $islem_id,
                'kitap_id' => $kitap_id,
                'label' => $label_text,
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