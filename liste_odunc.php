<?php
require_once 'config.php';
session_start();

// Güvenlik: Giriş yapılmamışsa veya okul ID'si yoksa işlemi durdur
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["okul_id"])) {
    $response = ['table_html' => '<thead><tr><th>Öğrenci No</th><th>Ad Soyad</th><th>Kitap Adı</th><th>Ödünç Tarihi</th><th>İade Tarihi</th><th>İşlem</th></tr></thead><tbody><tr><td colspan="6">Yetkiniz yok.</td></tr></tbody>', 'pagination_html' => ''];
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response);
    exit;
}

// Oturumdan mevcut okulun ID'sini al
$okul_id = $_SESSION["okul_id"];

// Sayfalama Ayarları
$sayfa_odunc = isset($_GET['sayfa_odunc']) ? (int)$_GET['sayfa_odunc'] : 1;
if ($sayfa_odunc < 1) $sayfa_odunc = 1;
$kayit_per_sayfa_odunc = 8; // Daha önceki istek üzerine 8 olarak belirlendi.
$offset_odunc = ($sayfa_odunc - 1) * $kayit_per_sayfa_odunc;

// Toplam Kayıt Sayısı (okul_id'ye göre)
$sql_count_odunc = "SELECT COUNT(id) FROM islemler WHERE okul_id = ? AND iade_tarihi IS NULL";
$stmt_count_odunc = $mysqli->prepare($sql_count_odunc);
$stmt_count_odunc->bind_param("i", $okul_id);
$stmt_count_odunc->execute();
$stmt_count_odunc->store_result();
$stmt_count_odunc->bind_result($toplam_kayit_odunc);
$stmt_count_odunc->fetch();
$toplam_sayfa_odunc = ceil($toplam_kayit_odunc / $kayit_per_sayfa_odunc);
$stmt_count_odunc->close();

// Liste sorgusu (okul_id'ye göre)
$sql_list = "SELECT islemler.id AS islem_id, islemler.kitap_id, kitaplar.kitap_adi, ogrenciler.ogrenci_no, ogrenciler.ad, ogrenciler.soyad, islemler.odunc_tarihi, islemler.son_iade_tarihi 
             FROM islemler JOIN kitaplar ON islemler.kitap_id = kitaplar.id JOIN ogrenciler ON islemler.ogrenci_id = ogrenciler.id 
             WHERE islemler.okul_id = ? AND islemler.iade_tarihi IS NULL 
             ORDER BY ogrenciler.ogrenci_no + 0 ASC  /* BURADA DÜZENLEME YAPILDI */
             LIMIT ? OFFSET ?";
    
$stmt_list = $mysqli->prepare($sql_list);
$stmt_list->bind_param("iii", $okul_id, $kayit_per_sayfa_odunc, $offset_odunc);
$stmt_list->execute();
$stmt_list->store_result();
$stmt_list->bind_result($islem_id, $kitap_id, $kitap_adi, $ogrenci_no, $ad, $soyad, $odunc_tarihi, $son_iade_tarihi);

// HTML Çıktısını Oluştur
$bugun_str = date('Y-m-d');
$html_output = '<thead><tr><th>Öğrenci No</th><th>Öğrenci Ad Soyad</th><th>Kitap Adı</th><th>Ödünç Tarihi</th><th>Son İade Tarihi</th><th>İşlem</th></tr></thead><tbody>';

if ($stmt_list->num_rows > 0) {
    while ($stmt_list->fetch()) {
        $durum_class = (!empty($son_iade_tarihi) && strtotime($son_iade_tarihi) < strtotime($bugun_str)) ? 'durum-gecikti' : 'durum-odunc';
        $html_output .= "<tr data-islem-id='{$islem_id}'>";
        $html_output .= "<td>" . htmlspecialchars($ogrenci_no) . "</td>";
        $html_output .= "<td>" . htmlspecialchars($ad . ' ' . $soyad) . "</td>";
        $html_output .= "<td>" . htmlspecialchars($kitap_adi) . "</td>";
        $html_output .= "<td>" . date("d.m.Y", strtotime($odunc_tarihi)) . "</td>";
        $html_output .= "<td><span class='durum " . $durum_class . "'>" . (!empty($son_iade_tarihi) ? date("d.m.Y", strtotime($son_iade_tarihi)) : '-') . "</span></td>";
        $html_output .= "<td><button class='button-link small button-warning list-iade-btn' data-islem-id='{$islem_id}' data-kitap-id='{$kitap_id}'>İade Al</button></td>";
        $html_output .= "</tr>";
    }
} else {
    $html_output .= '<tr><td colspan="6">Şu anda ödünçte kitap bulunmamaktadır.</td></tr>';
}
$html_output .= '</tbody>';
$stmt_list->close();

// Sayfalama Linklerini Oluştur
$pagination_html = '';
if ($toplam_sayfa_odunc > 1) {
    $pagination_html .= '<nav><ul class="pagination">';
    $disabled_prev = ($sayfa_odunc <= 1) ? 'disabled' : '';
    $pagination_html .= "<li class='{$disabled_prev}'><a href='#' data-page='" . ($sayfa_odunc - 1) . "'>Önceki</a></li>";
    // Sayfa numaraları yerine "Sayfa X / Y" formatı
    $pagination_html .= "<li class='page-info'><span>Sayfa {$sayfa_odunc} / {$toplam_sayfa_odunc}</span></li>";
    $disabled_next = ($sayfa_odunc >= $toplam_sayfa_odunc) ? 'disabled' : '';
    $pagination_html .= "<li class='{$disabled_next}'><a href='#' data-page='" . ($sayfa_odunc + 1) . "'>Sonraki</a></li>";
    $pagination_html .= '</ul></nav>';
}

$mysqli->close();

// Sonuçları JSON olarak döndür
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'table_html' => $html_output,
    'pagination_html' => $pagination_html
]);
exit;
?>