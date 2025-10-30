<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';
session_start();

// Yetki kontrolü
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["okul_id"])) {
    http_response_code(403);
    echo json_encode(['error' => 'Yetkisiz erişim.']);
    exit;
}

$okul_id = $_SESSION["okul_id"];
$raporlar = [];

// 1. Son 12 Aydaki İşlem Sayısı Raporu (okul_id'ye göre)
$aylik_islemler = [];
$aylar = [];
for ($i = 11; $i >= 0; $i--) {
    $tarih = date('Y-m', strtotime("-$i months"));
    $aylar[] = date('M Y', strtotime($tarih));
    $aylik_islemler[$tarih] = 0;
}
$sql_aylik = "SELECT DATE_FORMAT(odunc_tarihi, '%Y-%m') as ay, COUNT(id) as sayi 
              FROM islemler 
              WHERE okul_id = ? AND odunc_tarihi >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
              GROUP BY ay 
              ORDER BY ay ASC";
if ($stmt = $mysqli->prepare($sql_aylik)) {
    $stmt->bind_param("i", $okul_id);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($ay, $sayi);
    while($stmt->fetch()){
        if (isset($aylik_islemler[$ay])) {
            $aylik_islemler[$ay] = (int)$sayi;
        }
    }
    $stmt->close();
}
$raporlar['aylikIslemler'] = ['labels' => $aylar, 'data' => array_values($aylik_islemler)];

// 2. En Çok Okunan 10 Kitap Raporu (okul_id'ye göre)
$en_cok_okunan_kitaplar = ['labels' => [], 'data' => []];
$sql_kitaplar = "SELECT K.kitap_adi, COUNT(I.id) AS okuma_sayisi
                 FROM islemler I JOIN kitaplar K ON I.kitap_id = K.id
                 WHERE I.okul_id = ?
                 GROUP BY I.kitap_id
                 ORDER BY okuma_sayisi DESC LIMIT 10";
if ($stmt = $mysqli->prepare($sql_kitaplar)) {
    $stmt->bind_param("i", $okul_id);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($kitap_adi, $okuma_sayisi);
    while($stmt->fetch()){
        $en_cok_okunan_kitaplar['labels'][] = $kitap_adi;
        $en_cok_okunan_kitaplar['data'][] = (int)$okuma_sayisi;
    }
    $stmt->close();
}
$raporlar['enCokOkunanKitaplar'] = $en_cok_okunan_kitaplar;

// 3. En Çok Kitap Okuyan 10 Öğrenci Raporu (okul_id'ye göre)
$en_cok_okuyan_ogrenciler = ['labels' => [], 'data' => []];
$sql_ogrenciler = "SELECT O.ad, O.soyad, COUNT(I.id) AS kitap_sayisi
                   FROM islemler I JOIN ogrenciler O ON I.ogrenci_id = O.id
                   WHERE I.okul_id = ?
                   GROUP BY I.ogrenci_id
                   ORDER BY kitap_sayisi DESC LIMIT 10";
if ($stmt = $mysqli->prepare($sql_ogrenciler)) {
    $stmt->bind_param("i", $okul_id);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($ad, $soyad, $kitap_sayisi);
    while($stmt->fetch()){
        $en_cok_okuyan_ogrenciler['labels'][] = $ad . ' ' . $soyad;
        $en_cok_okuyan_ogrenciler['data'][] = (int)$kitap_sayisi;
    }
    $stmt->close();
}
$raporlar['enCokOkuyanOgrenciler'] = $en_cok_okuyan_ogrenciler;

// 4. En Popüler 10 Yazar Raporu (okul_id'ye göre)
$en_populer_yazarlar = ['labels' => [], 'data' => []];
$sql_yazarlar = "SELECT K.yazar, COUNT(I.id) AS okuma_sayisi
                 FROM islemler I JOIN kitaplar K ON I.kitap_id = K.id
                 WHERE I.okul_id = ? AND K.yazar IS NOT NULL AND K.yazar != ''
                 GROUP BY K.yazar
                 ORDER BY okuma_sayisi DESC LIMIT 10";
if ($stmt = $mysqli->prepare($sql_yazarlar)) {
    $stmt->bind_param("i", $okul_id);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($yazar, $okuma_sayisi);
    while($stmt->fetch()){
        $en_populer_yazarlar['labels'][] = $yazar;
        $en_populer_yazarlar['data'][] = (int)$okuma_sayisi;
    }
    $stmt->close();
}
$raporlar['enPopulerYazarlar'] = $en_populer_yazarlar;

$mysqli->close();
echo json_encode($raporlar);
exit;
?>