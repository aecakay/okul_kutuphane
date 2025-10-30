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

// 2. ÖĞRENCİ BİLGİLERİ, OKUL NO, SINIF VE YASAK KONTROLÜNÜ ÇEK
$ogrenci_no = '';
$sinif = '';
$sql_ogrenci = "SELECT ogrenci_no, sinif, kitap_almasi_yasak_tarih FROM ogrenciler WHERE id = ? AND okul_id = ?";
if($stmt_ogr = $mysqli->prepare($sql_ogrenci)){
    $stmt_ogr->bind_param("ii", $student_id, $okul_id);
    $stmt_ogr->execute();
    $stmt_ogr->store_result();
    $stmt_ogr->bind_result($ogrenci_no, $sinif, $db_yasak_tarihi);
    if ($stmt_ogr->fetch()) {
        $yasak_bitis_tarihi = $db_yasak_tarihi;
        if (!empty($yasak_bitis_tarihi) && strtotime($yasak_bitis_tarihi) >= $bugun_timestamp) {
            $yasak_var = true;
        }
    }
    $stmt_ogr->close();
}

// 3. ÜZERİNDEKİ KİTAPLARI VE CEZA BİLGİLERİNİ ÇEK/HESAPLA
$sql_kitaplar = "SELECT k.kitap_adi, k.yazar, k.sayfa_sayisi, i.odunc_tarihi, i.son_iade_tarihi, i.iade_tarihi
        FROM islemler AS i
        JOIN kitaplar AS k ON i.kitap_id = k.id
        WHERE i.ogrenci_id = ? AND i.okul_id = ?
        ORDER BY i.odunc_tarihi DESC";

$uzerimdeki_kitaplar = [];
if ($stmt = $mysqli->prepare($sql_kitaplar)) {
    $stmt->bind_param("ii", $student_id, $okul_id);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($kitap_adi, $yazar, $sayfa_sayisi, $odunc_tarihi, $son_iade_tarihi, $iade_tarihi);

    while ($stmt->fetch()) {
        
        // Okuma Karnesi Hesaplaması
        if ($iade_tarihi !== NULL) { 
            $toplam_okunan_kitap++;
            $toplam_okunan_sayfa += $sayfa_sayisi;
        }
        
        $gecikme_gun = 0;
        $gecikme_ceza = 0.00;

        // Sadece ödünçte olanlar için ceza hesapla
        if ($iade_tarihi === NULL && $son_iade_tarihi && strtotime($son_iade_tarihi) < $bugun_timestamp) {
            $fark_gun = floor(($bugun_timestamp - strtotime($son_iade_tarihi)) / (60 * 60 * 24));
            $gecikme_gun = $fark_gun;

            if ($ceza_tipi_para_aktif && $gunluk_ceza > 0.00) {
                $gecikme_ceza = $fark_gun * $gunluk_ceza;
                $toplam_ceza_miktari += $gecikme_ceza;
            }
        }
        
        // Ödünçte olanları listeye ekle
        if ($iade_tarihi === NULL) {
             $uzerimdeki_kitaplar[] = [
                'kitap_adi' => $kitap_adi, 'yazar' => $yazar, 'odunc_tarihi' => $odunc_tarihi, 
                'son_iade_tarihi' => $son_iade_tarihi, 'gecikme_gun' => $gecikme_gun, 'gecikme_ceza' => $gecikme_ceza
             ];
        }
    }
    $stmt->close();
}
// Ortalama hesaplama
$ortalama_sayfa = ($toplam_okunan_kitap > 0) ? round($toplam_okunan_sayfa / $toplam_okunan_kitap) : 0;

// Sınıf formatını düzeltme (11/A -> 11A)
$sade_sinif = str_replace('/', '', $sinif);
?>

<h1 class="daraltilmis-baslik">Merhaba, <?php echo htmlspecialchars($_SESSION["student_ad_soyad"]); ?> (<?php echo htmlspecialchars($ogrenci_no); ?> / <?php echo htmlspecialchars($sade_sinif); ?>)</h1>

<div id="stats-wrapper" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; margin-bottom: 15px !important;">
    
    <div class="form-wrapper custom-form-padding">
        <h3><i class="fas fa-book-reader fa-fw"></i> Okuma İstatistikleri</h3>
        <div style="display: flex; justify-content: space-between; align-items: center; text-align: center; margin-top: 15px;">
            
            <div style="flex: 1; border-right: 1px solid var(--border-color);">
                <p style="font-size: 2em; font-weight: bold; color: var(--text-primary); margin: 0;"><?php echo $toplam_okunan_kitap; ?></p>
                <p style="font-size: 0.8em; color: var(--text-secondary); margin: 0;">Toplam Kitap</p>
            </div>
            
            <div style="flex: 1;">
                <p style="font-size: 2em; font-weight: bold; color: var(--text-primary); margin: 0;"><?php echo $toplam_okunan_sayfa; ?></p>
                <p style="font-size: 0.8em; color: var(--text-secondary); margin: 0;">Toplam Sayfa</p>
            </div>
            
            <div style="flex: 1; border-left: 1px solid var(--border-color);">
                <p style="font-size: 2em; font-weight: bold; color: var(--text-primary); margin: 0;"><?php echo $ortalama_sayfa; ?></p>
                <p style="font-size: 0.8em; color: var(--text-secondary); margin: 0;">Ort. Sayfa</p>
            </div>
            
        </div>
    </div>
    <div class="form-wrapper custom-form-padding">
        <h3><i class="fas fa-gavel fa-fw"></i> Ceza Durumu</h3>
        <div style="margin-top: 10px;">
            <?php 
            $ceza_miktari_renk = $toplam_ceza_miktari > 0.00 ? 'var(--danger)' : 'var(--success)';
            $yasak_durumu_renk = $yasak_var ? 'var(--danger)' : 'var(--success)';
            ?>

            <div style="margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px dashed var(--border-color-light);">
                <span style="font-weight: bold; color: var(--text-secondary); display: block; margin-bottom: 3px;"><i class="fa-solid fa-turkish-lira-sign fa-fw"></i> Para Cezası:</span>
                <span style="font-weight: bold; color: <?php echo $ceza_miktari_renk; ?>; font-size: 1.2em;">
                    <?php echo $toplam_ceza_miktari > 0.00 ? number_format($toplam_ceza_miktari, 2, ',', '.') . ' TL' : 'Bulunmamaktadır'; ?>
                </span>
            </div>

            <div>
                <span style="font-weight: bold; color: var(--text-secondary); display: block; margin-bottom: 3px;"><i class="fas fa-lock fa-fw"></i> Kitap Alma Yasağı:</span>
                <span style="font-weight: bold; color: <?php echo $yasak_durumu_renk; ?>; font-size: 1.2em;">
                    <?php echo $yasak_var ? date("d.m.Y", strtotime($yasak_bitis_tarihi)) . ' tarihine kadar' : 'Yok'; ?>
                </span>
            </div>
        </div>
    </div>
    </div>

<div id="uzerimdeki-kitaplar-bolumu" class="form-wrapper custom-form-padding" style="margin-top: 15px !important;">
    <h3>Üzerinizdeki Kitaplar (<?php echo count($uzerimdeki_kitaplar); ?> adet)</h3>
    
    <?php if (!empty($uzerimdeki_kitaplar)): ?>
        <div class="kitap-listesi-container">
            <?php foreach ($uzerimdeki_kitaplar as $kitap): ?>
                <div class="kitap-kart">
                    <div class="kitap-baslik">
                        <?php echo htmlspecialchars($kitap['kitap_adi']); ?>
                        <span class="kitap-yazar"> / <?php echo htmlspecialchars($kitap['yazar']); ?></span>
                    </div>

                    <div class="kitap-bilgileri-grid">
                        <div class="kitap-bilgi-item">
                            <span class="bilgi-etiket"><i class="fas fa-calendar-alt fa-fw"></i> Alış Tarihi:</span>
                            <span class="bilgi-deger"><?php echo date("d.m.Y", strtotime($kitap['odunc_tarihi'])); ?></span>
                        </div>
                        <div class="kitap-bilgi-item">
                            <span class="bilgi-etiket"><i class="fas fa-calendar-check fa-fw"></i> Son İade Tarihi:</span>
                            <?php 
                                $iade_tarihi_ts = strtotime($kitap['son_iade_tarihi']);
                                $durum_class = ($kitap['gecikme_gun'] > 0) ? 'durum-gecikti' : '';
                            ?>
                            <span class="bilgi-deger <?php echo $durum_class; ?>"><?php echo date("d.m.Y", $iade_tarihi_ts); ?></span>
                        </div>
                        <?php if ($kitap['gecikme_gun'] > 0): ?>
                            <div class="kitap-bilgi-item gecikme-bilgisi">
                                <span class="bilgi-etiket"><i class="fas fa-clock fa-fw"></i> Gecikme:</span>
                                <span class="bilgi-deger gecikme-sayisi"><?php echo $kitap['gecikme_gun']; ?> gün</span>
                            </div>
                        <?php endif; ?>
                        <?php if($ceza_tipi_para_aktif && $kitap['gecikme_ceza'] > 0.00): ?>
                            <div class="kitap-bilgi-item ceza-bilgisi">
                                <span class="bilgi-etiket"><i class="fas fa-money-bill-wave fa-fw"></i> Ceza:</span>
                                <span class="bilgi-deger ceza-miktari"><?php echo number_format($kitap['gecikme_ceza'], 2, ',', '.') . ' TL'; ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p style="text-align: center; padding: 20px;">Şu anda üzerinizde ödünç alınmış bir kitap bulunmamaktadır.</p>
    <?php endif; ?>
    
    <div style="margin-top: 20px; text-align: right;">
        <a href="ogrenci_cikis.php" class="button-link button-secondary">Çıkış Yap</a>
    </div>
</div>


<div id="katalog-bolumu" class="form-wrapper custom-form-padding" style="margin-top: 30px;">
    <h3>Kütüphane Kataloğu</h3>
    <form action="ogrenci_panel.php" method="get" id="katalogAramaForm">
        <div class="form-group" style="margin-top: 0; margin-bottom: 20px;">
            <input type="text" id="katalogSearchInput" name="ara" placeholder="Kitap Adı, Yazar veya ISBN ile ara..." value="">
        </div>
    </form>
    
    <div id="katalog-listesi-container" class="katalog-listesi-container">
        <div id="katalogTableBody" class="katalog-listesi-ul">
            <p style="text-align: center; padding: 20px;">Yükleniyor...</p>
        </div>
    </div>

    <nav id="katalogPaginationNav" style="margin-top: 10px;"></nav>
</div>


<style>
/* Başlık Daraltma */
.daraltilmis-baslik {
    font-size: 1.8em !important; 
    margin-top: 0 !important;
    margin-bottom: 15px !important;
    padding-bottom: 0 !important;
    border-bottom: none !important; 
}

/* Genel Daraltma */
.form-wrapper.custom-form-padding {
    padding: 1.2rem !important; 
}
.form-wrapper h3 {
    margin-top: 0 !important;
    margin-bottom: 10px !important;
}
.form-wrapper {
    margin-top: 15px; 
}

/* Kütüphane Kataloğu Listesi (YENİ TASARIM) */
.katalog-listesi-container {
    margin-top: 15px;
}
.katalog-listesi-ul {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0px; 
}
.katalog-list-item {
    padding: 4px 15px !important; /* Dikey boşluk çok daraltıldı */
    border-bottom: 1px solid var(--border-color); /* Kalın çizgi */
    display: flex;
    justify-content: space-between;
    align-items: center;
    text-decoration: none;
    color: inherit;
    transition: background-color 0.2s;
}
.katalog-list-item:last-child {
    border-bottom: none;
}
.katalog-list-item:hover {
    background-color: var(--background-secondary);
}

.katalog-list-item .kitap-bilgi {
    display: flex;
    align-items: baseline; 
    flex-grow: 1;
    overflow: hidden;
}
.katalog-list-item .kitap-baslik {
    font-weight: bold;
    font-size: 1em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis; 
}
.katalog-list-item .kitap-yazar {
    font-size: 0.85em;
    color: var(--text-secondary);
    margin-left: 5px;
    white-space: nowrap;
    flex-shrink: 0; 
}
.katalog-list-item .kitap-durum {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.75em;
    font-weight: 600;
    white-space: nowrap;
    flex-shrink: 0; 
    margin-left: 15px;
}


/* YENİ TASARIM CSS'İ: Üzerinizdeki Kitaplar (Kartlar) */
.kitap-listesi-container {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin-top: 15px;
}
.kitap-kart {
    padding: 15px;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    background-color: var(--background-secondary); 
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}
.kitap-baslik {
    font-size: 1.1em;
    font-weight: bold;
    color: var(--text-primary);
    margin-bottom: 10px;
    border-bottom: 1px dashed var(--border-color-light); 
    padding-bottom: 5px;
}
.kitap-yazar {
    font-weight: normal;
    font-size: 0.9em;
    color: var(--text-secondary);
}
.kitap-bilgileri-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px;
    font-size: 0.9em;
}
.bilgi-etiket {
    font-weight: bold;
    color: var(--text-secondary);
    display: block;
    margin-bottom: 2px;
}
.bilgi-deger {
    font-weight: 500;
    color: var(--text-primary);
}
/* Durum renkleri */
.durum-rafta { background-color: var(--success-bg); color: var(--success); }
.durum-odunc { background-color: var(--warning-bg); color: var(--warning); }
.durum-gecikti { color: var(--danger) !important; font-weight: bold; }
.gecikme-sayisi { color: var(--danger) !important; }
.ceza-miktari { color: var(--danger) !important; }
</style>

<script>
// Bu JavaScript kodu daha önce kurduğumuz AJAX mantığını içerir.
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('katalogSearchInput');
    const tableBody = document.getElementById('katalogTableBody');
    const paginationNav = document.getElementById('katalogPaginationNav');
    const katalogBolumu = document.getElementById('katalog-bolumu');
    const aramaForm = document.getElementById('katalogAramaForm');

    function fetchKatalog(searchTerm, page = 1) {
        tableBody.style.opacity = '0.5';
        const url = `arama_ogrenci_katalog.php?ara=${encodeURIComponent(searchTerm)}&sayfa=${page}`;
        
        fetch(url)
            .then(response => {
                if (!response.ok) { throw new Error('Sunucu hatası oluştu.'); }
                return response.json();
            })
            .then(data => {
                if (data.error) { throw new Error(data.error); }
                // Katalog artık liste yapısında olduğu için içeriği direkt div'e dolduruyoruz.
                tableBody.innerHTML = data.html_tablo; 
                paginationNav.innerHTML = data.html_sayfalama;
                tableBody.style.opacity = '1';
            })
            .catch(error => {
                console.error('Katalog arama hatası:', error);
                tableBody.innerHTML = `<p style="text-align: center; color: var(--danger); padding: 20px;">Katalog yüklenirken bir hata oluştu. Lütfen daha sonra tekrar deneyin.</p>`;
                tableBody.style.opacity = '1';
            });
    }

    aramaForm.addEventListener('submit', (e) => {
        e.preventDefault();
        fetchKatalog(searchInput.value, 1);
    });

    searchInput.addEventListener('input', debounce((e) => { fetchKatalog(e.target.value, 1); }, 350));

    paginationNav.addEventListener('click', (e) => {
        e.preventDefault();
        if (e.target.tagName === 'A' && e.target.dataset.page && !e.target.parentElement.classList.contains('disabled')) {
            const page = e.target.dataset.page;
            fetchKatalog(searchInput.value, page);
            katalogBolumu.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });

    // Sayfa yüklendiğinde AJAX ile kataloğu yükle
    fetchKatalog('', 1);
});

function debounce(func, delay = 300) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            func.apply(this, args);
        }, delay);
    };
}
</script>

<?php
if(isset($mysqli) && !$mysqli->connect_errno) { $mysqli->close(); }
require_once 'footer.php';
?>