<?php
require_once 'header.php';
// Güvenlik: Giriş yapılmamışsa veya okul ID'si yoksa login sayfasına yönlendir
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["okul_id"])){
    header("location: login.php");
    exit;
}
if (!isset($mysqli) || $mysqli->connect_errno) { require "config.php"; }
?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">

    <div class="form-wrapper">
        <h3><i class="fa-solid fa-arrow-right-from-bracket"></i> Kitap Ver</h3>
        <form id="oduncForm">
            <div class="form-group autocomplete-wrapper">
                <label for="ogrenci_arama">Kitap Alacak Öğrenci:</label>
                <input type="text" id="ogrenci_arama" autocomplete="off" required>
                <input type="hidden" name="selected_ogrenci_id" id="selected_ogrenci_id" value="0">
                <div class="autocomplete-items" id="ogrenci_sonuclari"></div>
            </div>
            <div class="form-group autocomplete-wrapper">
                <label for="kitap_arama">Ödünç Verilecek Kitap:</label>
                <input type="text" id="kitap_arama" autocomplete="off" required>
                <input type="hidden" name="selected_kitap_id" id="selected_kitap_id" value="0">
                <div class="autocomplete-items" id="kitap_sonuclari"></div>
            </div>
            <input type="submit" value="Ödünç Ver">
        </form>
    </div>

    <div class="form-wrapper">
         <h3><i class="fa-solid fa-arrow-right-to-bracket"></i> Kitap Al</h3>
         <form id="iadeForm">
             <div class="form-group autocomplete-wrapper">
                <label for="iade_ogrenci_arama">Kitap Verecek Öğrenci:</label>
                <input type="text" id="iade_ogrenci_arama" autocomplete="off" required>
                 <input type="hidden" name="selected_iade_ogrenci_id" id="selected_iade_ogrenci_id" value="0">
                 <div class="autocomplete-items" id="iade_sonuclari"></div>
             </div>
             <div class="form-group">
                 <label for="iade_kitap_secimi">İade Alınacak Kitaplar:</label>
                 <select name="selected_islem_id" id="iade_kitap_secimi" disabled required>
                     </select>
             </div>
             <input type="submit" id="iade_et_btn" value="İade Al" disabled>
         </form>
    </div>

</div>

<div class="form-wrapper" style="margin-top: 10px;">
    <h3 style="text-align: center;">Şu Anda Ödünçte Olan Kitaplar</h3>
    <div id="oduncListesiWrapper">
        <table class="table-odunc" id="oduncTable">
            </table>
        <div id="oduncPagination">
            </div>
    </div>
</div>

<script type="text/javascript">
document.addEventListener('DOMContentLoaded', () => {

    const oduncTable = document.getElementById('oduncTable');
    const oduncPagination = document.getElementById('oduncPagination');

    // =======================================================
    // 1. ÖDÜNÇ LİSTESİ YÖNETİMİ
    // =======================================================
    function loadOduncList(page = 1) {
        oduncTable.style.opacity = '0.5';
        fetch(`liste_odunc.php?sayfa_odunc=${page}`)
            .then(response => response.json())
            .then(data => {
                oduncTable.innerHTML = data.table_html;
                oduncPagination.innerHTML = data.pagination_html;
                oduncTable.style.opacity = '1';
                addEventListenersToList();
            })
            .catch(error => {
                console.error("Liste yüklenemedi:", error);
                oduncTable.innerHTML = '<tbody><tr><td colspan="6">Liste yüklenirken bir hata oluştu.</td></tr></tbody>';
                oduncTable.style.opacity = '1';
            });
    }
    
    function addEventListenersToList() {
        // Sayfalama linkleri
        oduncPagination.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', e => {
                e.preventDefault();
                if (!link.parentElement.classList.contains('disabled')) {
                    const page = link.dataset.page;
                    loadOduncList(page);
                }
            });
        });

        // HATA ÇÖZÜMÜ: Listeden iade alma butonları
        oduncTable.querySelectorAll('.list-iade-btn').forEach(button => {
            button.addEventListener('click', function() {
                const islemId = this.dataset.islemId;
                const kitapId = this.dataset.kitapId;
                // Doğrudan AJAX fonksiyonunu çağırıyoruz
                handleIade(islemId, kitapId, 'list');
            });
        });
    }

    loadOduncList(1);

    // =======================================================
    // 2. İŞLEM MANTIĞI (AJAX)
    // =======================================================
    function handleIade(islemId, kitapId, source = 'form') {
        const formData = new FormData();
        formData.append('islem_id', islemId);
        formData.append('kitap_id', kitapId);

        fetch('iade_al.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    // İade başarılı olursa listeyi yenile
                    loadOduncList();
                    // Formu temizle
                    if(source === 'form') {
                        document.getElementById('iadeForm').reset();
                        document.getElementById('iade_et_btn').disabled = true;
                        document.getElementById('iade_kitap_secimi').innerHTML = '';
                        document.getElementById('iade_kitap_secimi').disabled = true;
                    }
                }
            })
            .catch(error => console.error('İade hatası:', error));
    }

    document.getElementById('oduncForm').addEventListener('submit', function(e){
        e.preventDefault();
        const kitapId = document.getElementById('selected_kitap_id').value;
        const ogrenciId = document.getElementById('selected_ogrenci_id').value;
        if (kitapId === '0' || ogrenciId === '0') {
            showToast("Lütfen arama sonuçlarından geçerli bir kitap ve öğrenci seçin.", "error");
            return;
        }
        const formData = new FormData(this);
        fetch('odunc_ver.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                showToast(data.message, data.success ? 'success' : 'error');
                if(data.success) {
                    loadOduncList();
                    this.reset();
                    document.getElementById('selected_kitap_id').value = '0';
                    document.getElementById('selected_ogrenci_id').value = '0';
                }
            })
            .catch(error => console.error('Ödünç verme hatası:', error));
    });

    document.getElementById('iadeForm').addEventListener('submit', function(e){
        e.preventDefault();
        const secilenDeger = document.getElementById('iade_kitap_secimi').value;
        if (!secilenDeger) {
            showToast("Lütfen iade edilecek bir kitap seçin.", "error");
            return;
        }
        const [islemId, kitapId] = secilenDeger.split('-');
        handleIade(islemId, kitapId, 'form');
    });

    // =======================================================
    // 3. AUTOCOMPLETE FONKSİYONU
    // =======================================================
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

        function closeAllLists() { document.querySelectorAll(".autocomplete-items").forEach(el => { el.innerHTML = ""; el.style.display = 'none'; }); }
        document.addEventListener("click", (e) => { if (!e.target.closest('.autocomplete-wrapper')) closeAllLists(); });
    }

    // Autocomplete'leri Başlat
    // 1. Kitap Ver - Öğrenci: Tüm öğrencileri arar
    setupAutocomplete("ogrenci_arama", "ogrenci_sonuclari", "selected_ogrenci_id", "arama_ogrenci.php", null);
    // 2. Kitap Ver - Kitap: Tüm kitapları arar
    setupAutocomplete("kitap_arama", "kitap_sonuclari", "selected_kitap_id", "arama_kitap.php", null);
    
    // 3. Kitap Al - Öğrenci: SADECE ÜZERİNDE KİTAP OLAN ÖĞRENCİLERİ ARAR
    const iadeKitapSelect = document.getElementById('iade_kitap_secimi');
    const iadeBtn = document.getElementById('iade_et_btn');
    // DEĞİŞİKLİK BURADA YAPILDI: arama_ogrenci.php yerine arama_iade_ogrenci.php kullanılır
    setupAutocomplete("iade_ogrenci_arama", "iade_sonuclari", "selected_iade_ogrenci_id", "arama_iade_ogrenci.php", (ogrenci) => {
        iadeKitapSelect.innerHTML = '';
        iadeKitapSelect.disabled = true;
        iadeBtn.disabled = true;

        if (ogrenci && ogrenci.id > 0) {
            iadeKitapSelect.innerHTML = '<option value="">Kitaplar yükleniyor...</option>';
            fetch(`ogrenci_kitaplarini_getir.php?ogrenci_id=${ogrenci.id}`)
                .then(response => response.json())
                .then(kitaplar => {
                    iadeKitapSelect.innerHTML = '';
                    if (kitaplar.length > 0) {
                        if (kitaplar.length > 1) {
                            iadeKitapSelect.innerHTML = '<option value="">-- Kitap Seçin --</option>';
                        }
                        
                        kitaplar.forEach(kitap => {
                            const option = document.createElement('option');
                            option.value = `${kitap.islem_id}-${kitap.kitap_id}`;
                            option.textContent = kitap.kitap_adi;
                            iadeKitapSelect.appendChild(option);
                        });
                        iadeKitapSelect.disabled = false;

                        if (kitaplar.length === 1) {
                            iadeBtn.disabled = false;
                        }
                    } else {
                        iadeKitapSelect.innerHTML = '<option value="">Öğrencinin üzerinde kitap yok</option>';
                    }
                });
        }
    });

    iadeKitapSelect.addEventListener('change', function() {
        iadeBtn.disabled = this.value === '';
    });
});
</script>

<?php
if(isset($mysqli) && $mysqli instanceof mysqli && !$mysqli->connect_errno) {
    $mysqli->close();
}
require_once 'footer.php';
?>