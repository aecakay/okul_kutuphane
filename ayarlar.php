<?php
require_once 'header.php';
// Güvenlik: Giriş yapılmamışsa veya okul ID'si yoksa login sayfasına yönlendir
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["okul_id"])){
    header("location: login.php");
    exit;
}
if (!isset($mysqli) || $mysqli->connect_errno) { require "config.php"; }

$okul_id = $_SESSION["okul_id"];
// Oturum açan kullanıcının 'admin' olup olmadığını kontrol et
$is_admin = (isset($_SESSION["username"]) && $_SESSION["username"] === 'admin');

// --- POST İŞLEMLERİNİ YÖNETME ---

if($_SERVER["REQUEST_METHOD"] == "POST"){

    // 1. OKUL ADI GÜNCELLEME (SADECE ADMİN)
    if($is_admin && isset($_POST['okul_adi_guncelle'])){
        if(!empty(trim($_POST['okul_adi']))){
            $yeni_okul_adi = trim($_POST['okul_adi']);
            $sql_update_okul = "UPDATE okullar SET okul_adi = ? WHERE id = ?";
            if($stmt = $mysqli->prepare($sql_update_okul)){
                $stmt->bind_param("si", $yeni_okul_adi, $okul_id);
                if($stmt->execute()){
                    $_SESSION['toast_message'] = ['success' => true, 'message' => 'Okul adı başarıyla güncellendi. Değişikliğin menüde görünmesi için yeniden giriş yapmanız gerekebilir.'];
                } else {
                    $_SESSION['toast_message'] = ['success' => false, 'message' => 'HATA: Okul adı güncellenemedi.'];
                }
                $stmt->close();
            }
        } else {
            $_SESSION['toast_message'] = ['success' => false, 'message' => 'Okul adı boş bırakılamaz.'];
        }
        header("Location: ayarlar.php");
        exit;
    }

    // 2. KÜTÜPHANE PARAMETRELERİNİ GÜNCELLEME
    if(isset($_POST['diger_ayarlari_guncelle'])){
        $ayarlar_form = $_POST['ayarlar'];
        $ayarlar_form['ceza_tipi_para'] = isset($ayarlar_form['ceza_tipi_para']) ? '1' : '0';
        $ayarlar_form['ceza_tipi_yasak'] = isset($ayarlar_form['ceza_tipi_yasak']) ? '1' : '0';
        
        $mysqli->begin_transaction();
        try {
            $sql_ayar = "INSERT INTO ayarlar (okul_id, ayar_adi, ayar_degeri) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE ayar_degeri = VALUES(ayar_degeri)";
            if($stmt_ayar = $mysqli->prepare($sql_ayar)){
                foreach($ayarlar_form as $ayar_adi => $ayar_degeri){
                    $stmt_ayar->bind_param("iss", $okul_id, $ayar_adi, $ayar_degeri);
                    $stmt_ayar->execute();
                }
                $stmt_ayar->close();
                $mysqli->commit();
                $_SESSION['toast_message'] = ['success' => true, 'message' => 'Ayarlar başarıyla kaydedildi.'];
            } else {
                throw new Exception("SQL hazırlama hatası.");
            }
        } catch (Exception $e) {
            $mysqli->rollback();
            $_SESSION['toast_message'] = ['success' => false, 'message' => 'HATA: Ayarlar kaydedilemedi.'];
        }
        header("Location: ayarlar.php");
        exit;
    }
}

// --- SAYFA YÜKLENİRKEN GEREKLİ VERİLERİ ÇEKME ---

// Mevcut okul adını çek (Sadece admin için)
$mevcut_okul_adi = '';
if ($is_admin) {
    $sql_okul = "SELECT okul_adi FROM okullar WHERE id = ?";
    if($stmt_okul = $mysqli->prepare($sql_okul)){
        $stmt_okul->bind_param("i", $okul_id);
        $stmt_okul->execute();
        $stmt_okul->bind_result($db_okul_adi);
        $stmt_okul->fetch();
        $mevcut_okul_adi = $db_okul_adi;
        $stmt_okul->close();
    }
}

// Kütüphane parametrelerini çek
$ayarlar = [];
$sql_ayarlar_sorgu = "SELECT ayar_adi, ayar_degeri FROM ayarlar WHERE okul_id = ?";
if($stmt_ayarlar_sorgu = $mysqli->prepare($sql_ayarlar_sorgu)){
    $stmt_ayarlar_sorgu->bind_param("i", $okul_id);
    $stmt_ayarlar_sorgu->execute();
    $stmt_ayarlar_sorgu->bind_result($ayar_adi, $ayar_degeri);
    while($stmt_ayarlar_sorgu->fetch()){
        $ayarlar[$ayar_adi] = $ayar_degeri;
    }
    $stmt_ayarlar_sorgu->close();
}

// Yedek dosyalarını oku (Sadece admin için)
$yedekler = [];
if ($is_admin) {
    $backup_dir = __DIR__ . '/yedekler/';
    if (is_dir($backup_dir)) {
        $files = glob($backup_dir . '*.sql');
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        $yedekler = $files;
    }
}
?>

<?php if($is_admin): ?>
<div class="form-wrapper">
    <h3>Genel Ayarlar</h3>
    <form action="ayarlar.php" method="post">
        <div class="form-group">
            <label for="okulAdiInput">Okul Adı</label>
            <input type="text" id="okulAdiInput" name="okul_adi" value="<?php echo htmlspecialchars($mevcut_okul_adi); ?>" required>
        </div>
        <input type="submit" name="okul_adi_guncelle" value="Okul Adını Güncelle">
    </form>
</div>
<?php endif; ?>


<div class="form-wrapper" style="margin-top: 30px;">
    <h3>Kütüphane Parametreleri</h3>
    <form action="ayarlar.php" method="post">
        <div class="form-group">
            <label for="oduncSuresiInput">Varsayılan Ödünç Süresi (Gün)</label>
            <input type="number" id="oduncSuresiInput" name="ayarlar[varsayilan_iade_suresi_gun]" min="1" value="<?php echo htmlspecialchars($ayarlar['varsayilan_iade_suresi_gun'] ?? '14'); ?>" required>
        </div>
        <div class="form-group">
            <label for="oduncLimitiInput">Öğrenci Başına Ödünç Kitap Limiti</label>
            <input type="number" id="oduncLimitiInput" name="ayarlar[odunc_kitap_limiti]" min="1" value="<?php echo htmlspecialchars($ayarlar['odunc_kitap_limiti'] ?? '3'); ?>" required>
        </div>
        <hr>
        <h4>Gecikme Cezası Ayarları</h4>
        <div class="form-group">
            <label>Uygulanacak Ceza Tipleri</label>
            <div class="checkbox-group">
                <label><input type="checkbox" name="ayarlar[ceza_tipi_para]" value="1" <?php if(!empty($ayarlar['ceza_tipi_para']) && $ayarlar['ceza_tipi_para'] == '1') echo 'checked'; ?>> Para Cezası</label>
                <label><input type="checkbox" name="ayarlar[ceza_tipi_yasak]" value="1" <?php if(!empty($ayarlar['ceza_tipi_yasak']) && $ayarlar['ceza_tipi_yasak'] == '1') echo 'checked'; ?>> Kitap Alma Yasağı</label>
            </div>
        </div>
        <div class="form-group">
            <label for="gunlukCezaInput">Günlük Para Cezası Miktarı (TL)</label>
            <input type="text" id="gunlukCezaInput" name="ayarlar[gunluk_ceza_miktari]" placeholder="Örn: 0.50" value="<?php echo htmlspecialchars($ayarlar['gunluk_ceza_miktari'] ?? '0.50'); ?>">
        </div>
        <div class="form-group">
            <label for="yasakGunSayisiInput">Kitap Alma Yasağı Süresi (Gün)</label>
            <input type="number" id="yasakGunSayisiInput" name="ayarlar[yasak_gun_sayisi]" min="1" value="<?php echo htmlspecialchars($ayarlar['yasak_gun_sayisi'] ?? '7'); ?>">
        </div>
        <input type="submit" name="diger_ayarlari_guncelle" value="Parametreleri Kaydet">
    </form>
</div>

<?php if($is_admin): ?>
<div class="form-wrapper" style="margin-top: 30px;">
    <h3>Yedekleme Yönetimi</h3>
    <p>Sistem, her gece otomatik olarak tam veritabanı yedeği alır ve son 7 yedeği saklar. Aşağıdan mevcut yedekleri yönetebilir veya manuel yedek alabilirsiniz.</p>
    
    <div style="margin-bottom: 20px;">
        <form action="export.php" method="post" style="display: inline-block;">
             <input type="submit" name="export" value="Şimdi Manuel Yedek Al" class="button-secondary">
        </form>
    </div>

    <h4>Mevcut Yedekler (En Yeni Üstte)</h4>
    <?php if(!empty($yedekler)): ?>
        <table class="table-layout-fixed">
            <thead>
                <tr>
                    <th>Yedek Dosyası</th>
                    <th>Oluşturma Tarihi</th>
                    <th style="width: 250px;">İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($yedekler as $yedek): ?>
                <tr>
                    <td data-label="Dosya Adı"><?php echo basename($yedek); ?></td>
                    <td data-label="Tarih"><?php echo date("d.m.Y H:i:s", filemtime($yedek)); ?></td>
                    <td data-label="İşlemler">
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <form action="yedek_geri_yukle.php" method="post" onsubmit="return confirm('DİKKAT! Bu yedeği geri yüklemek istediğinizden emin misiniz? Mevcut TÜM veriler silinecek ve bu yedeğin içeriğiyle değiştirilecektir. Bu işlem geri alınamaz!');">
                                <input type="hidden" name="backup_file" value="<?php echo basename($yedek); ?>">
                                <button type="submit" name="restore_backup" class="button-link button-warning">Geri Yükle</button>
                            </form>
                            <form action="yedek_sil.php" method="post" onsubmit="return confirm('Bu yedek dosyasını kalıcı olarak silmek istediğinizden emin misiniz?');">
                                <input type="hidden" name="backup_file" value="<?php echo basename($yedek); ?>">
                                <button type="submit" name="delete_backup" class="button-link button-danger">Sil</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Henüz oluşturulmuş bir yedek bulunmamaktadır. Otomatik yedeklemenin çalışması için sunucuda Cron Job ayarlanmalıdır.</p>
    <?php endif; ?>
</div>
<?php endif; ?>


<?php 
if(isset($mysqli) && !$mysqli->connect_errno) { $mysqli->close(); }
require_once 'footer.php'; 
?>