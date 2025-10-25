<?php
// Bu dosya, öğrenci panelindeki katalog için AJAX isteklerine cevap verir
require_once "config.php";
session_start();

// Güvenlik: Öğrenci girişi yapılmamışsa veya okul ID'si yoksa işlemi durdur
if (!isset($_SESSION["student_loggedin"]) || $_SESSION["student_loggedin"] !== true || !isset($_SESSION["student_okul_id"])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Yetkisiz erişim.']);
    exit;
}

// Oturumdan öğrencinin okul ID'sini al
$okul_id = $_SESSION["student_okul_id"];

// Gerekli parametreleri al
$arama_terimi = isset($_GET['ara']) ? trim($_GET['ara']) : '';
$sql_arama_terimi = '%' . $arama_terimi . '%';
$sayfa = isset($_GET['sayfa']) ? (int)$_GET['sayfa'] : 1;
if ($sayfa < 1) $sayfa = 1;
$kayit_per_sayfa = 10;
$offset = ($sayfa - 1) * $kayit_per_sayfa;

// SQL WHERE koşulunu hazırla (SADECE ÖĞRENCİNİN OKULU)
$where_sql = " WHERE okul_id = ?";
$params = [$okul_id];
$types = "i";

if (!empty($arama_terimi)) {
    $where_sql .= " AND (kitap_adi LIKE ? OR yazar LIKE ? OR isbn LIKE ?)";
    $params = array_merge($params, [$sql_arama_terimi, $sql_arama_terimi, $sql_arama_terimi]);
    $types .= "sss";
}

// Toplam Başlık Sayısını Al (Sayfalama için)
$sql_count = "SELECT COUNT(id) FROM kitaplar" . $where_sql;
$stmt_count = $mysqli->prepare($sql_count);
$stmt_count->bind_param($types, ...$params);
$stmt_count->execute();
$stmt_count->store_result();
$stmt_count->bind_result($toplam_kayit);
$stmt_count->fetch();
$toplam_sayfa = ceil($toplam_kayit / $kayit_per_sayfa);
$stmt_count->close();

// Tablo HTML'ini oluştur
$html_tablo = "";
$sql_list = "SELECT id, kitap_adi, yazar, raftaki_adet FROM kitaplar" . $where_sql . " ORDER BY kitap_adi LIMIT ? OFFSET ?";
$params[] = $kayit_per_sayfa; $types .= "i";
$params[] = $offset;          $types .= "i";

$stmt_list = $mysqli->prepare($sql_list);
$stmt_list->bind_param($types, ...$params);
$stmt_list->execute();
$stmt_list->store_result();
$stmt_list->bind_result($col_id, $col_kitap_adi, $col_yazar, $col_raftaki_adet);

if ($stmt_list->num_rows > 0) {
    while ($stmt_list->fetch()) {
        $html_tablo .= "<tr>";
        $html_tablo .= '<td><a href="kitap_detay.php?id=' . $col_id . '">' . htmlspecialchars($col_kitap_adi) . '</a></td>';
        $html_tablo .= "<td>" . htmlspecialchars($col_yazar) . "</td>";
        if ($col_raftaki_adet > 0) {
             $html_tablo .= '<td><span class="durum durum-rafta">Rafta</span></td>';
        } else {
             $html_tablo .= '<td><span class="durum durum-odunc">Ödünçte</span></td>';
        }
        $html_tablo .= "</tr>";
    }
} else {
    $html_tablo = '<tr><td colspan="3">Kayıt bulunamadı.</td></tr>';
}
$stmt_list->close();

// Sayfalama HTML'ini oluştur
$html_sayfalama = "";
if ($toplam_sayfa > 1) {
    $html_sayfalama = '<ul class="pagination">';
    $disabled = ($sayfa <= 1) ? 'disabled' : '';
    $html_sayfalama .= "<li class=\"$disabled\"><a href=\"#\" data-page=\"" . ($sayfa - 1) . "\">Önceki</a></li>";
    for ($i = 1; $i <= $toplam_sayfa; $i++) {
        $active = ($sayfa == $i) ? 'active' : '';
        $html_sayfalama .= "<li class=\"$active\"><a href=\"#\" data-page=\"$i\">$i</a></li>";
    }
    $disabled = ($sayfa >= $toplam_sayfa) ? 'disabled' : '';
    $html_sayfalama .= "<li class=\"$disabled\"><a href=\"#\" data-page=\"" . ($sayfa + 1) . "\">Sonraki</a></li>";
    $html_sayfalama .= '</ul>';
}

$mysqli->close();

// Sonuçları JSON olarak döndür
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'html_tablo' => $html_tablo,
    'html_sayfalama' => $html_sayfalama
]);
exit;
?>