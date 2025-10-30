<?php
require_once 'header.php';
// Güvenlik: Giriş yapılmamışsa veya okul ID'si yoksa login sayfasına yönlendir
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["okul_id"])){
    header("location: login.php");
    exit;
}

// Sayfa ilk yüklendiğinde filtrelerin URL'den okunması için değişkenler
$filt_ogrenci_id = isset($_GET['ogrenci_id']) ? (int)$_GET['ogrenci_id'] : 0;
$filt_kitap_id = isset($_GET['kitap_id']) ? (int)$_GET['kitap_id'] : 0;
$filt_bas_tarih = isset($_GET['bas_tarih']) ? $_GET['bas_tarih'] : '';
$filt_bit_tarih = isset($_GET['bit_tarih']) ? $_GET['bit_tarih'] : '';
?>

<div class="form-wrapper">
    <h3>Geçmişi Filtrele</h3>
    <form id="filterForm">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
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
            <div><label for="bas_tarih">Başlangıç Tarihi:</label><input type="date" name="bas_tarih" id="bas_tarih"></div>
            <div><label for="bit_tarih">Bitiş Tarihi:</label><input type="date" name="bit_tarih" id="bit_tarih"></div>
        </div>
        <div style="display: flex; gap: 10px;">
            <button type="submit" style="flex: 1;">Filtrele</button>
            <button type="button" id="clearFilterBtn" class="button-link button-secondary" style="flex: 1;">Filtreyi Temizle</button>
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
    const clearFilterBtn = document.getElementById('clearFilterBtn');
    const tableBody = document.getElementById('gecmisTableBody');
    const paginationNav = document.getElementById('paginationNav');
    const kayitSayisiBaslik = document.getElementById('kayitSayisiBaslik');

    function performSearch(page = 1) {
        tableBody.style.opacity = '0.5';
        const formData = new FormData(filterForm);
        const params = new URLSearchParams(formData);
        params.append('sayfa', page);
        
        const queryString = params.toString();
        
        fetch(`arama_gecmis.php?${queryString}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    tableBody.innerHTML = data.html_tablo;
                    paginationNav.innerHTML = data.html_sayfalama;
                    kayitSayisiBaslik.textContent = `Tüm İşlemler (${data.toplam_kayit} Kayıt Bulundu)`;
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

    filterForm.addEventListener('submit', e => {
        e.preventDefault();
        performSearch(1);
    });

    clearFilterBtn.addEventListener('click', () => {
        filterForm.reset();
        document.getElementById('ogrenci_id').value = '0';
        document.getElementById('kitap_id').value = '0';
        performSearch(1);
    });

    performSearch(1);

    // Canlı arama kutularını başlat
    setupAutocomplete("ogrenci_arama", "ogrenci_sonuclari", "ogrenci_id", "arama_ogrenci.php", null);
    setupAutocomplete("kitap_arama", "kitap_sonuclari", "kitap_id", "arama_kitap_tumu.php", null);
});

// --- YENİ EKLENEN BÖLÜM ---
// Canlı arama (autocomplete) fonksiyonu
function setupAutocomplete(inputId, resultsId, hiddenId, sourceUrl, onSelectCallback) {
    const input = document.getElementById(inputId);
    const resultsContainer = document.getElementById(resultsId);
    const hiddenInput = document.getElementById(hiddenId);
    let fetchTimeout;
    
    input.addEventListener("input", function() {
        let val = this.value;
        closeAllLists();
        hiddenInput.value = "0"; // Arama yaparken seçimi sıfırla
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
                        // Arama terimini kalınlaştır
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

    // Dışarıya tıklandığında listeleri kapat
    document.addEventListener("click", (e) => {
        if (!e.target.closest('.autocomplete-wrapper')) {
            closeAllLists();
        }
    });
}
// --- YENİ EKLENEN BÖLÜM SONU ---
</script>

<?php require_once 'footer.php'; ?>