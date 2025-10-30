<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once 'config.php';

$response = [
    'html_tablo' => '<tr><td colspan="5">Bir hata oluştu.</td></tr>',
    'html_sayfalama' => '',
    'toplam_kayit' => 0
];

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["okul_id"])) {
    $response['html_tablo'] = '<tr><td colspan="5">Yetkisiz erişim.</td></tr>';
    echo json_encode($response);
    exit;
}

$okul_id = $_SESSION["okul_id"];
$arama_terimi = isset($_GET['ara']) ? trim($_GET['ara']) : '';
$sayfa = isset($_GET['sayfa']) ? (int)$_GET['sayfa'] : 1;
if ($sayfa < 1) $sayfa = 1;

// İsteğiniz üzerine sayfa başına kayıt sayısı 8 olarak ayarlandı.
$kayit_per_sayfa = 8;
$offset = ($sayfa - 1) * $kayit_per_sayfa;

// SQL sorgularının temelini ve parametrelerini oluştur
$sql_base = "FROM ogrenciler WHERE okul_id = ?";
$params = [$okul_id];
$types = "i";

if (!empty($arama_terimi)) {
    $arama_wildcard = "%{$arama_terimi}%";
    $sql_base .= " AND (ogrenci_no LIKE ? OR ad LIKE ? OR soyad LIKE ? OR sinif LIKE ?)";
    array_push($params, $arama_wildcard, $arama_wildcard, $arama_wildcard, $arama_wildcard);
    $types .= "ssss";
}

// Toplam kayıt sayısını al
$sql_count = "SELECT COUNT(id) " . $sql_base;
$stmt_count = $mysqli->prepare($sql_count);
$stmt_count->bind_param($types, ...$params);
$stmt_count->execute();
$stmt_count->bind_result($toplam_kayit);
$stmt_count->fetch();
$stmt_count->close();
$response['toplam_kayit'] = $toplam_kayit;
$toplam_sayfa = ceil($toplam_kayit / $kayit_per_sayfa);

// Sayfalanmış öğrenci listesini al (SUNUCUNUZLA UYUMLU YÖNTEM)
$sql_select = "SELECT id, ogrenci_no, sinif, ad, soyad " . $sql_base . " ORDER BY ogrenci_no + 0 ASC LIMIT ? OFFSET ?";
array_push($params, $kayit_per_sayfa, $offset);
$types .= "ii";

$stmt = $mysqli->prepare($sql_select);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($id, $ogrenci_no, $sinif, $ad, $soyad);

$html_tablo = '';
if ($stmt->num_rows > 0) {
    while ($stmt->fetch()) {
        $html_tablo .= '<tr>
            <td style="text-align: center;"><input type="checkbox" class="student-checkbox" data-id="' . $id . '"></td>
            <td>' . htmlspecialchars($ogrenci_no) . '</td>
            <td>' . htmlspecialchars($sinif) . '</td>
            <td>' . htmlspecialchars($ad . ' ' . $soyad) . '</td>
            <td class="td-islemler">
                <a href="ogrenci_duzenle.php?id=' . $id . '" class="button-link button-edit"><i class="fa-solid fa-pencil"></i> Düzenle</a>
                <button class="button-link button-danger delete-btn" data-id="' . $id . '" data-name="' . htmlspecialchars($ad . ' ' . $soyad) . '"><i class="fa-solid fa-trash-can"></i> Sil</button>
            </td>
        </tr>';
    }
} else {
    $html_tablo = '<tr><td colspan="5" style="text-align:center; padding: 20px;">Kayıt bulunamadı.</td></tr>';
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