<?php
require_once 'header.php';
// Güvenlik: Giriş yapılmamışsa veya okul ID'si yoksa login sayfasına yönlendir
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["okul_id"])){
    header("location: login.php");
    exit;
}

// === DOSYA İŞLEME MANTIĞI FONKSİYONU (DÜZELTİLMİŞ) ===
function process_ogrenci_file($mysqli, $file_path, $okul_id) {
    ini_set('auto_detect_line_endings', TRUE);
    $file_content = file_get_contents($file_path);
    if (!$file_content) return ['success' => false, 'message' => 'Dosya okunamadı.', 'details' => []];

    $comma_count = substr_count($file_content, ',');
    $semicolon_count = substr_count($file_content, ';');
    $delimiter = $semicolon_count > $comma_count ? ';' : ',';
    
    $file = fopen($file_path, "r");
    if (!$file) return ['success' => false, 'message' => 'Dosya tekrar açılamadı.', 'details' => []];

    $sinif = ""; $ogrenciler_basladi = false; $yeni_eklenen = 0; $guncellenen = 0; $hatali_satir = 0;
    $is_eokul_format = false; $line_count = 0; $bulunan_siniflar = [];

    $mysqli->begin_transaction();
    try {
        $sql = "INSERT INTO ogrenciler (okul_id, ogrenci_no, ad, soyad, sinif) 
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                ad = VALUES(ad), soyad = VALUES(soyad), sinif = VALUES(sinif)";
        $stmt = $mysqli->prepare($sql);

        while (($data = fgetcsv($file, 2000, $delimiter)) !== FALSE) {
            $line_count++;
            $data = array_map(fn($str) => trim(mb_convert_encoding($str, 'UTF-8', 'auto')), $data);

            // --- YENİ DÜZELTME BAŞLANGICI ---
            // Herhangi bir satırda yeni bir sınıf başlığı var mı diye kontrol et
            if (isset($data[0]) && preg_match('/([0-9]{1,2})\. Sınıf \/ ([A-ZÇĞİÖŞÜ]) Şubesi/u', $data[0], $matches)) {
                $is_eokul_format = true;
                // Yeni sınıfı bulduk, mevcut sınıfı güncelle
                $sinif = $matches[1] . '/' . $matches[2];
                if (!in_array($sinif, $bulunan_siniflar)) {
                    $bulunan_siniflar[] = $sinif;
                }
                // Yeni bir sınıf başladığı için öğrenci listesinin de yeniden başlaması gerekiyor.
                $ogrenciler_basladi = false; 
                continue; // Bu satır başlık olduğu için atla
            }
            // --- YENİ DÜZELTME SONU ---

            if ($is_eokul_format) {
                if (isset($data[0]) && trim($data[0]) == "S.No") { $ogrenciler_basladi = true; continue; }
                if (isset($data[0]) && strpos($data[0], 'Öğrenci Sayısı') !== false) { $ogrenciler_basladi = false; continue; }
                if (!$ogrenciler_basladi) continue;
                
                $ogrenci_no_raw = isset($data[1]) ? $data[1] : '';
                $ad = isset($data[3]) ? $data[3] : '';
                $soyad = isset($data[7]) ? $data[7] : '';
            } else { // Standart CSV mantığı (değişiklik yok)
                if ($line_count == 1 && (strtolower($data[0]) == 'ogrenci_no' || strtolower($data[0]) == 'no')) continue;
                $ogrenci_no_raw = isset($data[0]) ? $data[0] : '';
                $ad = isset($data[1]) ? $data[1] : '';
                $soyad = isset($data[2]) ? $data[2] : '';
                $sinif = isset($data[3]) ? $data[3] : '';
            }

            $ogrenci_no = preg_replace('/[^0-9]/', '', $ogrenci_no_raw);

            if (empty($ogrenci_no) || empty($ad) || empty($soyad) || empty($sinif)) {
                if(!empty($ogrenci_no) || !empty($ad)) $hatali_satir++;
                continue;
            }
            
            $stmt->bind_param("issss", $okul_id, $ogrenci_no, $ad, $soyad, $sinif);
            $stmt->execute();
            
            if ($stmt->affected_rows === 1) {
                $yeni_eklenen++;
            } elseif ($stmt->affected_rows === 2) {
                $guncellenen++;
            }
        }
        
        $mysqli->commit();
        $stmt->close();

        return [
            'success' => true, 'message' => 'Öğrenci aktarımı başarıyla tamamlandı.',
            'details' => [
                'Kullanılan Ayırıcı: ' . ($delimiter === ';' ? 'Noktalı Virgül' : 'Virgül'),
                'Tespit Edilen Format: ' . ($is_eokul_format ? 'e-Okul Listesi' : 'Standart CSV'),
                'İşlenen Sınıflar: ' . (!empty($bulunan_siniflar) ? implode(', ', $bulunan_siniflar) : 'Sınıf bulunamadı'),
                'Yeni Eklenen Öğrenci: ' . $yeni_eklenen,
                'Güncellenen Öğrenci: ' . $guncellenen,
                'Hatalı/Boş Satır: ' . $hatali_satir
            ]
        ];
    } catch (Exception $e) {
        $mysqli->rollback();
        return ['success' => false, 'message' => 'Veritabanı hatası: ' . $e->getMessage(), 'details' => []];
    } finally {
        fclose($file);
    }
}

// === SAYFA GÖRÜNÜMÜ VE FORM ===
$import_type = isset($_GET['type']) ? $_GET['type'] : 'ogrenci';
$page_title = $import_type === 'kitap' ? 'Kitap' : 'Öğrenci';
$action_url = "import.php?type={$import_type}";
?>

<div class="form-wrapper">
    <h3>Toplu <?php echo $page_title; ?> Aktarma</h3>
    <p>Bu arayüzü kullanarak <strong>e-Okul Sınıf Listelerini</strong> veya <strong>Standart CSV</strong> dosyalarını sisteme yükleyebilirsiniz.</p>
    
    <form action="<?php echo $action_url; ?>" method="post" enctype="multipart/form-data" style="margin-top: 20px;">
        <div class="form-group">
            <label for="import_file">Yüklenecek Dosyayı Seçin (.csv formatında)</label>
            <input type="file" name="import_file" id="import_file" accept=".csv" required>
            <small style="display: block; color: var(--text-secondary); margin-top: 10px;">
                <strong>e-Okul Listesi:</strong> Doğrudan yükleyebilirsiniz. Sınıf bilgisi dosya içeriğinden otomatik olarak okunacaktır.<br>
                <strong>Standart Liste:</strong> CSV dosyanızın sütunları `<?php echo $import_type === 'kitap' ? 'kitap_adi,yazar,isbn,toplam_adet' : 'ogrenci_no,ad,soyad,sinif'; ?>` şeklinde olmalıdır.
            </small>
        </div>
        <input type="submit" name="upload" value="<?php echo $page_title; ?> Bilgilerini İçeri Aktar">
    </form>
</div>

<?php
// === PHP İŞLEM KISMI: Dosya yüklendiğinde çalışır ===
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["import_file"])) {
    if ($_FILES["import_file"]["error"] == UPLOAD_ERR_OK) {
        $okul_id = $_SESSION["okul_id"];
        $file_path = $_FILES["import_file"]["tmp_name"];
        
        function show_import_result($result) {
            $alert_class = $result['success'] ? 'success' : 'error';
            echo '<div class="form-wrapper" style="margin-top: 20px; border-left: 5px solid var(--' . $alert_class . ');">';
            echo '<h4>Aktarım Sonucu</h4>';
            echo '<p>' . htmlspecialchars($result['message']) . '</p>';
            if(!empty($result['details'])){
                echo '<ul>';
                foreach($result['details'] as $detail){
                    echo '<li>' . htmlspecialchars($detail) . '</li>';
                }
                echo '</ul>';
            }
            echo '</div>';
        }

        if ($import_type === 'ogrenci') {
            $result = process_ogrenci_file($mysqli, $file_path, $okul_id);
            show_import_result($result);
        }
        elseif ($import_type === 'kitap') {
             show_import_result(['success' => false, 'message' => 'Kitap içeri aktarma özelliği henüz tamamlanmamıştır.', 'details' => []]);
        }
    } else {
        echo '<p class="error">Dosya yüklenirken bir hata oluştu.</p>';
    }
}

require_once 'footer.php'; 
?>