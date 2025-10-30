<?php
require_once 'header.php';
// Güvenlik: Giriş yapılmamışsa veya okul ID'si yoksa login sayfasına yönlendir
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["okul_id"])){
    header("location: login.php");
    exit;
}
if (!isset($mysqli) || $mysqli->connect_errno) { require "config.php"; }

$okul_id = $_SESSION["okul_id"];
?>

<div class="form-wrapper">
    <h3>Kütüphane Genel Durumu (Grafikler)</h3>
    <p>Aşağıdaki grafikler, kütüphanenizdeki genel eğilimleri ve istatistikleri göstermektedir.</p>
    <div class="charts-container" style="margin-top:20px;">
        <div class="chart-wrapper">
            <h4>En Çok Okunan 10 Kitap</h4>
            <canvas id="enCokOkunanKitaplarChart"></canvas>
        </div>
        <div class="chart-wrapper">
            <h4>En Çok Kitap Okuyan 10 Öğrenci</h4>
            <canvas id="enCokOkuyanOgrencilerChart"></canvas>
        </div>
        <div class="chart-wrapper">
            <h4>Aylara Göre Kitap Okunma Sayıları (Son 1 Yıl)</h4>
            <canvas id="aylaraGoreOkunmaChart"></canvas>
        </div>
        <div class="chart-wrapper">
            <h4>Kitap Türü Dağılımı</h4>
            <canvas id="kitapTuruDagilimiChart"></canvas>
        </div>
    </div>
</div>

<hr style="margin: 40px 0;">

<div class="form-wrapper">
    <h3>Detaylı PDF Rapor Oluştur</h3>
    <p>Belirli kriterlere göre detaylı raporlar oluşturup PDF olarak indirin.</p>
    <form action="rapor_olustur.php" method="post" target="_blank" style="margin-top:20px;">
        <div class="form-group">
            <label for="raporTipiSelect">Rapor Tipi:</label>
            <select name="rapor_tipi" id="raporTipiSelect" required>
                <option value="">Lütfen bir rapor tipi seçin...</option>
                <option value="okuma_karnesi">Öğrenci Okuma Karnesi</option>
                <option value="tum_kitaplar">Tüm Kitapların Listesi</option>
                <option value="tum_ogrenciler">Tüm Öğrencilerin Listesi</option>
                <option value="geciken_kitaplar">Geciken Kitaplar Listesi</option>
                <option value="verilen_kitaplar">Şu An Dışarıda Olan Kitaplar</option>
            </select>
        </div>

        <div id="ogrenciSecimAlani" class="form-group" style="display: none;">
            <label for="ogrenciIdInput">Öğrenci Seçin:</label>
            <input type="text" id="ogrenciAramaInput" placeholder="Öğrenci adı veya numarası ile arayın...">
            <select name="ogrenci_id" id="ogrenciIdInput" required>
                <option value="">Arama yaparak öğrenci seçin...</option>
            </select>
        </div>
        
        <div id="tarihAraligiAlani" class="form-group" style="display: none;">
            <label>Tarih Aralığı Seçin (İsteğe Bağlı):</label>
            <div style="display: flex; gap: 15px;">
                <input type="date" name="baslangic_tarihi" title="Başlangıç Tarihi">
                <input type="date" name="bitis_tarihi" title="Bitiş Tarihi">
            </div>
        </div>

        <input type="submit" value="Raporu Oluştur ve İndir">
    </form>
</div>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // --- GRAFİK RAPORLAR İÇİN GEREKLİ KODLAR ---
    const ctxKitaplar = document.getElementById('enCokOkunanKitaplarChart').getContext('2d');
    const ctxOgrenciler = document.getElementById('enCokOkuyanOgrencilerChart').getContext('2d');
    const ctxAylaraGore = document.getElementById('aylaraGoreOkunmaChart').getContext('2d');
    const ctxTurler = document.getElementById('kitapTuruDagilimiChart').getContext('2d');

    fetch('veri_raporlar.php')
        .then(response => response.json())
        .then(data => {
            // Renk paleti
            const backgroundColors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796', '#5a5c69', '#f8f9fc', '#dddfeb', '#d1d3e2'];
            
            new Chart(ctxKitaplar, {
                type: 'bar',
                data: { labels: data.enCokOkunanKitaplar.etiketler, datasets: [{ label: 'Okunma Sayısı', data: data.enCokOkunanKitaplar.veriler, backgroundColor: backgroundColors }] },
                options: { responsive: true, plugins: { legend: { display: false } } }
            });

            new Chart(ctxOgrenciler, {
                type: 'bar',
                data: { labels: data.enCokOkuyanOgrenciler.etiketler, datasets: [{ label: 'Okuduğu Kitap Sayısı', data: data.enCokOkuyanOgrenciler.veriler, backgroundColor: backgroundColors }] },
                options: { responsive: true, indexAxis: 'y', plugins: { legend: { display: false } } }
            });

            new Chart(ctxAylaraGore, {
                type: 'line',
                data: { labels: data.aylaraGoreOkunma.etiketler, datasets: [{ label: 'Okunan Kitap Sayısı', data: data.aylaraGoreOkunma.veriler, borderColor: '#4e73df', tension: 0.1 }] },
                options: { responsive: true }
            });

            new Chart(ctxTurler, {
                type: 'pie',
                data: { labels: data.kitapTuruDagilimi.etiketler, datasets: [{ data: data.kitapTuruDagilimi.veriler, backgroundColor: backgroundColors }] },
                options: { responsive: true }
            });
        })
        .catch(error => console.error('Grafik verileri alınırken hata oluştu:', error));

    // --- PDF RAPOR FORMU İÇİN GEREKLİ KODLAR ---
    const raporTipiSelect = document.getElementById('raporTipiSelect');
    const ogrenciSecimAlani = document.getElementById('ogrenciSecimAlani');
    const tarihAraligiAlani = document.getElementById('tarihAraligiAlani');
    const ogrenciAramaInput = document.getElementById('ogrenciAramaInput');
    const ogrenciIdSelect = document.getElementById('ogrenciIdInput');

    raporTipiSelect.addEventListener('change', function() {
        const secilenTip = this.value;
        ogrenciSecimAlani.style.display = (secilenTip === 'okuma_karnesi') ? 'block' : 'none';
        tarihAraligiAlani.style.display = (secilenTip === 'geciken_kitaplar' || secilenTip === 'verilen_kitaplar') ? 'none' : 'block';
    });
    
    let debounceTimer;
    ogrenciAramaInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const aramaTerimi = this.value;
        if (aramaTerimi.length < 2) { return; }
        
        debounceTimer = setTimeout(() => {
            fetch(`arama_ogrenci.php?term=${encodeURIComponent(aramaTerimi)}`)
                .then(response => response.json())
                .then(data => {
                    ogrenciIdSelect.innerHTML = '<option value="">Arama sonucu seçin...</option>';
                    if (data.length > 0) {
                        data.forEach(ogrenci => {
                            const option = document.createElement('option');
                            option.value = ogrenci.id;
                            option.textContent = `${ogrenci.ad_soyad} (${ogrenci.okul_no})`;
                            ogrenciIdSelect.appendChild(option);
                        });
                    } else {
                        ogrenciIdSelect.innerHTML = '<option value="">Öğrenci bulunamadı.</option>';
                    }
                });
        }, 300);
    });
});
</script>

<?php 
require_once 'footer.php'; 
?>