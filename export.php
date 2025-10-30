<?php
session_start();
// Güvenlik: Sadece ana admin bu işlemi yapabilir
if(!isset($_SESSION["username"]) || $_SESSION["username"] !== 'admin'){
    echo "Bu işlemi sadece ana yönetici yapabilir.";
    exit;
}
require_once "config.php";

$backup_file = 'tam_yedek_' . DB_NAME . '_' . date("Y-m-d-H-i-s") . '.sql';
$content = "-- RafTertip Kütüphane Yönetim Sistemi - Tam Veritabanı Yedek Dosyası\n";
$content .= "-- Yedekleme Tarihi: " . date('Y-m-d H:i:s') . "\n";
$content .= "-- Veritabanı: " . DB_NAME . "\n\n";

// Yedeklenecek TÜM tablolar
$tables = ['okullar', 'yoneticiler', 'ayarlar', 'islemler', 'kitaplar', 'ogrenciler', 'rezervasyonlar'];

foreach ($tables as $table) {
    $table_structure_query = $mysqli->query('SHOW CREATE TABLE ' . $table);
    if (!$table_structure_query) continue;
    
    $row_structure = $table_structure_query->fetch_row();
    $content .= "\n\n-- --------------------------------------------------------\n";
    $content .= "-- Tablo Yapısı: `$table`\n";
    $content .= "-- --------------------------------------------------------\n\n";
    $content .= $row_structure[1] . ";\n\n";
    
    $content .= "-- --------------------------------------------------------\n";
    $content .= "-- Tablo Dökümü: `$table`\n";
    $content .= "-- --------------------------------------------------------\n\n";

    // 'okullar' ve 'yoneticiler' tabloları global olduğu için tüm veriyi al,
    // diğerlerini ise okula göre yedekle (gelecekteki esneklik için bu mantık korunabilir)
    $sql = 'SELECT * FROM ' . $table;
    
    $result = $mysqli->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $content .= 'INSERT INTO `' . $table . '` VALUES(';
            $field_count = count($row);
            $current_field = 0;
            foreach ($row as $value) {
                if (isset($value)) {
                    $content .= '"' . $mysqli->real_escape_string($value) . '"';
                } else {
                    $content .= 'NULL';
                }
                if ($current_field < ($field_count - 1)) {
                    $content .= ',';
                }
                $current_field++;
            }
            $content .= ");\n";
        }
    }
    if($result) $result->free();
}

// Dosyayı tarayıcıya indir
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($backup_file) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . strlen($content));
ob_clean();
flush();
echo $content;
exit;
?>