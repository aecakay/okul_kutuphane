<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';
session_start();

$response = [
    'success' => false, 'message' => 'Bilinmeyen bir hata oluştu.',
    'toplam_kayit' => 0, 'html_tablo' => '<tr><td colspan="6">Başlangıçta veri yok.</td></tr>', 'html_sayfalama' => ''
];

// 1. Yetki Kontrolü
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["okul_id"])) {
    $response['message'] = 'Yetkisiz erişim.';
    http_response_code(403);
    echo json_encode($response);
    exit;
}

$okul_id = $_SESSION["okul_id"];

// 2. Filtre ve Sayfalama Değerlerini Al
$filt_ogrenci_id = isset($_GET['ogrenci_id']) ? (int)$_GET['ogrenci_id'] : 0;
$filt_kitap_id = isset($_GET['kitap_id']) ? (int)$_GET['kitap_id'] : 0;
$filt_bas_tarih = isset($_GET['bas_tarih']) ? $_GET['bas_tarih'] : '';
$filt_bit_tarih = isset($_GET['bit_tarih']) ? $_GET['bit_tarih'] : '';
$sayfa = isset($_GET['sayfa']) ? (int)$_GET['sayfa'] : 1;
if ($sayfa < 1) $sayfa = 1;
$kayit_per_sayfa = 20;
$offset = ($sayfa - 1) * $kayit_per_sayfa;

// 3. Dinamik SQL WHERE Koşulu Oluşturma (okul_id ile birlikte)
$where_clauses = ["islemler.okul_id = ?"];
$params = [$okul_id];
$types = "i";
$join_sql = " FROM islemler JOIN kitaplar ON islemler.kitap_id = kitaplar.id JOIN ogrenciler ON islemler.ogrenci_id = ogrenciler.id";

if ($filt_ogrenci_id > 0) { $where_clauses[] = "islemler.ogrenci_id = ?"; $params[] = $filt_ogrenci_id; $types .= "i"; }
if ($filt_kitap_id > 0) { $where_clauses[] = "islemler.kitap_id = ?"; $params[] = $filt_kitap_id; $types .= "i"; }
if (!empty($filt_bas_tarih)) { $where_clauses[] = "islemler.odunc_tarihi >= ?"; $params[] = $filt_bas_tarih; $types .= "s"; }
if (!empty($filt_bit_tarih)) { $where_clauses[] = "islemler.odunc_tarihi <= ?"; $params[] = $filt_bit_tarih; $types .= "s"; }
$where_sql = " WHERE " . implode(" AND ", $where_clauses);

// 4. Toplam Kayıt Sayısını Hesapla
$sql_count = "SELECT COUNT(islemler.id)" . $join_sql . $where_sql;
$stmt_count = $mysqli->prepare($sql_count);
$stmt_count->bind_param($types, ...$params);
$stmt_count->execute();
$stmt_count->store_result();
$stmt_count->bind_result($toplam_kayit);
$stmt_count->fetch();
$toplam_sayfa = ceil($toplam_kayit / $kayit_per_sayfa);
$stmt_count->close();
$response['toplam_kayit'] = $toplam_kayit;

// 5. Kayıtları Çek ve HTML Oluştur
$html_tablo = '';
$sql_list = "SELECT kitaplar.kitap_adi, ogrenciler.ad, ogrenciler.soyad, ogrenciler.ogrenci_no, islemler.odunc_tarihi, islemler.son_iade_tarihi, islemler.iade_tarihi " . $join_sql . $where_sql;
$sql_list .= " ORDER BY islemler.id DESC LIMIT ? OFFSET ?";
$params[] = $kayit_per_sayfa; $types .= "i";
$params[] = $offset; $types .= "i";

$stmt_list = $mysqli->prepare($sql_list);
$stmt_list->bind_param($types, ...$params);
$stmt_list->execute();
$stmt_list->store_result();
$stmt_list->bind_result($col_kitap, $col_ad, $col_soyad, $col_ogrenci_no, $col_odunc, $col_son_iade, $col_iade_tarihi);

if ($stmt_list->num_rows > 0) {
    while ($stmt_list->fetch()) {
        $html_tablo .= "<tr>";
        $html_tablo .= "<td>" . htmlspecialchars($col_ogrenci_no) . "</td>";
        $html_tablo .= "<td>" . htmlspecialchars($col_ad . ' ' . $col_soyad) . "</td>";
        $html_tablo .= "<td>" . htmlspecialchars($col_kitap) . "</td>";
        $html_tablo .= "<td>" . date("d.m.Y", strtotime($col_odunc)) . "</td>";
        $html_tablo .= "<td>" . (!empty($col_son_iade) ? date("d.m.Y", strtotime($col_son_iade)) : '-') . "</td>";
        $durum_html = '';
        if ($col_iade_tarihi === NULL) {
            if (!empty($col_son_iade) && strtotime($col_son_iade) < strtotime(date('Y-m-d'))) {
                $durum_html = '<td><span class="durum durum-gecikti">GECİKTİ</span></td>';
            } else {
                $durum_html = '<td><span class="durum durum-odunc">ÖDÜNÇTE</span></td>';
            }
        } else {
            $durum_html = '<td><span class="durum durum-rafta">İade Edildi (' . date("d.m.Y", strtotime($col_iade_tarihi)) . ')</span></td>';
        }
        $html_tablo .= $durum_html;
        $html_tablo .= "</tr>";
    }
} else {
    $html_tablo = '<tr><td colspan="6">Filtre kriterlerine uygun kayıt bulunamadı.</td></tr>';
}
$stmt_list->close();
$response['html_tablo'] = $html_tablo;

// 6. Sayfalama HTML'ini Oluştur
$html_sayfalama = "";
if ($toplam_sayfa > 1) {
    $page_params = [
        'ogrenci_id' => $filt_ogrenci_id,
        'kitap_id' => $filt_kitap_id,
        'bas_tarih' => $filt_bas_tarih,
        'bit_tarih' => $filt_bit_tarih,
    ];
    $param_string = http_build_query(array_filter($page_params));

    $html_sayfalama .= '<nav><ul class="pagination">';
    $disabled_prev = ($sayfa <= 1) ? 'disabled' : '';
    $html_sayfalama .= "<li class='{$disabled_prev}'><a href='#' data-page='" . ($sayfa - 1) . "' data-params='{$param_string}'>Önceki</a></li>";
    for ($i = 1; $i <= $toplam_sayfa; $i++) {
        $active = ($sayfa == $i) ? 'active' : '';
        $html_sayfalama .= "<li class='{$active}'><a href='#' data-page='{$i}' data-params='{$param_string}'>{$i}</a></li>";
    }
    $disabled_next = ($sayfa >= $toplam_sayfa) ? 'disabled' : '';
    $html_sayfalama .= "<li class='{$disabled_next}'><a href='#' data-page='" . ($sayfa + 1) . "' data-params='{$param_string}'>Sonraki</a></li>";
    $html_sayfalama .= '</ul></nav>';
}
$response['html_sayfalama'] = $html_sayfalama;
$response['success'] = true;

$mysqli->close();
echo json_encode($response);
exit;
?>