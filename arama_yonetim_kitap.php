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

// Gerekli parametreleri al
$arama_terimi = isset($_GET['ara']) ? trim($_GET['ara']) : '';
$sql_arama_terimi = '%' . $arama_terimi . '%';
$sayfa = isset($_GET['sayfa']) ? (int)$_GET['sayfa'] : 1;
if ($sayfa < 1) $sayfa = 1;
$kayit_per_sayfa = 20;
$offset = ($sayfa - 1) * $kayit_per_sayfa;

// SQL WHERE koşulunu ve parametreleri hazırla
// YENİ: Temel koşul olarak okul_id eklendi
$where_sql = " WHERE okul_id = ?";
$params_sql = [$okul_id];
$types_sql = "i";

if(!empty($arama_terimi)){
    $where_sql .= " AND (kitap_adi LIKE ? OR yazar LIKE ? OR isbn LIKE ?)";
    $params_sql = array_merge($params_sql, [$sql_arama_terimi, $sql_arama_terimi, $sql_arama_terimi]);
    $types_sql .= "sss";
}

// 1. Toplam KAYIT (BAŞLIK) Sayısını Al (okul_id'ye göre)
$sql_count_baslik = "SELECT COUNT(id) FROM kitaplar" . $where_sql;
$stmt_count_baslik = $mysqli->prepare($sql_count_baslik);
$stmt_count_baslik->bind_param($types_sql, ...$params_sql);
$stmt_count_baslik->execute();
$stmt_count_baslik->store_result();
$stmt_count_baslik->bind_result($toplam_kayit_baslik);
$stmt_count_baslik->fetch();
$toplam_sayfa = ceil($toplam_kayit_baslik / $kayit_per_sayfa);
$stmt_count_baslik->close();

// 2. Toplam FİZİKSEL KİTAP Sayısını Al (okul_id'ye göre)
$sql_count_fiziksel = "SELECT SUM(toplam_adet) FROM kitaplar" . $where_sql;
$stmt_count_fiziksel = $mysqli->prepare($sql_count_fiziksel);
$stmt_count_fiziksel->bind_param($types_sql, ...$params_sql);
$stmt_count_fiziksel->execute();
$stmt_count_fiziksel->store_result();
$stmt_count_fiziksel->bind_result($toplam_kayit_fiziksel);
$stmt_count_fiziksel->fetch();
if ($toplam_kayit_fiziksel === null) $toplam_kayit_fiziksel = 0;
$stmt_count_fiziksel->close();

// 3. Tablo İçeriğini (tbody) Oluştur (okul_id'ye göre)
$html_tablo = ""; 
$sql_list = "SELECT id, kitap_adi, yazar, isbn, basim_yili, toplam_adet, raftaki_adet FROM kitaplar" . $where_sql;
$sql_list .= " ORDER BY kitap_adi LIMIT ? OFFSET ?";

$params_list = $params_sql;
$params_list[] = $kayit_per_sayfa; $types_sql .= "i";
$params_list[] = $offset;          $types_sql .= "i";

$stmt_list = $mysqli->prepare($sql_list);
$stmt_list->bind_param($types_sql, ...$params_list);
$stmt_list->execute();
$stmt_list->store_result();
$stmt_list->bind_result($col_id, $col_kitap_adi, $col_yazar, $col_isbn, $col_basim_yili, $col_toplam_adet, $col_raftaki_adet);

if($stmt_list->num_rows > 0){
    while($stmt_list->fetch()){
        $html_tablo .= "<tr data-id='{$col_id}'>";
        $html_tablo .= '<td style="text-align: center;"><input type="checkbox" class="book-checkbox" data-id="' . $col_id . '"></td>';
        $html_tablo .= "<td>" . htmlspecialchars($col_kitap_adi) . "</td>";
        $html_tablo .= "<td>" . htmlspecialchars($col_yazar) . "</td>";
        $html_tablo .= "<td>" . htmlspecialchars($col_isbn) . "</td>";
        $html_tablo .= "<td>" . $col_raftaki_adet . " / " . $col_toplam_adet . "</td>";
        $html_tablo .= '<td style="text-align:center;">';
        $html_tablo .= '<a href="kitap_duzenle.php?id=' . $col_id . '" class="button-link small">Düzenle</a> ';
        $html_tablo .= '<button class="button-link small button-danger delete-btn" data-id="' . $col_id . '" data-name="' . htmlspecialchars($col_kitap_adi) . '">Sil</button>';
        $html_tablo .= '</td>';
        $html_tablo .= "</tr>";
    }
} else {
    $html_tablo = '<tr><td colspan="6">Kayıt bulunamadı.</td></tr>';
}
$stmt_list->close();
$mysqli->close();

// 4. Sayfalama Linklerini (nav) Oluştur
$html_sayfalama = "";
if ($toplam_sayfa > 1) { 
    $html_sayfalama = '<ul class="pagination">';
    $disabled = ($sayfa <= 1) ? 'disabled' : '';
    $html_sayfalama .= "<li class=\"$disabled\"><a href=\"#\" data-page=\"" . ($sayfa - 1) . "\">Önceki</a></li>";
    for($i = 1; $i <= $toplam_sayfa; $i++){
        $active = ($sayfa == $i) ? 'active' : '';
        $html_sayfalama .= "<li class=\"$active\"><a href=\"#\" data-page=\"$i\">$i</a></li>";
    }
    $disabled = ($sayfa >= $toplam_sayfa) ? 'disabled' : '';
    $html_sayfalama .= "<li class=\"$disabled\"><a href=\"#\" data-page=\"" . ($sayfa + 1) . "\">Sonraki</a></li>";
    $html_sayfalama .= '</ul>';
}

// 5. Sonuçları JSON olarak döndür
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'toplam_kayit_baslik' => $toplam_kayit_baslik,
    'toplam_kayit_fiziksel' => $toplam_kayit_fiziksel,
    'html_tablo' => $html_tablo,
    'html_sayfalama' => $html_sayfalama
]);
exit;
?>