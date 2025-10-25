<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Öğrenci giriş yapmamışsa, giriş sayfasına yönlendir
if (!isset($_SESSION["student_loggedin"]) || $_SESSION["student_loggedin"] !== true) {
    header("location: ogrenci_giris.php");
    exit;
}

require_once 'header.php';
if (!isset($mysqli) || $mysqli->connect_errno) { require "config.php"; }

// Oturumdan öğrenci bilgilerini al
$student_id = $_SESSION["student_id"];
$okul_id = $_SESSION["student_okul_id"];

$kitaplar = [];
$toplam_okunan_kitap = 0;
$toplam_okunan_sayfa = 0;
$toplam_ceza_miktari = 0.00;
$yasak_var = false;
$yasak_bitis_tarihi = null;

$bugun_timestamp = strtotime(date('Y-m-d'));

// 1. O OKULA AİT AYARLARI ÇEK
$ayarlar = [];
$sql_ayarlar = "SELECT ayar_adi, ayar_degeri FROM ayarlar WHERE okul_id = ?";
if ($stmt_ayar = $mysqli->prepare($sql_ayarlar)) {
    $stmt_ayar->bind_param("i", $okul_id);
    $stmt_ayar->execute();
    $stmt_ayar->store_result();
    $stmt_ayar->bind_result($ayar_adi, $ayar_degeri);
    while ($stmt_ayar->fetch()) {
        $ayarlar[$ayar_adi] = $ayar_degeri;
    }
    $stmt_ayar->close();
}
$gunluk_ceza = (float)($ayarlar['gunluk_ceza_miktari'] ?? 0.00);
$ceza_tipi_para_aktif = ($ayarlar['ceza_tipi_para'] ?? '0') == '1';
$ceza_tipi_yasak_aktif = ($ayarlar['ceza_tipi_yasak'] ?? '0') == '1';

// 2. ÖĞRENCİ BİLGİLERİ VE YASAK KONTROLÜNÜ ÇEK
$sql_ogrenci = "SELECT kitap_almasi_yasak_tarih FROM ogrenciler WHERE id = ? AND okul_id = ?";
if($stmt_ogr = $mysqli->prepare($sql_ogrenci)){
    $stmt_ogr->bind_param("ii", $student_id, $okul_id);
    $stmt_ogr->execute();
    $stmt_ogr->store_result();
    $stmt_ogr->bind_result($db_yasak_tarihi);
    if ($stmt_ogr->fetch()) {
        $yasak_bitis_tarihi = $db_yasak_tarihi;
        if (!empty($yasak_bitis_tarihi) && strtotime($yasak_bitis_tarihi) >= $bugun_timestamp) {
            $yasak_var = true;
        }
    }
    $stmt_ogr->close();
}

// 3. OKUMA GEÇMİŞİNİ VE CEZA BİLGİLERİNİ ÇEK/HESAPLA
$sql_kitaplar = "SELECT k.kitap_adi, k.yazar, k.sayfa_sayisi, i.odunc_tarihi, i.son_iade_tarihi, i.iade_tarihi
        FROM islemler AS i
        JOIN kitaplar AS k ON i.kitap_id = k.id
        WHERE i.ogrenci_id = ? AND i.okul_id = ?
        ORDER BY i.odunc_tarihi DESC";

if ($stmt = $mysqli->prepare($sql_kitaplar)) {
    $stmt->bind_param("ii", $student_id, $okul_id);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($kitap_adi, $yazar, $sayfa_sayisi, $odunc_tarihi, $son_iade_tarihi, $iade_tarihi);

    while ($stmt->fetch()) {
        $toplam_okunan_kitap++;
        if ($iade_tarihi !== NULL) {
            $toplam_okunan_sayfa += (int)$sayfa_sayisi;
        }
        
        $gecikme_gun = 0;
        $gecikme_ceza = 0.00;

        if ($iade_tarihi === NULL && $son_iade_tarihi && strtotime($son_iade_tarihi) < $bugun_timestamp) {
            $fark_gun = floor(($bugun_timestamp - strtotime($son_iade_tarihi)) / (60 * 60 * 24));
            $gecikme_gun = $fark_gun;

            if ($ceza_tipi_para_aktif && $gunluk_ceza > 0.00) {
                $gecikme_ceza = $fark_gun * $gunluk_ceza;
                $toplam_ceza_miktari += $gecikme_ceza;
            }
        }
        
        if ($iade_tarihi === NULL) {
             $kitaplar[] = [
                'kitap_adi' => $kitap_adi, 'yazar' => $yazar, 'odunc_tarihi' => $odunc_tarihi, 
                'son_iade_tarihi' => $son_iade_tarihi, 'gecikme_gun' => $gecikme_gun, 'gecikme_ceza' => $gecikme_ceza
             ];
        }
    }
    $stmt->close();
}

// 4. KATALOG İÇİN VERİ ÇEKME
$sql_katalog = "SELECT k.id, k.kitap_adi, k.yazar, k.raftaki_adet FROM kitaplar k WHERE k.okul_id = ?";
$params_katalog = [$okul_id];
$types_katalog = "i";

// Sayfalama ve Arama
$sayfa = isset($_GET['sayfa']) ? (int)$_GET['sayfa'] : 1;
if ($sayfa < 1) $sayfa = 1;
$kayit_per_sayfa = 10;
$offset = ($sayfa - 1) * $kayit_per_sayfa;
$arama_terimi = isset($_GET['ara']) ? trim($_GET['ara']) : '';

if(!empty($arama_terimi)){
    $sql_katalog .= " AND (k.kitap_adi LIKE ? OR k.yazar LIKE ? OR k.isbn LIKE ?)";
    $arama_wildcard = "%{$arama_terimi}%";
    $params_katalog = array_merge($params_katalog, [$arama_wildcard, $arama_wildcard, $arama_wildcard]);
    $types_katalog .= "sss";
}

// Toplam Başlık Sayısı
$sql_count_baslik = "SELECT COUNT(k.id) FROM kitaplar k WHERE k.okul_id = ?";
if (!empty($arama_terimi)) { $sql_count_baslik .= " AND (k.kitap_adi LIKE ? OR k.yazar LIKE ? OR k.isbn LIKE ?)"; }
$stmt_count_baslik = $mysqli->prepare($sql_count_baslik);
if (!empty($arama_terimi)) { $stmt_count_baslik->bind_param("isss", $okul_id, $arama_wildcard, $arama_wildcard, $arama_wildcard); }
else { $stmt_count_baslik->bind_param("i", $okul_id); }
$stmt_count_baslik->execute();
$stmt_count_baslik->bind_result($toplam_kayit_baslik);
$stmt_count_baslik->fetch();
$toplam_sayfa = ceil($toplam_kayit_baslik / $kayit_per_sayfa);
$stmt_count_baslik->close();

$sql_list = $sql_katalog . " ORDER BY k.kitap_adi LIMIT ? OFFSET ?";
$params_katalog[] = $kayit_per_sayfa; $types_katalog .= "i";
$params_katalog[] = $offset;          $types_katalog .= "i";
?>

<h1>Merhaba, <?php echo htmlspecialchars($_SESSION["student_ad_soyad"]); ?>!</h1>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; margin-bottom: 30px;">
    
    <div class="form-wrapper">
        <h3>Okuma Karnesi Detayı</h3>
        <p><strong>Toplam Okunan Kitap:</strong> <?php echo $toplam_okunan_kitap; ?> Adet</p>
        <p><strong>Toplam Okunan Sayfa:</strong> <?php echo $toplam_okunan_sayfa; ?> Sayfa</p>
        <p><strong>Ortalama Kitap Sayfası:</strong> <?php echo ($toplam_okunan_kitap > 0 && $toplam_okunan_sayfa > 0) ? round($toplam_okunan_sayfa / $toplam_okunan_kitap) : 0; ?> Sayfa</p>
    </div>

    <div class="form-wrapper">
        <h3>Ceza Durumu</h3>
        <?php if ($toplam_ceza_miktari > 0.00 && $ceza_tipi_para_aktif): ?>
            <p style="font-weight: bold; color: var(--danger);"><i class="fas fa-exclamation-triangle fa-fw"></i> Gecikme Cezası: <?php echo number_format($toplam_ceza_miktari, 2, ',', '.') . ' TL'; ?></p>
        <?php else: ?>
            <p style="color: var(--success);">Aktif Para Cezası Bulunmamaktadır.</p>
        <?php endif; ?>
        
        <?php if ($yasak_var && $ceza_tipi_yasak_aktif): ?>
            <p style="font-weight: bold; color: var(--danger);"><i class="fas fa-lock fa-fw"></i> YASAK: Yeni Kitap Alma Yasağınız Var.</p>
            <p><strong>Yasak Bitiş Tarihi:</strong> <span style="color: var(--danger);"><?php echo date("d.m.Y", strtotime($yasak_bitis_tarihi)); ?></span></p>
        <?php else: ?>
            <p style="color: var(--success);">Kitap Alma Yasağınız Bulunmamaktadır.</p>
        <?php endif; ?>
    </div>
</div>

<div class="form-wrapper" style="margin-top: 30px;">
    <h3>Üzerinizdeki Kitaplar (<?php echo count($kitaplar); ?> adet)</h3>
    <?php if (!empty($kitaplar)): ?>
        <table>
            <thead> <tr> <th>Kitap Adı</th><th>Yazar</th><th>Ödünç Aldığınız Tarih</th><th>Son İade Tarihi</th><th>Gecikme (Gün)</th> <?php if($ceza_tipi_para_aktif): ?><th>Ceza (TL)</th><?php endif; ?> </tr> </thead>
            <tbody>
                <?php foreach ($kitaplar as $kitap): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($kitap['kitap_adi']); ?></td>
                        <td><?php echo htmlspecialchars($kitap['yazar']); ?></td>
                        <td><?php echo date("d.m.Y", strtotime($kitap['odunc_tarihi'])); ?></td>
                        <td>
                            <?php 
                            $iade_tarihi_ts = strtotime($kitap['son_iade_tarihi']);
                            $durum_class = ($kitap['gecikme_gun'] > 0) ? 'durum-gecikti' : 'durum-odunc';
                            ?>
                            <span class="durum <?php echo $durum_class; ?>"><?php echo date("d.m.Y", $iade_tarihi_ts); ?></span>
                        </td>
                        <td><?php if ($kitap['gecikme_gun'] > 0): ?><span style="color: var(--danger); font-weight: bold;"><?php echo $kitap['gecikme_gun']; ?> gün</span><?php else: ?>-<?php endif; ?></td>
                        <?php if($ceza_tipi_para_aktif): ?>
                            <td><?php if ($kitap['gecikme_ceza'] > 0): ?><span style="color: var(--danger); font-weight: bold;"><?php echo number_format($kitap['gecikme_ceza'], 2, ',', '.'); ?> TL</span><?php else: ?>-<?php endif; ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="text-align: center; padding: 20px;">Şu anda üzerinizde ödünç alınmış bir kitap bulunmamaktadır.</p>
    <?php endif; ?>
    
    <div style="margin-top: 20px; text-align: right;">
        <a href="ogrenci_cikis.php" class="button-link button-secondary">Çıkış Yap</a>
    </div>
</div>


<div class="form-wrapper" style="margin-top: 30px;">
    <h3>Kütüphane Kataloğu</h3>
    <form action="ogrenci_panel.php" method="get">
        <div class="form-group" style="margin-top: 0; margin-bottom: 20px;">
            <input type="text" id="liveSearchInput" name="ara" placeholder="Kitap Adı, Yazar veya ISBN ile ara..." value="<?php echo htmlspecialchars($arama_terimi); ?>">
        </div>
    </form>
    
    <table>
        <thead><tr><th>Kitap Adı</th><th>Yazar</th><th>Durum</th></tr></thead>
        <tbody>
        <?php 
        $stmt_list = $mysqli->prepare($sql_list);
        $stmt_list->bind_param($types_katalog, ...$params_katalog);
        $stmt_list->execute();
        $stmt_list->store_result();
        $stmt_list->bind_result($col_id, $col_kitap_adi, $col_yazar, $col_raftaki_adet);
        
        if ($stmt_list->num_rows > 0) {
            while($stmt_list->fetch()): ?>
                <tr>
                    <td><a href="kitap_detay.php?id=<?php echo $col_id; ?>"><?php echo htmlspecialchars($col_kitap_adi); ?></a></td>
                    <td><?php echo htmlspecialchars($col_yazar); ?></td>
                    <td>
                        <?php if ($col_raftaki_adet > 0): ?>
                             <span class="durum durum-rafta">Rafta</span>
                        <?php else: ?>
                             <span class="durum durum-odunc">Ödünçte</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile;
        } else {
             echo '<tr><td colspan="3">Kayıt bulunamadı.</td></tr>';
        }
        $stmt_list->close();
        ?>
        </tbody>
    </table>

    <nav id="paginationNav" style="margin-top: 10px;">
        <ul class="pagination">
            <li class="<?php if($sayfa <= 1){ echo 'disabled'; } ?>"> <a href="?sayfa=<?php echo $sayfa - 1; ?>&ara=<?php echo urlencode($arama_terimi); ?>">Önceki</a> </li>
            <?php for($i = 1; $i <= $toplam_sayfa; $i++): ?> <li class="<?php if($sayfa == $i) { echo 'active'; } ?>"> <a href="?sayfa=<?php echo $i; ?>&ara=<?php echo urlencode($arama_terimi); ?>"><?php echo $i; ?></a> </li> <?php endfor; ?>
            <li class="<?php if($sayfa >= $toplam_sayfa){ echo 'disabled'; } ?>"> <a href="?sayfa=<?php echo $sayfa + 1; ?>&ara=<?php echo urlencode($arama_terimi); ?>">Sonraki</a> </li>
        </ul>
    </nav>
</div>

<?php
if(isset($mysqli) && !$mysqli->connect_errno) { $mysqli->close(); }
require_once 'footer.php';
?>