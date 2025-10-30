<?php
// Bu betiğin sadece sunucu tarafından (CLI) çalıştırılması hedeflenir.
// Gerekli ayarları ve veritabanı bağlantısını dahil et.
require_once "config.php";

// Yedeklerin saklanacağı klasör
$backup_dir = __DIR__ . '/yedekler/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Yedek dosyasının adı
$backup_file = $backup_dir . 'tam_yedek_' . DB_NAME . '_' . date("Y-m-d_H-i-s") . '.sql';

// Yedek içeriğini oluşturmaya başla
$content = "-- RafTertip Kütüphane Yönetim Sistemi - Otomatik Tam Veritabanı Yedek Dosyası\n";
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

    $result = $mysqli->query('SELECT * FROM ' . $table);
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

// Yedeği dosyaya yaz
file_put_contents($backup_file, $content);

// ESKİ YEDEKLERİ TEMİZLEME (Son 7 yedeği sakla)
$yedekler = glob($backup_dir . '*.sql');
// Tarihe göre sırala (en yeni en üstte)
usort($yedekler, function($a, $b) {
    return filemtime($b) - filemtime($a);
});

// 7'den fazla yedek varsa en eskilerini sil
if (count($yedekler) > 7) {
    $silinecekler = array_slice($yedekler, 7);
    foreach ($silinecekler as $silinecek) {
        unlink($silinecek);
    }
}

echo "Yedekleme tamamlandı: " . basename($backup_file) . "\n";
$mysqli->close();
?>