<?php
require_once 'header.php';
// Güvenlik: Giriş yapılmamışsa veya okul ID'si yoksa login sayfasına yönlendir
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["okul_id"])){
    header("location: login.php");
    exit;
}
if (!isset($mysqli) || $mysqli->connect_errno) { require "config.php"; }

$okul_id = $_SESSION["okul_id"];

// YENİ KİTAP EKLEME (POST İŞLEMİ)
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['yeni_kitap_ekle'])){
    $kitap_adi_form = trim($_POST["kitap_adi"]);
    $yazar_form = trim($_POST["yazar"]);
    $isbn_form = trim($_POST["isbn"]);
    $basim_yili_form = trim($_POST["basim_yili"]);
    $sayfa_sayisi_form = !empty($_POST["sayfa_sayisi"]) ? (int)$_POST["sayfa_sayisi"] : null;
    $kapak_url_form = trim($_POST["kapak_url"]);
    $toplam_adet_form = isset($_POST["toplam_adet"]) && (int)$_POST["toplam_adet"] > 0 ? (int)$_POST["toplam_adet"] : 1;
    $raftaki_adet_form = $toplam_adet_form;

    if(!empty($kitap_adi_form)){
        $sql_insert = "INSERT INTO kitaplar (okul_id, kitap_adi, yazar, isbn, basim_yili, sayfa_sayisi, kapak_url, toplam_adet, raftaki_adet) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        if($stmt = $mysqli->prepare($sql_insert)){
            $stmt->bind_param("issssisii", $okul_id, $kitap_adi_form, $yazar_form, $isbn_form, $basim_yili_form, $sayfa_sayisi_form, $kapak_url_form, $toplam_adet_form, $raftaki_adet_form);
            if($stmt->execute()){
                $_SESSION['toast_message'] = ['success' => true, 'message' => 'Yeni kitap başarıyla eklendi.'];
            } else {
                $_SESSION['toast_message'] = ['success' => false, 'message' => 'HATA: Kitap eklenemedi (ISBN daha önce kaydedilmiş olabilir).'];
            }
            $stmt->close();
            header("Location: kitaplar.php");
            exit;
        }
    }
}
?>

<div class="form-wrapper">
    <h3>Yeni Kitap Ekle</h3>
    <form action="kitaplar.php" method="post" id="yeniKitapForm">
        <div style="display: grid; grid-template-columns: 1fr 170px; gap: 20px;">
            <div style="grid-column: 1 / 2;">
                <div class="form-group"> <label for="isbnInput">ISBN (Bilgileri Otomatik Getir)</label> <input type="text" name="isbn" id="isbnInput"> <small id="fetchStatus" style="display: block; color: var(--text-secondary); margin-top: 5px; min-height: 1.2em;"></small> </div>
                <div class="form-group"> <label for="kitapAdiInput">Kitap Adı (*)</label> <input type="text" name="kitap_adi" id="kitapAdiInput" required> </div>
                <div class="form-group"> <label for="yazarInput">Yazar</label> <input type="text" name="yazar" id="yazarInput"> </div>
                <div class="form-group">
                     <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px;">
                        <div> <label for="basimYiliInput">Basım Yılı</label> <input type="text" name="basim_yili" id="basimYiliInput"> </div>
                        <div> <label for="sayfaSayisiInput">Sayfa Sayısı</label> <input type="number" name="sayfa_sayisi" id="sayfaSayisiInput" min="0"> </div>
                        <div> <label for="toplamAdetInput">Adet</label> <input type="number" name="toplam_adet" id="toplamAdetInput" min="1" value="1" required> </div>
                    </div>
                </div>
            </div>
            <div style="grid-column: 2 / 3;" class="form-col-right">
                <label>Kapak Fotoğrafı</label>
                <div class="cover-preview-wrapper" style="width: 170px; height: 210px; background-color: var(--bg-main); border: 1px solid var(--border-color); border-radius: 6px; display: flex; align-items: center; justify-content: center; color: var(--text-secondary); font-size: 0.9em; overflow: hidden; position: relative; margin: 0 auto;"> <span id="coverPlaceholder" class="placeholder">Kapak</span> <img id="coverPreview" src="" alt="Kitap Kapağı" style="display: none; width: 100%; height: 100%; object-fit: contain;"> </div>
                <input type="hidden" name="kapak_url" id="kapakUrlInput">
            </div>
        </div>
        <div class="form-group" style="margin-top: 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
            <button type="button" id="fetchInfoBtn" class="button button-secondary" style="width: 100%;">ISBN ile Bilgileri Getir</button>
            <input type="submit" name="yeni_kitap_ekle" value="Kitabı Kütüphaneye Ekle" style="width: 100%;">
        </div>
    </form>
</div>

<div class="form-wrapper" style="margin-top: 20px;">
    <h3>Toplu Veri İşlemleri</h3>
    <p>Standart CSV dosyalarını kullanarak toplu kitap ekleme/güncelleme yapabilir veya mevcut kitap listenizi dışarı aktarabilirsiniz.</p>
    <div style="display: flex; gap: 10px; margin-top: 15px;">
        <a href="import.php?type=kitap" class="button button-primary" style="flex: 1; text-align: center;">
            <i class="fa-solid fa-file-import"></i> Kitap Listesi Aktar (.csv)
        </a>
        <a href="export.php?type=kitaplar" class="button button-primary" style="flex: 1; text-align: center;">
            <i class="fa-solid fa-file-export"></i> Listeyi Dışarı Aktar (.csv)
        </a>
    </div>
</div>

<div class="form-group" style="margin-top: 30px; margin-bottom: 20px;">
    <label for="liveSearchInput" style="font-size: 1.2em; color: var(--text-primary); font-weight: 600;">Kayıtlı Kitaplarda Ara</label>
    <input type="text" name="ara" id="liveSearchInput" placeholder="Ad, yazar veya ISBN'e göre ara...">
</div>

<h3 id="kayitliKitaplarBaslik">Kayıtlı Kitaplar</h3>
<div id="topluIslemWrapper" style="display: none; margin-bottom: 15px;">
    <button id="topluSilBtn" class="button-link button-danger"><i class="fa-solid fa-trash-can"></i> Seçilen <span id="seciliSayisi">0</span> Kitabı Sil</button>
</div>

<table class="table-layout-fixed table-kitaplar">
    <thead>
        <tr>
            <th style="width: 5%; text-align: center;"><input type="checkbox" id="selectAllCheckbox"></th>
            <th class="th-kitap-adi">Kitap Adı</th>
            <th class="th-yazar">Yazar</th>
            <th class="th-isbn">ISBN</th>
            <th class="th-stok">Stok (Rafta/Toplam)</th>
            <th class="th-islemler">İşlemler</th>
        </tr>
    </thead>
    <tbody id="kitaplarTableBody">
        <tr><td colspan="6" style="text-align: center; padding: 2rem;">Yükleniyor...</td></tr>
    </tbody>
</table>
<nav id="paginationNav"></nav>

<script>
// ISBN Bilgi Getirme Fonksiyonu
function fetchBookInfo() {
    const isbnInput = document.getElementById('isbnInput'), titleInput = document.getElementById('kitapAdiInput'), authorInput = document.getElementById('yazarInput'), yearInput = document.getElementById('basimYiliInput'), pageCountInput = document.getElementById('sayfaSayisiInput'), coverPreview = document.getElementById('coverPreview'), coverPlaceholder = document.getElementById('coverPlaceholder'), coverUrlInput = document.getElementById('kapakUrlInput'), statusDiv = document.getElementById('fetchStatus'), fetchBtn = document.getElementById('fetchInfoBtn');
    const isbn = isbnInput.value.trim();
    if (isbn === "") { statusDiv.textContent = "Lütfen bir ISBN numarası girin."; statusDiv.style.color = "var(--danger)"; return; }
    fetchBtn.disabled = true; fetchBtn.textContent = "Getiriliyor..."; statusDiv.textContent = "Google Books API'den bilgiler alınıyor..."; statusDiv.style.color = "var(--text-secondary)";
    fetch(`fetch_book_info.php?isbn=${encodeURIComponent(isbn)}`).then(res => res.json()).then(data => {
        if (data.error) { throw new Error(data.error); }
        if (!data.items || data.items.length === 0) { throw new Error("Bu ISBN ile eşleşen kitap bulunamadı."); }
        const volumeInfo = data.items[0].volumeInfo;
        titleInput.value = volumeInfo.title || '';
        authorInput.value = volumeInfo.authors ? volumeInfo.authors.join(', ') : '';
        pageCountInput.value = volumeInfo.pageCount || '';
        const imageUrl = volumeInfo.imageLinks ? (volumeInfo.imageLinks.thumbnail || volumeInfo.imageLinks.smallThumbnail || "") : "";
        let publishedYear = "";
        if (volumeInfo.publishedDate && volumeInfo.publishedDate.match(/^\d{4}/)) { publishedYear = volumeInfo.publishedDate.match(/^\d{4}/)[0]; }
        yearInput.value = publishedYear;
        if (imageUrl) {
            coverPreview.src = imageUrl.replace("http://", "https://");
            coverPreview.style.display = "block";
            coverPlaceholder.style.display = "none";
            coverUrlInput.value = imageUrl.replace("http://", "https://");
        } else {
            coverPreview.src = ""; coverPreview.style.display = "none"; coverPlaceholder.style.display = "flex"; coverUrlInput.value = "";
        }
        statusDiv.textContent = "Bilgiler başarıyla getirildi!"; statusDiv.style.color = "var(--success)";
    }).catch(err => {
        statusDiv.textContent = `Hata: ${err.message}`; statusDiv.style.color = "var(--danger)";
        coverPreview.src = ""; coverPreview.style.display = "none"; coverPlaceholder.style.display = "flex"; coverUrlInput.value = "";
    }).finally(() => {
        fetchBtn.disabled = false; fetchBtn.textContent = "ISBN ile Bilgileri Getir";
    });
}


document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('fetchInfoBtn').onclick = fetchBookInfo;
    
    const searchInput = document.getElementById('liveSearchInput');
    const tableBody = document.getElementById('kitaplarTableBody');
    const paginationNav = document.getElementById('paginationNav');
    const tableTitle = document.getElementById('kayitliKitaplarBaslik');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const topluIslemWrapper = document.getElementById('topluIslemWrapper');
    const topluSilBtn = document.getElementById('topluSilBtn');
    const seciliSayisiSpan = document.getElementById('seciliSayisi');

    function performLiveSearch(searchTerm, page = 1) {
        tableBody.style.opacity = '0.5';
        const url = `arama_yonetim_kitap.php?ara=${encodeURIComponent(searchTerm)}&sayfa=${page}`;
        fetch(url).then(response => response.json()).then(data => {
            if (data.error) { throw new Error(data.error); }
            tableBody.innerHTML = data.html_tablo;
            paginationNav.innerHTML = data.html_sayfalama;
            tableTitle.textContent = `Kayıtlı Kitaplar (Toplam: ${data.toplam_kayit_fiziksel} Kitap)`;
            tableBody.style.opacity = '1';
            addEventListenersToTable();
            updateTopluIslemUI();
        }).catch(error => {
            console.error('Canlı arama hatası:', error);
            tableBody.innerHTML = '<tr><td colspan="6" style="color: var(--danger);">Arama sırasında bir hata oluştu.</td></tr>';
            tableBody.style.opacity = '1';
        });
    }

    function addEventListenersToTable() {
        // Tekli silme
        document.querySelectorAll('.delete-btn').forEach(button => {
            button.addEventListener('click', function() {
                const bookId = this.dataset.id;
                const bookName = this.dataset.name;
                if (confirm(`'${bookName}' adlı kitabı silmek istediğinize emin misiniz?`)) {
                    const formData = new FormData();
                    formData.append('id', bookId);
                    fetch('kitap_sil.php', { method: 'POST', body: formData })
                        .then(res => res.json())
                        .then(data => {
                            showToast(data.message, data.success ? 'success' : 'error');
                            if (data.success) {
                                performLiveSearch(searchInput.value, 1);
                            }
                        });
                }
            });
        });

        // Checkbox'lar
        document.querySelectorAll('.book-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateTopluIslemUI);
        });
    }

    function updateTopluIslemUI() {
        const seciliCheckboxlar = document.querySelectorAll('.book-checkbox:checked');
        const sayi = seciliCheckboxlar.length;
        if (sayi > 0) {
            topluIslemWrapper.style.display = 'block';
            seciliSayisiSpan.textContent = sayi;
        } else {
            topluIslemWrapper.style.display = 'none';
        }
        const tumCheckboxlar = document.querySelectorAll('.book-checkbox');
        selectAllCheckbox.checked = tumCheckboxlar.length > 0 && sayi === tumCheckboxlar.length;
    }

    selectAllCheckbox.addEventListener('change', function() {
        document.querySelectorAll('.book-checkbox').forEach(checkbox => { checkbox.checked = this.checked; });
        updateTopluIslemUI();
    });
    
    topluSilBtn.addEventListener('click', function() {
        const seciliCheckboxlar = document.querySelectorAll('.book-checkbox:checked');
        const seciliIdler = Array.from(seciliCheckboxlar).map(cb => cb.dataset.id);
        if (seciliIdler.length === 0) { return; }
        if (confirm(`${seciliIdler.length} kitabı silmek istediğinize emin misiniz?`)) {
            const formData = new FormData();
            formData.append('idler', JSON.stringify(seciliIdler));
            fetch('kitap_toplu_sil.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    showToast(data.message, data.success ? 'success' : 'error');
                    if(data.success) {
                        performLiveSearch(searchInput.value, 1);
                    }
                });
        }
    });

    searchInput.addEventListener('input', debounce((e) => { performLiveSearch(e.target.value, 1); }, 300));
    paginationNav.addEventListener('click', (e) => {
        if (e.target.tagName === 'A' && !e.target.parentElement.classList.contains('disabled')) {
            e.preventDefault();
            const page = e.target.dataset.page;
            performLiveSearch(searchInput.value, page);
        }
    });

    performLiveSearch(searchInput.value, 1);
});
function debounce(func, delay = 300) { let timeout; return function(...args) { clearTimeout(timeout); timeout = setTimeout(() => { func.apply(this, args); }, delay); }; }
</script>

<?php require_once 'footer.php'; ?>