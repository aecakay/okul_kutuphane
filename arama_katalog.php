<?php
// Bu dosya AJAX isteklerine cevap verir, HTML içermez.
require_once "config.php";

// Gerekli parametreleri al
$arama_terimi = isset($_GET['ara']) ? trim($_GET['ara']) : '';
$sql_arama_terimi = '%' . $arama_terimi . '%';
$sayfa = isset($_GET['sayfa']) ? (int)$_GET['sayfa'] : 1;
if ($sayfa < 1) $sayfa = 1;
$kayit_per_sayfa = 20;
$offset = ($sayfa - 1) * $kayit_per_sayfa;

// SQL WHERE koşulunu hazırla (okul_id filtresi YOK, çünkü bu halka açık bir arama)
$where_sql_init = "";
$params_init = [];
$types_init = "";
if(!empty($arama_terimi)){
    $where_sql_init = " WHERE (k.kitap_adi LIKE ? OR k.yazar LIKE ? OR k.isbn LIKE ?)";
    $params_init = [$sql_arama_terimi, $sql_arama_terimi, $sql_arama_terimi];
    $types_init = "sss";
}

// YENİ: Kitapları okullarla birleştirmek için JOIN sorgusu
$join_sql = " FROM kitaplar k JOIN okullar o ON k.okul_id = o.id ";

// Toplam Fiziksel Kitap Sayısı
$sql_count_fiziksel = "SELECT SUM(k.toplam_adet)" . $join_sql . $where_sql_init;
$stmt_count_fiziksel = $mysqli->prepare($sql_count_fiziksel);
if(!empty($params_init)){ $stmt_count_fiziksel->bind_param($types_init, ...$params_init); }
$stmt_count_fiziksel->execute();
$stmt_count_fiziksel->store_result();
$stmt_count_fiziksel->bind_result($toplam_kayit_fiziksel);
$stmt_count_fiziksel->fetch();
if ($toplam_kayit_fiziksel === null) $toplam_kayit_fiziksel = 0;
$stmt_count_fiziksel->close();

// Toplam Başlık Sayısı (Sayfalama için)
$sql_count_baslik = "SELECT COUNT(k.id)" . $join_sql . $where_sql_init;
$stmt_count_baslik = $mysqli->prepare($sql_count_baslik);
if(!empty($params_init)){ $stmt_count_baslik->bind_param($types_init, ...$params_init); }
$stmt_count_baslik->execute();
$stmt_count_baslik->store_result();
$stmt_count_baslik->bind_result($toplam_kayit_baslik);
$stmt_count_baslik->fetch();
$toplam_sayfa = ceil($toplam_kayit_baslik / $kayit_per_sayfa);
$stmt_count_baslik->close();

// Tablo HTML'ini oluştur
$html_tablo = "";
// YENİ: Sorgu, okul adını da alacak şekilde güncellendi
$sql_list = "SELECT k.id, k.kitap_adi, k.yazar, k.raftaki_adet, o.okul_adi " . $join_sql . $where_sql_init;
$sql_list .= " ORDER BY k.kitap_adi LIMIT ? OFFSET ?";
$params_list = $params_init;
$params_list[] = $kayit_per_sayfa; $types_init .= "i";
$params_list[] = $offset;          $types_init .= "i";

$stmt_list = $mysqli->prepare($sql_list);
if($stmt_list) {
    $stmt_list->bind_param($types_init, ...$params_list);
    $stmt_list->execute();
    $stmt_list->store_result();
    $stmt_list->bind_result($col_id, $col_kitap_adi, $col_yazar, $col_raftaki_adet, $col_okul_adi);

    if($stmt_list->num_rows > 0){
        while($stmt_list->fetch()){
            $html_tablo .= "<tr>";
            // YENİ: Kitap adının altına okul adı eklendi
            $html_tablo .= '<td><a href="kitap_detay.php?id=' . $col_id . '">' . htmlspecialchars($col_kitap_adi) . '</a><br><small style="color:var(--text-secondary); font-size: 0.8em;">' . htmlspecialchars($col_okul_adi) . '</small></td>';
            $html_tablo .= "<td>" . htmlspecialchars($col_yazar) . "</td>";
            if ($col_raftaki_adet > 0) {
                 $html_tablo .= '<td><span class="durum durum-rafta">Rafta</span></td>';
            } else {
                 $html_tablo .= '<td><span class="durum durum-odunc">Ödünçte</span></td>';
            }
            $html_tablo .= "</tr>";
        }
    } else {
        $html_tablo = '<tr><td colspan="3">Arama kriterlerine uygun kitap bulunamadı.</td></tr>';
    }
    $stmt_list->close();
}

// Sayfalama HTML'ini oluştur
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

$mysqli->close();

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'toplam_kayit_fiziksel' => $toplam_kayit_fiziksel,
    'html_tablo' => $html_tablo,
    'html_sayfalama' => $html_sayfalama
]);
exit;
?>