<?php
require_once 'header.php';
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["okul_id"])){ header("location: login.php"); exit; }
if (!isset($mysqli) || $mysqli->connect_errno) { require "config.php"; }

$mesaj_okul_adi = $mesaj_sure = $mesaj_sifre = $mesaj_import = $mesaj_ceza = $mesaj_limit = "";
$okul_id = $_SESSION["okul_id"];

// Fonksiyon: Ayar değerini önce KONTROL EDER, yoksa EKLER, sonra GÜNCELLER
function update_or_insert_ayar($mysqli, $ayar_adi, $yeni_deger, $okul_id) {
    // 1. Kontrol: Ayar mevcut mu?
    $sql_check = "SELECT ayar_degeri FROM ayarlar WHERE ayar_adi = ? AND okul_id = ?";
    if ($stmt_check = $mysqli->prepare($sql_check)) {
        $stmt_check->bind_param("si", $ayar_adi, $okul_id);
        $stmt_check->execute();
        $stmt_check->store_result();
        $exists = $stmt_check->num_rows > 0;
        $stmt_check->close();
    } else { return false; }
    
    if ($exists) {
        // 2a. Mevcutsa, sadece güncelle
        $sql_update = "UPDATE ayarlar SET ayar_degeri = ? WHERE ayar_adi = ? AND okul_id = ?";
        if($stmt_update = $mysqli->prepare($sql_update)){
            $stmt_update->bind_param("ssi", $yeni_deger, $ayar_adi, $okul_id);
            $result = $stmt_update->execute();
            $stmt_update->close();
            return $result;
        }
    } else {
        // 2b. Mevcut değilse, ekle
        $sql_insert = "INSERT INTO ayarlar (okul_id, ayar_adi, ayar_degeri) VALUES (?, ?, ?)";
        if($stmt_insert = $mysqli->prepare($sql_insert)){
            $stmt_insert->bind_param("iss", $okul_id, $ayar_adi, $yeni_deger);
            $result = $stmt_insert->execute();
            $stmt_insert->close();
            return $result;
        }
    }
    return false;
}

// ----------------------------------------------------
// POST İŞLEMLERİ
// ----------------------------------------------------
// 0. OKUL ADI GÜNCELLEME
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['guncelle_okul_adi'])){
    if(isset($_POST['okul_adi']) && !empty(trim($_POST['okul_adi']))){
        $yeni_okul_adi = trim($_POST['okul_adi']);
        // Okul adını Ayarlar tablosunda okul_id'ye göre güncelle
        if(update_or_insert_ayar($mysqli, 'okul_adi', $yeni_okul_adi, $okul_id)) {
             $mesaj_okul_adi = '<p class="success">Okul adı başarıyla güncellendi.</p>';
        } else {
             $mesaj_okul_adi = '<p class="error">HATA: Okul adı güncellenirken bir sorun oluştu.</p>';
        }
    } else { $mesaj_okul_adi = '<p class="error">HATA: Okul adı boş olamaz.</p>'; }
}

// 1. VARSAYILAN İADE SÜRESİ GÜNCELLEME
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['guncelle_sure'])){
    if(isset($_POST['varsayilan_iade_suresi']) && is_numeric($_POST['varsayilan_iade_suresi']) && $_POST['varsayilan_iade_suresi'] > 0){
        $yeni_sure = (int)$_POST['varsayilan_iade_suresi'];
        if(update_or_insert_ayar($mysqli, 'varsayilan_iade_suresi', (string)$yeni_sure, $okul_id)) {
            $mesaj_sure = '<p class="success">Varsayılan iade süresi başarıyla güncellendi.</p>'; 
        } else { $mesaj_sure = '<p class="error">HATA: Ayar güncellenirken bir sorun oluştu.</p>'; }
    } else { $mesaj_sure = '<p class="error">HATA: Lütfen geçerli bir gün sayısı girin.</p>'; }
}

// 2. ÖDÜNÇ KİTAP LİMİTİ GÜNCELLEME
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['guncelle_limit'])){
    if(isset($_POST['odunc_kitap_limiti']) && is_numeric($_POST['odunc_kitap_limiti']) && $_POST['odunc_kitap_limiti'] >= 0){
        $yeni_limit = (int)$_POST['odunc_kitap_limiti'];
        if(update_or_insert_ayar($mysqli, 'odunc_kitap_limiti', (string)$yeni_limit, $okul_id)) {
            $mesaj_limit = '<p class="success">Ödünç kitap limiti başarıyla güncellendi.</p>'; 
        } else { $mesaj_limit = '<p class="error">HATA: Limit güncellenirken bir sorun oluştu.</p>'; }
    } else { $mesaj_limit = '<p class="error">HATA: Lütfen geçerli bir limit sayısı girin (0 veya üzeri).</p>'; }
}


// 3. CEZA AYARLARI GÜNCELLEME
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['guncelle_ceza'])){
    $hata_var = false;
    
    $yeni_ceza_miktari = str_replace(',', '.', trim($_POST['gunluk_ceza_miktari']));
    if (!is_numeric($yeni_ceza_miktari) || $yeni_ceza_miktari < 0) {
        $mesaj_ceza .= '<p class="error">HATA: Geçerli bir ceza miktarı girin (0.50 gibi).</p>'; $hata_var = true;
    }
    $yeni_yasak_gun = (int)($_POST['yasak_gun_sayisi'] ?? 0);
    if (!is_numeric($yeni_yasak_gun) || $yeni_yasak_gun < 0) {
        $mesaj_ceza .= '<p class="error">HATA: Geçerli bir yasak gün sayısı girin.</p>'; $hata_var = true;
    }

    if (!$hata_var) {
        $tip_para = isset($_POST['ceza_tipi_para']) ? '1' : '0';
        $tip_yasak = isset($_POST['ceza_tipi_yasak']) ? '1' : '0';

        $sonuc = update_or_insert_ayar($mysqli, 'gunluk_ceza_miktari', $yeni_ceza_miktari, $okul_id);
        $sonuc &= update_or_insert_ayar($mysqli, 'yasak_gun_sayisi', (string)$yeni_yasak_gun, $okul_id);
        $sonuc &= update_or_insert_ayar($mysqli, 'ceza_tipi_para', $tip_para, $okul_id);
        $sonuc &= update_or_insert_ayar($mysqli, 'ceza_tipi_yasak', $tip_yasak, $okul_id);

        if ($sonuc) {
            $mesaj_ceza = '<p class="success">Ceza ayarları başarıyla güncellendi.</p>';
        } else {
            $mesaj_ceza = '<p class="error">HATA: Ceza ayarları güncellenirken bir sorun oluştu.</p>';
        }
    }
}

// 4. ŞİFRE DEĞİŞTİRME
$mevcut_sifre_err = $yeni_sifre_err = $yeni_sifre_tekrar_err = "";
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['guncelle_sifre'])){ 
    // ... (Şifre değiştirme mantığı aynı, school_id zaten sorguda kullanılıyor)
}

// 5. İÇE AKTARMA
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["csv_file"]) && isset($_POST["import_type"]) && isset($_POST['ice_aktar'])) { 
    // ... (İçe aktarma mantığı aynı)
}

// ----------------------------------------------------
// AYAR DEĞERLERİNİ ÇEKME (DÜZELTİLDİ)
// ----------------------------------------------------
$ayarlar = [];
// Sadece giriş yapılan okula ait ayarları çek
$sql_select_ayarlar = "SELECT ayar_adi, ayar_degeri FROM ayarlar WHERE okul_id = ?";

if($stmt_select = $mysqli->prepare($sql_select_ayarlar)) {
    $stmt_select->bind_param("i", $okul_id);
    $stmt_select->execute();
    $stmt_select->store_result();
    $stmt_select->bind_result($ayar_adi, $ayar_degeri);
    
    while($stmt_select->fetch()) {
        $ayarlar[$ayar_adi] = $ayar_degeri;
    }
    $stmt_select->close();
} else {
    $mesaj_okul_adi = '<p class="error">HATA: Ayarlar yüklenemedi. Veritabanı hatası.</p>';
}

// Varsayılan Değerler: Eğer veritabanından çekilemezse (ilk kez giriş yapıldıysa) burada varsayılan değerler kullanılır.
$varsayilan_sure = $ayarlar['varsayilan_iade_suresi'] ?? '14';
$okul_adi = $ayarlar['okul_adi'] ?? 'Kütüphane';
$gunluk_ceza = $ayarlar['gunluk_ceza_miktari'] ?? '0.50'; 
$ceza_tipi_para = ($ayarlar['ceza_tipi_para'] ?? '1') == '1';
$ceza_tipi_yasak = ($ayarlar['ceza_tipi_yasak'] ?? '1') == '1';
$yasak_gun_sayisi = $ayarlar['yasak_gun_sayisi'] ?? '7';
$odunc_limit = $ayarlar['odunc_kitap_limiti'] ?? '3';

// CRON JOB İÇİN YOL HESAPLAMA
$abs_path = __DIR__;
$cron_command = "0 3 * * * /usr/bin/php -f {$abs_path}/yedek_al.php > /dev/null 2>&1";
$cron_url_command = "0 3 * * * /usr/bin/wget -q -O /dev/null 'http://SITENIZ.COM" . dirname($_SERVER['PHP_SELF']) . "/yedek_al.php' > /dev/null 2>&1";
?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
    <div class="form-wrapper">
        <h3>Temel Ayarlar</h3>
        <div style="border-bottom: 1px dashed var(--border-color); padding-bottom: 20px; margin-bottom: 20px;">
            <h4>Okul Bilgileri</h4>
            <?php echo $mesaj_okul_adi; ?>
            <form action="ayarlar.php" method="post">
                <div class="form-group">
                    <label for="okul_adi">Okul Adı:</label>
                    <input type="text" name="okul_adi" id="okul_adi" value="<?php echo htmlspecialchars($okul_adi); ?>" required>
                 </div>
                <input type="submit" name="guncelle_okul_adi" value="Okul Adını Kaydet">
            </form>
        </div>
        <div>
            <h4>Yönetici Şifresini Değiştir</h4>
            <?php echo $mesaj_sifre; ?>
            <form action="ayarlar.php" method="post">
                <div class="form-group"> <label>Mevcut Şifre</label> <input type="password" name="mevcut_sifre"> <?php if(!empty($mevcut_sifre_err)){ echo '<p class="error">' . $mevcut_sifre_err . '</p>'; } ?> </div>
                <div class="form-group"> <label>Yeni Şifre (En az 6 karakter)</label> <input type="password" name="yeni_sifre"> <?php if(!empty($yeni_sifre_err)){ echo '<p class="error">' . $yeni_sifre_err . '</p>'; } ?> </div>
                <div class="form-group"> <label>Yeni Şifre (Tekrar)</label> <input type="password" name="yeni_sifre_tekrar"> <?php if(!empty($yeni_sifre_tekrar_err)){ echo '<p class="error">' . $yeni_sifre_tekrar_err . '</p>'; } ?> </div>
                <input type="submit" name="guncelle_sifre" value="Şifreyi Değiştir">
            </form>
        </div>
    </div>
    
    <div class="form-wrapper">
        <h3>Kitap Ayarları ve Cezalar</h3>
        
        <div style="border-bottom: 1px dashed var(--border-color); padding-bottom: 20px; margin-bottom: 20px;">
            <h4>Ödünç Süresi ve Limiti</h4>
            <?php echo $mesaj_sure; echo $mesaj_limit; ?>
            <form action="ayarlar.php" method="post" style="display: flex; gap: 10px; align-items: flex-end;">
                <div class="form-group" style="flex: 1;">
                    <label for="varsayilan_iade_suresi">Süre (Gün):</label>
                    <input type="number" name="varsayilan_iade_suresi" id="varsayilan_iade_suresi" value="<?php echo htmlspecialchars($varsayilan_sure); ?>" min="1" required style="margin-bottom: 0;">
                </div>
                <input type="submit" name="guncelle_sure" value="Kaydet" class="button-secondary" style="flex: 0 0 100px;">
            </form>
            <form action="ayarlar.php" method="post" style="display: flex; gap: 10px; align-items: flex-end; margin-top:10px;">
                <div class="form-group" style="flex: 1;">
                    <label for="odunc_kitap_limiti">Limit (Adet):</label>
                    <input type="number" name="odunc_kitap_limiti" id="odunc_kitap_limiti" value="<?php echo htmlspecialchars($odunc_limit); ?>" min="0" required style="margin-bottom: 0;">
                </div>
                <input type="submit" name="guncelle_limit" value="Kaydet" class="button-secondary" style="flex: 0 0 100px;">
            </form>
        </div>

        <h4>Gecikme Cezası Ayarları</h4>
        <?php echo $mesaj_ceza; ?>
        <form action="ayarlar.php" method="post">
            
            <div style="display: flex; gap: 20px;">
                <div class="form-group" style="flex: 1;">
                    <label style="margin-bottom: 10px; display: flex; align-items: center;">
                        <input type="checkbox" name="ceza_tipi_para" <?php echo $ceza_tipi_para ? 'checked' : ''; ?> style="width: 20px; height: 20px; margin-right: 10px;">
                        <span>Günlük Para Cezası Uygula</span>
                    </label>
                    <label for="gunluk_ceza_miktari">Günlük Ceza Miktarı (TL):</label>
                    <input type="text" name="gunluk_ceza_miktari" id="gunluk_ceza_miktari" value="<?php echo htmlspecialchars($gunluk_ceza); ?>" pattern="[0-9]*[.,]?[0-9]+" required>
                </div>
                
                <div class="form-group" style="flex: 1;">
                    <label style="margin-bottom: 10px; display: flex; align-items: center;">
                        <input type="checkbox" name="ceza_tipi_yasak" <?php echo $ceza_tipi_yasak ? 'checked' : ''; ?> style="width: 20px; height: 20px; margin-right: 10px;">
                        <span>Kitap Alma Yasağı Uygula</span>
                    </label>
                    <label for="yasak_gun_sayisi">Yasak Süresi (Gün):</label>
                    <input type="number" name="yasak_gun_sayisi" id="yasak_gun_sayisi" value="<?php echo htmlspecialchars($yasak_gun_sayisi); ?>" min="0" required>
                </div>
            </div>
            
            <input type="submit" name="guncelle_ceza" value="Ceza Ayarlarını Kaydet">
        </form>
        <small style="display: block; color: var(--text-secondary); margin-top: 5px;">Her iki ceza tipi de aynı anda uygulanabilir.</small>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
    <div class="form-wrapper">
        <h3>Veri Yönetimi</h3>
        <div style="border-bottom: 1px dashed var(--border-color); padding-bottom: 20px; margin-bottom: 20px;">
            <h4>Veri İçe/Dışa Aktarma (CSV)</h4>
            <?php echo $mesaj_import; ?>
            <form action="ayarlar.php" method="post" enctype="multipart/form-data" style="margin-bottom: 20px;">
                <div class="form-group"> <label for="import_type">Ne İçe Aktarılacak?</label>
                    <select name="import_type" id="import_type" required> <option value=""></option> <option value="kitaplar">Kitap Listesi</option> <option value="ogrenciler">Öğrenci Listesi</option> </select> 
                </div>
                <div class="form-group"> <label for="csv_file">CSV Dosyası:</label> <input type="file" name="csv_file" id="csv_file" accept=".csv" required> </div>
                <input type="submit" name="ice_aktar" value="İçe Aktar">
            </form>
            <div style="display: flex; gap: 15px;">
                <a href="export.php?type=kitaplar" class="button-link button-secondary" style="flex: 1;">Kitapları İndir</a>
                <a href="export.php?type=ogrenciler" class="button-link button-secondary" style="flex: 1;">Öğrencileri İndir</a>
            </div>
        </div>
        <div>
            <h4>Veritabanı ve Site Yedekleme</h4>
            <p style="color: var(--text-secondary); font-size: 0.9em; margin-bottom: 15px;">Yedekler, <code>/yedekler/</code> dizinine kaydedilir.</p>
            <form action="yedek_al.php" method="post" style="margin-bottom: 25px;">
                <input type="submit" value="Hemen Tam Yedek Al" class="button-warning" style="width: 100%;">
            </form>
            <h5>Otomatik Planlı Yedekleme (Cron Job)</h5>
            <p style="font-size: 0.9em; color: var(--text-secondary);">Sunucunuzun kontrol panelindeki Cron Job ayarlarına aşağıdaki komutu ekleyin (her gece 03:00):</p>
            <div style="background-color: var(--bg-content); padding: 10px; border-radius: var(--border-radius); font-family: monospace; font-size: 0.85em; overflow-x: auto; border: 1px solid var(--border-color);">
                 <?php echo $cron_command; ?>
            </div>
        </div>
    </div>

    <div class="form-wrapper" style="opacity: 0.5;">
         <h3>Gelecek Ayarlar</h3>
         <p style="color: var(--text-secondary);">Bu alana gelecekte yeni ayar seçenekleri eklenebilir.</p>
    </div>
</div>

<?php
if (isset($mysqli) && !$mysqli->connect_errno) { $mysqli->close(); }
require_once 'footer.php';
?>