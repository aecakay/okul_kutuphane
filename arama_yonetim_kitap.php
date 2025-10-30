<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once 'config.php';

$response = [
    'html_tablo' => '<tr><td colspan="6">Bir hata oluştu.</td></tr>',
    'html_sayfalama' => '',
    'toplam_kayit_baslik' => 0,
    'toplam_kayit_fiziksel' => 0
];

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["okul_id"])) {
    $response['html_tablo'] = '<tr><td colspan="6">Yetkisiz erişim.</td></tr>';
    echo json_encode($response);
    exit;
}

$okul_id = $_SESSION["okul_id"];
$arama_terimi = isset($_GET['ara']) ? trim($_GET['ara']) : '';
$sayfa = isset($_GET['sayfa']) ? (int)$_GET['sayfa'] : 1;
if ($sayfa < 1) $sayfa = 1;
$kayit_per_sayfa = 8; // İsteğiniz üzerine sayfa başına 8 kayıt
$offset = ($sayfa - 1) * $kayit_per_sayfa;

// SQL sorgularının temelini ve parametrelerini oluştur
$sql_base = "FROM kitaplar WHERE okul_id = ?";
$params = [$okul_id];
$types = "i";

if (!empty($arama_terimi)) {
    $arama_wildcard = "%{$arama_terimi}%";
    $sql_base .= " AND (kitap_adi LIKE ? OR yazar LIKE ? OR isbn LIKE ?)";
    array_push($params, $arama_wildcard, $arama_wildcard, $arama_wildcard);
    $types .= "sss";
}

// Toplam başlık sayısını al
$sql_count_baslik = "SELECT COUNT(id) " . $sql_base;
$stmt_count_baslik = $mysqli->prepare($sql_count_baslik);
$stmt_count_baslik->bind_param($types, ...$params);
$stmt_count_baslik->execute();
$stmt_count_baslik->bind_result($toplam_kayit_baslik);
$stmt_count_baslik->fetch();
$stmt_count_baslik->close();
$response['toplam_kayit_baslik'] = $toplam_kayit_baslik;
$toplam_sayfa = ceil($toplam_kayit_baslik / $kayit_per_sayfa);

// Toplam fiziksel kitap sayısını al
$sql_count_fiziksel = "SELECT SUM(toplam_adet) " . $sql_base;
$stmt_count_fiziksel = $mysqli->prepare($sql_count_fiziksel);
$stmt_count_fiziksel->bind_param($types, ...$params);
$stmt_count_fiziksel->execute();
$stmt_count_fiziksel->bind_result($toplam_kayit_fiziksel);
$stmt_count_fiziksel->fetch();
$stmt_count_fiziksel->close();
$response['toplam_kayit_fiziksel'] = $toplam_kayit_fiziksel ?? 0;

// Sayfalanmış kitap listesini al
$sql_select = "SELECT id, kitap_adi, yazar, isbn, toplam_adet, raftaki_adet " . $sql_base . " ORDER BY id DESC LIMIT ? OFFSET ?";
array_push($params, $kayit_per_sayfa, $offset);
$types .= "ii";

$stmt = $mysqli->prepare($sql_select);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($id, $kitap_adi, $yazar, $isbn, $toplam_adet, $raftaki_adet);

$html_tablo = '';
if ($stmt->num_rows > 0) {
    while ($stmt->fetch()) {
        $html_tablo .= '<tr>
            <td style="text-align: center;"><input type="checkbox" class="book-checkbox" data-id="' . $id . '"></td>
            <td>' . htmlspecialchars($kitap_adi) . '</td>
            <td>' . htmlspecialchars($yazar) . '</td>
            <td>' . htmlspecialchars($isbn) . '</td>
            <td>' . $raftaki_adet . ' / ' . $toplam_adet . '</td>
            <td class="td-islemler">
                <a href="kitap_duzenle.php?id=' . $id . '" class="button-link button-edit"><i class="fa-solid fa-pencil"></i> Düzenle</a>
                <button class="button-link button-danger delete-btn" data-id="' . $id . '" data-name="' . htmlspecialchars($kitap_adi) . '"><i class="fa-solid fa-trash-can"></i> Sil</button>
            </td>
        </tr>';
    }
} else {
    $html_tablo = '<tr><td colspan="6" style="text-align:center; padding: 20px;">Kayıt bulunamadı.</td></tr>';
}
$response['html_tablo'] = $html_tablo;
$stmt->close();

// Sayfalama HTML'ini oluştur
$html_sayfalama = '';
if ($toplam_sayfa > 1) {
    $html_sayfalama = '<ul class="pagination">';
    if ($sayfa > 1) {
        $html_sayfalama .= '<li><a href="#" data-page="' . ($sayfa - 1) . '">« Önceki</a></li>';
    }
    for ($i = 1; $i <= $toplam_sayfa; $i++) {
        $html_sayfalama .= '<li class="' . ($sayfa == $i ? 'active' : '') . '"><a href="#" data-page="' . $i . '">' . $i . '</a></li>';
    }
    if ($sayfa < $toplam_sayfa) {
        $html_sayfalama .= '<li><a href="#" data-page="' . ($sayfa + 1) . '">Sonraki »</a></li>';
    }
    $html_sayfalama .= '</ul>';
}
$response['html_sayfalama'] = $html_sayfalama;

echo json_encode($response);
$mysqli->close();
?>