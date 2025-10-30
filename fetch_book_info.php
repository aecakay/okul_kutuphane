<?php
header('Content-Type: application/json; charset=utf-8');

// Sizin sağladığınız YENİ API anahtarı koda eklendi.
$apiKey = "AIzaSyCkOIYLekKOQ9o5dyjMCYHESqsuD9GIbto";

$response = ['error' => 'Bilinmeyen bir hata oluştu.'];
$isbn = isset($_GET['isbn']) ? trim($_GET['isbn']) : '';

if (empty($isbn)) {
    $response['error'] = 'ISBN numarası gönderilmedi.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// URL'nin sonuna "&key=" parametresi ile API anahtarı eklendi.
$apiUrl = "https://www.googleapis.com/books/v1/volumes?q=isbn:" . urlencode($isbn) . "&key=" . $apiKey;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
$api_response_json = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    $response['error'] = 'cURL Hatası: ' . curl_error($ch);
    curl_close($ch);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}
curl_close($ch);

if ($http_code !== 200) {
    $response['error'] = 'Google API\'den veri alınamadı. HTTP Kodu: ' . $http_code . '. API Anahtarınızı ve API\'nin etkin olup olmadığını kontrol edin.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

$api_response_data = json_decode($api_response_json, true);

if (isset($api_response_data['error'])) {
    $response['error'] = 'Google API Hatası: ' . $api_response_data['error']['message'];
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode($api_response_data, JSON_UNESCAPED_UNICODE);
exit;
?>