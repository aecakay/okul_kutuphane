<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once 'config.php';

// Güvenlik kontrolü
if (!isset($_SESSION["student_loggedin"]) || $_SESSION["student_loggedin"] !== true || !isset($_SESSION["student_okul_id"])) {
    echo json_encode(['error' => 'Yetkisiz erişim.']);
    exit;
}

$okul_id = $_SESSION["student_okul_id"];
$arama_terimi = isset($_GET['ara']) ? trim($_GET['ara']) : '';
$sayfa = isset($_GET['sayfa']) ? (int)$_GET['sayfa'] : 1;
if ($sayfa < 1) $sayfa = 1;
$kayit_per_sayfa = 10;
$offset = ($sayfa - 1) * $kayit_per_sayfa;

// SQL ve parametreleri hazırla
$sql_base = "FROM kitaplar k WHERE k.okul_id = ?";
$params = [$okul_id];
$types = "i";

if (!empty($arama_terimi)) {
    // Kitap adı, yazar veya ISBN'e göre arama
    $sql_base .= " AND (k.kitap_adi LIKE ? OR k.yazar LIKE ? OR k.isbn LIKE ?)";
    $arama_wildcard = "%{$arama_terimi}%";
    array_push($params, $arama_wildcard, $arama_wildcard, $arama_wildcard);
    $types .= "sss";
}

// TOPLAM KAYIT SAYISINI ALMA
$sql_count = "SELECT COUNT(k.id) " . $sql_base;
$stmt_count = $mysqli->prepare($sql_count);
$stmt_count->bind_param($types, ...$params);
$stmt_count->execute();
$stmt_count->bind_result($toplam_kayit);
$stmt_count->fetch();
$stmt_count->close();
$toplam_sayfa = $toplam_kayit > 0 ? ceil($toplam_kayit / $kayit_per_sayfa) : 0;


// SAYFALANMIŞ VERİYİ ALMA
$sql_select = "SELECT k.id, k.kitap_adi, k.yazar, k.raftaki_adet " . $sql_base . " ORDER BY k.kitap_adi LIMIT ? OFFSET ?";
array_push($params, $kayit_per_sayfa, $offset);
$types .= 'ii';

$stmt = $mysqli->prepare($sql_select);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($id, $kitap_adi, $yazar, $raftaki_adet);


// HTML ÇIKTILARINI OLUŞTURMA (MODERN LİSTE YAPISI)
$html_tablo = '';
if ($stmt->num_rows > 0) {
    $html_tablo .= '<div class="katalog-listesi-ul">'; 
    while ($stmt->fetch()) {
        $durum_text = $raftaki_adet > 0 ? 'Rafta' : 'Ödünçte';
        $durum_class = $raftaki_adet > 0 ? 'durum-rafta' : 'durum-odunc';
        
        $html_tablo .= '<a href="kitap_detay.php?id=' . $id . '" class="katalog-list-item">';
        $html_tablo .= '<div class="kitap-bilgi">';
        // Kitap adı vurgulu, yazar hemen yanında
        $html_tablo .= '<span class="kitap-baslik">' . htmlspecialchars($kitap_adi) . '</span>';
        $html_tablo .= '<span class="kitap-yazar">' . htmlspecialchars($yazar) . '</span>';
        $html_tablo .= '</div>';
        $html_tablo .= '<div class="kitap-durum ' . $durum_class . '">' . $durum_text . '</div>';
        $html_tablo .= '</a>';
    }
    $html_tablo .= '</div>';
} else {
    $html_tablo = '<p style="text-align: center; padding: 20px;">Arama kriterlerinize uygun kitap bulunamadı.</p>';
}
$stmt->close();


// Sayfalama HTML'ini Oluştur
$html_sayfalama = '';
if ($toplam_sayfa > 1) {
    $html_sayfalama = '<ul class="pagination">';
    $html_sayfalama .= '<li class="' . ($sayfa <= 1 ? 'disabled' : '') . '"><a href="#" data-page="' . ($sayfa - 1) . '">Önceki</a></li>';
    for ($i = 1; $i <= $toplam_sayfa; $i++) {
        $html_sayfalama .= '<li class="' . ($sayfa == $i ? 'active' : '') . '"><a href="#" data-page="' . $i . '">' . $i . '</a></li>';
    }
    $html_sayfalama .= '<li class="' . ($sayfa >= $toplam_sayfa ? 'disabled' : '') . '"><a href="#" data-page="' . ($sayfa + 1) . '">Sonraki</a></li>';
    $html_sayfalama .= '</ul>';
}

// JSON olarak response gönder
echo json_encode([
    'html_tablo' => $html_tablo,
    'html_sayfalama' => $html_sayfalama
], JSON_UNESCAPED_UNICODE);

$mysqli->close();
?>