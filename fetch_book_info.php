<?php
// Tarayıcıya UTF-8 JSON gönderdiğimizi söylüyoruz
header('Content-Type: application/json; charset=utf-8');

// Oturum kontrolü (AJAX güvenliği için)
session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header('HTTP/1.1 401 Unauthorized');
    echo '{"error": "Yetkisiz erişim."}'; // Hata mesajını ham JSON olarak yaz
    exit;
}

if (!isset($_GET['isbn']) || empty(trim($_GET['isbn']))) {
    echo '{"error": "ISBN numarası gönderilmedi."}';
    exit;
}

$isbn = trim($_GET['isbn']);
$isbn_clean = str_replace('-', '', $isbn);
$apiUrl = "https://www.googleapis.com/books/v1/volumes?q=isbn:" . urlencode($isbn_clean);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Okul Kutuphane App');

$response = curl_exec($ch); // Google'dan gelen ham JSON metni
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    $curl_error = curl_error($ch);
    curl_close($ch);
    echo '{"error": "API isteği başarısız oldu (cURL Hatası): ' . addslashes($curl_error) . '"}';
    exit;
}
curl_close($ch);

if ($httpCode != 200) {
     echo '{"error": "Google Books API\'den geçersiz yanıt alındı (HTTP Kodu: ' . $httpCode . ')."}';
     exit;
}

// Google'dan gelen ham yanıtı (JSON) doğrudan ekrana bas
echo $response;

exit;
?>