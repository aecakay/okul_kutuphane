<?php
require_once 'header.php';
// Güvenlik: Giriş yapılmamışsa veya okul ID'si yoksa login sayfasına yönlendir
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["okul_id"])){
    header("location: login.php");
    exit;
}

// Okulun sınıf listesini al (Sınıf bazlı raporlama için)
if (!isset($mysqli) || $mysqli->connect_errno) { require "config.php"; }
$okul_id = $_SESSION["okul_id"];
$siniflar = [];
$sql_siniflar = "SELECT DISTINCT sinif FROM ogrenciler WHERE okul_id = ? AND sinif IS NOT NULL AND sinif != '' ORDER BY sinif ASC";
if ($stmt_sinif = $mysqli->prepare($sql_siniflar)) {
    $stmt_sinif->bind_param("i", $okul_id);
    $stmt_sinif->execute();
    $stmt_sinif->bind_result($db_sinif);
    while($stmt_sinif->fetch()) {
        $siniflar[] = $db_sinif;
    }
    $stmt_sinif->close();
}
?>

<div class="form-wrapper">
    <h3>Detaylı Raporlama ve İşlem Geçmişi</h3>
    <form id="filterForm" action="rapor_olustur.php" method="post" target="_blank">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end; margin-bottom: 15px;">
            
            <div class="form-group autocomplete-wrapper">
                <label for="ogrenci_arama">Öğrenci Ara:</label>
                <input type="text" id="ogrenci_arama" autocomplete="off" placeholder="No, ad veya soyad">
                <input type="hidden" name="ogrenci_id" id="ogrenci_id" value="0">
                <div class="autocomplete-items" id="ogrenci_sonuclari"></div>
            </div>

            <div class="form-group autocomplete-wrapper">
                <label for="kitap_arama">Kitap Ara:</label>
                <input type="text" id="kitap_arama" autocomplete="off" placeholder="Ad, yazar veya ISBN">
                <input type="hidden" name="kitap_id" id="kitap_id" value="0">
                <div class="autocomplete-items" id="kitap_sonuclari"></div>
            </div>

            <div class="form-group">
                 <label for="sinif_select">Sınıf Seçimi:</label>
                 <select name="sinif" id="sinif_select">
                    <option value="">Tümü</option>
                    <?php foreach($siniflar as $sinif): ?>
                        <option value="<?php echo htmlspecialchars($sinif); ?>"><?php echo htmlspecialchars($sinif); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div><label for="bas_tarih">Başlangıç Tarihi:</label><input type="date" name="bas_tarih" id="bas_tarih"></div>
            <div><label for="bit_tarih">Bitiş Tarihi:</label><input type="date" name="bit_tarih" id="bit_tarih"></div>
        </div>

        <input type="hidden" name="rapor_tipi" id="rapor_tipi" value="genel">

        <div style="display: flex; gap: 10px; margin-top: 20px;">
            <button type="button" id="filterBtn" class="button-primary" style="flex: 2;">Filtrele</button>
            <button type="submit" id="pdfBtn" class="button-secondary" style="flex: 1;"><i class="fas fa-file-pdf fa-fw"></i> PDF Raporu Oluştur</button>
            <button type="button" id="clearFilterBtn" class="button-link" style="flex: 1;">Filtreyi Temizle</button>
        </div>
    </form>
</div>

<h3 id="kayitSayisiBaslik">Tüm İşlemler</h3>
<table id="gecmisTable">
    <thead>
        <tr><th>Öğrenci No</th><th>Öğrenci Ad Soyad</th><th>Kitap Adı</th><th>Ödünç Tarihi</th><th>Son İade Tarihi</th><th>Durum / İade Tarihi</th></tr>
    </thead>
    <tbody id="gecmisTableBody">
        <tr><td colspan="6" style="text-align:center; padding: 2rem;">Yükleniyor...</td></tr>
    </tbody>
</table>
<div id="paginationNav"></div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const filterForm = document.getElementById('filterForm');
    const filterBtn = document.getElementById('filterBtn');
    const clearFilterBtn = document.getElementById('clearFilterBtn');
    const pdfBtn = document.getElementById('pdfBtn');
    
    const tableBody = document.getElementById('gecmisTableBody');
    const paginationNav = document.getElementById('paginationNav');
    const kayitSayisiBaslik = document.getElementById('kayitSayisiBaslik');
    
    const ogrenciIdInput = document.getElementById('ogrenci_id');
    const sinifSelect = document.getElementById('sinif_select');
    const raporTipiInput = document.getElementById('rapor_tipi');

    function performSearch(page = 1) {
        tableBody.style.opacity = '0.5';
        const formData = new FormData(filterForm);
        const params = new URLSearchParams(formData);
        params.append('sayfa', page);
        
        // Sınıf filtresini AJAX isteğine de ekleyelim
        if (sinifSelect.value) {
            params.append('sinif', sinifSelect.value);
        }
        
        fetch(`arama_gecmis.php?${params.toString()}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    tableBody.innerHTML = data.html_tablo;
                    paginationNav.innerHTML = data.html_sayfalama;
                    kayitSayisiBaslik.textContent = `İşlem Geçmişi (${data.toplam_kayit} Kayıt Bulundu)`;
                    addPaginationListeners();
                } else {
                    showToast(data.message || 'Bir hata oluştu.', 'error');
                }
            })
            .catch(error => {
                console.error('Arama hatası:', error);
                showToast('Filtreleme sırasında bir ağ hatası oluştu.', 'error');
            })
            .finally(() => {
                tableBody.style.opacity = '1';
            });
    }

    function addPaginationListeners() {
        paginationNav.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', e => {
                e.preventDefault();
                if (!link.parentElement.classList.contains('disabled')) {
                    const page = link.dataset.page;
                    performSearch(page);
                }
            });
        });
    }

    // Filtrele butonu tıklandığında AJAX ile arama yap
    filterBtn.addEventListener('click', () => {
        performSearch(1);
    });

    // Filtreyi Temizle butonu
    clearFilterBtn.addEventListener('click', () => {
        filterForm.reset();
        document.getElementById('ogrenci_id').value = '0';
        document.getElementById('kitap_id').value = '0';
        performSearch(1);
    });

    // PDF butonuna basılmadan önce rapor tipini ayarla
    pdfBtn.addEventListener('click', () => {
        if (ogrenciIdInput.value && ogrenciIdInput.value !== '0') {
            raporTipiInput.value = 'ogrenci';
        } else if (sinifSelect.value) {
            raporTipiInput.value = 'sinif';
        } else {
            raporTipiInput.value = 'genel';
        }
    });

    // Sayfa ilk yüklendiğinde listeyi getir
    performSearch(1);

    // Canlı arama kutularını başlat
    setupAutocomplete("ogrenci_arama", "ogrenci_sonuclari", "ogrenci_id", "arama_ogrenci.php", (item) => {
        // Öğrenci seçildiğinde sınıf filtresini temizle
        if (item) {
            sinifSelect.value = '';
        }
    });
    setupAutocomplete("kitap_arama", "kitap_sonuclari", "kitap_id", "arama_kitap_tumu.php", null);

    // Sınıf seçildiğinde öğrenci filtresini temizle
    sinifSelect.addEventListener('change', () => {
        if (sinifSelect.value) {
            document.getElementById('ogrenci_arama').value = '';
            ogrenciIdInput.value = '0';
        }
    });
});

// Canlı arama (autocomplete) fonksiyonu
function setupAutocomplete(inputId, resultsId, hiddenId, sourceUrl, onSelectCallback) {
    const input = document.getElementById(inputId);
    const resultsContainer = document.getElementById(resultsId);
    const hiddenInput = document.getElementById(hiddenId);
    let fetchTimeout;
    
    input.addEventListener("input", function() {
        let val = this.value;
        closeAllLists();
        hiddenInput.value = "0"; 
        if (onSelectCallback) onSelectCallback(null);
        
        if (!val || val.length < 1) return false;

        clearTimeout(fetchTimeout);
        fetchTimeout = setTimeout(() => {
            fetch(`${sourceUrl}?term=${encodeURIComponent(val)}`)
                .then(res => res.json())
                .then(data => {
                    resultsContainer.innerHTML = "";
                    if (!data || data.length === 0 || data.error) return;
                    resultsContainer.style.display = 'block';

                    data.forEach(item => {
                        let div = document.createElement("DIV");
                        div.innerHTML = item.label.replace(new RegExp(val.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&'), "gi"), (match) => `<strong>${match}</strong>`);
                        div.addEventListener("click", function() {
                            input.value = item.value;
                            hiddenInput.value = item.id || item.islem_id || "0";
                            if (onSelectCallback) onSelectCallback(item);
                            closeAllLists();
                        });
                        resultsContainer.appendChild(div);
                    });
                })
                .catch(err => console.error(err));
        }, 300);
    });

    function closeAllLists() {
        document.querySelectorAll(".autocomplete-items").forEach(el => {
            el.innerHTML = "";
            el.style.display = 'none';
        });
    }

    document.addEventListener("click", (e) => {
        if (!e.target.closest('.autocomplete-wrapper')) {
            closeAllLists();
        }
    });
}
</script>

<?php require_once 'footer.php'; ?>