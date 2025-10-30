<?php
require_once 'header.php';
// Güvenlik: Giriş yapılmamışsa veya okul ID'si yoksa login sayfasına yönlendir
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["okul_id"])){
    header("location: login.php");
    exit;
}
if (!isset($mysqli) || $mysqli->connect_errno) { require "config.php"; }

// YENİ ÖĞRENCİ EKLEME (POST İŞLEMİ)
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['yeni_ogrenci_ekle'])){
    $okul_id = $_SESSION["okul_id"];
    $ogrenci_no = trim($_POST["ogrenci_no"]);
    $ad = trim($_POST["ad"]);
    $soyad = trim($_POST["soyad"]);
    $sinif = trim($_POST["sinif"]);
    if(!empty($ogrenci_no) && !empty($ad) && !empty($soyad)){
        $sql_insert = "INSERT INTO ogrenciler (okul_id, ogrenci_no, ad, soyad, sinif) VALUES (?, ?, ?, ?, ?)";
        if($stmt = $mysqli->prepare($sql_insert)){
            $stmt->bind_param("issss", $okul_id, $ogrenci_no, $ad, $soyad, $sinif);
            if($stmt->execute()){
                $_SESSION['toast_message'] = ['success' => true, 'message' => 'Yeni öğrenci başarıyla eklendi.'];
            } else {
                $_SESSION['toast_message'] = ['success' => false, 'message' => 'HATA: Öğrenci eklenemedi (Öğrenci No daha önce kaydedilmiş olabilir).'];
            }
            $stmt->close();
            header("Location: ogrenciler.php");
            exit;
        }
    }
}
?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-bottom: 20px;">
    <div class="form-wrapper">
        <h3>Yeni Öğrenci Ekle</h3>
        <form action="ogrenciler.php" method="post">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group"> <label>Öğrenci No (*)</label> <input type="text" name="ogrenci_no" required> </div>
                <div class="form-group"> <label>Sınıf</label> <input type="text" name="sinif" placeholder="Örn: 9/A"> </div>
                <div class="form-group"> <label>Ad (*)</label> <input type="text" name="ad" required> </div>
                <div class="form-group"> <label>Soyad (*)</label> <input type="text" name="soyad" required> </div>
            </div>
            <input type="submit" name="yeni_ogrenci_ekle" value="Öğrenciyi Ekle">
        </form>
    </div>
    <div class="form-wrapper">
        <h3>Toplu Veri İşlemleri</h3>
        <p>e-Okul listelerini veya standart CSV dosyalarını kullanarak toplu öğrenci ekleme/güncelleme yapabilirsiniz.</p>
        <div style="display: flex; gap: 10px; margin-top: 15px;">
            <a href="import.php?type=ogrenci" class="button-secondary" style="flex: 1; text-align: center;"><i class="fa-solid fa-file-import"></i> e-Okul Listesi Aktar</a>
            <a href="export.php?type=ogrenciler" class="button-secondary" style="flex: 1; text-align: center;"><i class="fa-solid fa-file-export"></i> Listeyi Dışarı Aktar (.csv)</a>
        </div>
    </div>
</div>

<div class="form-group" style="margin-top: 30px; margin-bottom: 20px;">
    <label for="liveSearchInput" style="font-size: 1.2em; color: var(--text-primary); font-weight: 600;">Kayıtlı Öğrencilerde Ara</label>
    <input type="text" id="liveSearchInput" placeholder="No, ad, soyad veya sınıfa göre ara...">
</div>

<h3 id="kayitliOgrencilerBaslik">Tüm Öğrenciler</h3>
<div id="topluIslemWrapper" style="display: none; margin-bottom: 15px; display: flex; flex-wrap: wrap; gap: 15px; align-items: center;">
    <button id="topluSilBtn" class="button-link button-danger"><i class="fa-solid fa-user-slash"></i> Seçilen <span id="seciliSayisi">0</span> Öğrenciyi Sil</button>
    <button id="topluKaliciSilBtn" class="button-link" style="color: var(--danger); font-weight: bold; border: 1px solid var(--danger); padding: 5px 10px; border-radius: 5px;"><i class="fa-solid fa-triangle-exclamation"></i> Seçilenleri Geçmişiyle Sil</button>
</div>

<table class="table-layout-fixed table-ogrenciler">
    <thead>
        <tr>
            <th style="width: 5%; text-align: center;"><input type="checkbox" id="selectAllCheckbox"></th>
            <th class="th-no">Öğrenci No</th>
            <th class="th-sinif">Sınıf</th>
            <th class="th-ad-soyad">Ad Soyad</th>
            <th class="th-islemler">İşlemler</th>
        </tr>
    </thead>
    <tbody id="ogrencilerTableBody">
        <tr><td colspan="5" style="text-align: center; padding: 2rem;">Yükleniyor...</td></tr>
    </tbody>
</table>
<nav id="paginationNav"></nav>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('liveSearchInput');
    const tableBody = document.getElementById('ogrencilerTableBody');
    const paginationNav = document.getElementById('paginationNav');
    const tableTitle = document.getElementById('kayitliOgrencilerBaslik');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const topluIslemWrapper = document.getElementById('topluIslemWrapper');
    const topluSilBtn = document.getElementById('topluSilBtn');
    const seciliSayisiSpan = document.getElementById('seciliSayisi');
    
    function performLiveSearch(searchTerm, page = 1) {
        tableBody.style.opacity = '0.5';
        const url = `arama_yonetim_ogrenci.php?ara=${encodeURIComponent(searchTerm)}&sayfa=${page}`;
        fetch(url)
            .then(response => {
                if (!response.ok) { throw new Error('Sunucu hatası oluştu: ' + response.status); }
                return response.json();
            })
            .then(data => {
                if (data.error) { throw new Error(data.error); }
                tableBody.innerHTML = data.html_tablo;
                paginationNav.innerHTML = data.html_sayfalama;
                tableTitle.textContent = `Tüm Öğrenciler (Toplam: ${data.toplam_kayit} Kayıt)`;
                tableBody.style.opacity = '1';
                addEventListenersToTable();
                updateTopluIslemUI();
            })
            .catch(error => {
                console.error('Canlı arama hatası:', error);
                tableBody.innerHTML = '<tr><td colspan="5" style="color: var(--danger); text-align:center;">Arama sırasında bir hata oluştu. Lütfen tarayıcı konsolunu kontrol edin.</td></tr>';
                tableBody.style.opacity = '1';
            });
    }

    function addEventListenersToTable() {
        document.querySelectorAll('.delete-btn').forEach(button => {
            button.addEventListener('click', function() {
                const studentId = this.dataset.id;
                const studentName = this.dataset.name;
                if (confirm(`'${studentName}' adlı öğrenciyi silmek istediğinize emin misiniz?`)) {
                    const formData = new FormData();
                    formData.append('id', studentId);
                    fetch('ogrenci_sil.php', { method: 'POST', body: formData })
                        .then(res => res.json()).then(data => {
                            showToast(data.message, data.success ? 'success' : 'error');
                            if (data.success) { performLiveSearch(searchInput.value, 1); }
                        });
                }
            });
        });
        document.querySelectorAll('.student-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateTopluIslemUI);
        });
    }

    function updateTopluIslemUI() {
        const seciliCheckboxlar = document.querySelectorAll('.student-checkbox:checked');
        const sayi = seciliCheckboxlar.length;
        topluIslemWrapper.style.display = sayi > 0 ? 'flex' : 'none';
        seciliSayisiSpan.textContent = sayi;
        const tumCheckboxlar = document.querySelectorAll('.student-checkbox');
        selectAllCheckbox.checked = tumCheckboxlar.length > 0 && sayi === tumCheckboxlar.length;
    }

    selectAllCheckbox.addEventListener('change', function() {
        document.querySelectorAll('.student-checkbox').forEach(checkbox => { checkbox.checked = this.checked; });
        updateTopluIslemUI();
    });

    topluSilBtn.addEventListener('click', function() {
        const seciliIdler = Array.from(document.querySelectorAll('.student-checkbox:checked')).map(cb => cb.dataset.id);
        if (seciliIdler.length === 0) { return; }
        if (confirm(`${seciliIdler.length} öğrenciyi silmek istediğinize emin misiniz? (Not: İşlem geçmişi olanlar silinmeyecektir.)`)) {
            const formData = new FormData();
            formData.append('idler', JSON.stringify(seciliIdler));
            fetch('ogrenci_toplu_sil.php', { method: 'POST', body: formData })
                .then(res => res.json()).then(data => {
                    showToast(data.message, data.success ? 'success' : 'error');
                    if(data.success) { performLiveSearch(searchInput.value, 1); }
                });
        }
    });
    
    const topluKaliciSilBtn = document.getElementById('topluKaliciSilBtn');
    topluKaliciSilBtn.addEventListener('click', function() {
        const seciliIdler = Array.from(document.querySelectorAll('.student-checkbox:checked')).map(cb => cb.dataset.id);
        if (seciliIdler.length === 0) { return; }
        const confirmationText = "SİL";
        const userConfirmation = prompt(`DİKKAT!\n\n${seciliIdler.length} öğrenciyi ve bu öğrencilere ait TÜM KİTAP OKUMA GEÇMİŞİNİ kalıcı olarak silmek üzeresiniz. Bu işlem GERİ ALINAMAZ.\n\nDevam etmek için "${confirmationText}" yazarak onaylayın.`);
        if (userConfirmation === confirmationText) {
            const formData = new FormData();
            formData.append('idler', JSON.stringify(seciliIdler));
            fetch('ogrenci_kalici_sil.php', { method: 'POST', body: formData })
                .then(res => res.json()).then(data => {
                    showToast(data.message, data.success ? 'success' : 'error');
                    if(data.success) { performLiveSearch(searchInput.value, 1); }
                });
        } else if (userConfirmation !== null) {
            showToast('Onay metni yanlış girildiği için işlem iptal edildi.', 'error');
        }
    });

    searchInput.addEventListener('input', debounce(() => { performLiveSearch(searchInput.value, 1); }, 300));
    paginationNav.addEventListener('click', (e) => {
        if (e.target.tagName === 'A' && e.target.dataset.page) {
            e.preventDefault();
            performLiveSearch(searchInput.value, e.target.dataset.page);
        }
    });

    performLiveSearch('', 1);
});

function debounce(func, delay = 300) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => { func.apply(this, args); }, delay);
    };
}
</script>

<?php require_once 'footer.php'; ?>