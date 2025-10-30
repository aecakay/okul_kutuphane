<?php
require_once 'header.php';
// Güvenlik: Giriş yapılmamışsa veya okul ID'si yoksa login sayfasına yönlendir
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["okul_id"])) {
    header("location: login.php");
    exit;
}
?>

<div class="rapor-grid-container">
    
    <div class="form-wrapper chart-card">
        <h3>Son 12 Aydaki Kitap Ödünç Verme Sayısı</h3>
        <div class="chart-box"><canvas id="aylikIslemlerChart"></canvas></div>
    </div>

    <div class="form-wrapper chart-card">
        <h3>En Çok Okunan 10 Kitap</h3>
        <div class="chart-box"><canvas id="enCokOkunanKitaplarChart"></canvas></div>
    </div>
    
    <div class="form-wrapper chart-card">
        <h3>En Çok Kitap Alan 10 Öğrenci</h3>
        <div class="chart-box"><canvas id="enCokOkuyanOgrencilerChart"></canvas></div>
    </div>
    
    <div class="form-wrapper chart-card">
        <h3>En Popüler 10 Yazar</h3>
        <div class="chart-box"><canvas id="enPopulerYazarlarChart"></canvas></div>
    </div>

</div>

<style>
    .rapor-grid-container {
        display: grid;
        /* DEĞİŞİKLİK BURADA: minmax değeri 450px'ten 400px'e düşürüldü */
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); 
        gap: 20px;
        margin-top: 20px;
    }
    
    .chart-card {
        padding: 1.2rem !important;
        margin: 0 !important;
        height: 100%;
        box-sizing: border-box;
    }
    
    .chart-card h3 {
        font-size: 1.2em;
        margin-bottom: 10px !important;
        padding-bottom: 0 !important;
    }

    .chart-box {
        position: relative;
        /* DEĞİŞİKLİK BURADA: Yükseklik 300px'ten 270px'e düşürüldü */
        height: 270px; 
        width: 100%;
    }
</style>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Tema renklerine göre grafik seçeneklerini ayarla
    const isLightTheme = document.body.classList.contains('light-theme');
    const gridColor = isLightTheme ? 'rgba(0, 0, 0, 0.1)' : 'rgba(255, 255, 255, 0.1)';
    const labelColor = isLightTheme ? '#212529' : '#dee2e6';
    
    // Her bir grafik için tekrar eden seçenekleri tanımla
    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false, // Konteyner yüksekliğine uyması için
        plugins: {
            legend: { 
                labels: { color: labelColor }
            } 
        },
        scales: {
             x: { 
                 ticks: { color: labelColor, stepSize: 1 }, 
                 grid: { color: gridColor },
                 beginAtZero: true 
             },
             y: { 
                 ticks: { color: labelColor, stepSize: 1 }, 
                 grid: { color: gridColor },
                 beginAtZero: true 
             }
        }
    };

    // Verileri AJAX ile çek
    fetch('veri_raporlar.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Ağ yanıtı sorunlu: ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            if(data.error) {
                console.error("Rapor verileri alınamadı:", data.error);
                showToast("Rapor verileri yüklenemedi.", "error");
                return;
            }

            // 1. Aylık İşlemler Grafiği (Çizgi Grafiği)
            new Chart(document.getElementById('aylikIslemlerChart'), {
                type: 'line',
                data: {
                    labels: data.aylikIslemler.labels,
                    datasets: [{
                        label: 'Ödünç Verilen Kitap Sayısı',
                        data: data.aylikIslemler.data,
                        backgroundColor: 'rgba(13, 110, 253, 0.2)',
                        borderColor: 'rgba(13, 110, 253, 1)',
                        borderWidth: 2, fill: true, tension: 0.4
                    }]
                },
                options: { ...commonOptions, plugins: { legend: { labels: { color: labelColor } } } }
            });

            // 2. En Çok Okunan Kitaplar Grafiği (Yatay Çubuk)
            new Chart(document.getElementById('enCokOkunanKitaplarChart'), {
                type: 'bar',
                data: {
                    labels: data.enCokOkunanKitaplar.labels,
                    datasets: [{
                        label: 'Okunma Sayısı',
                        data: data.enCokOkunanKitaplar.data,
                        backgroundColor: 'rgba(25, 135, 84, 0.7)',
                        borderColor: 'rgba(25, 135, 84, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    ...commonOptions,
                    indexAxis: 'y', // Yatay eksen
                    plugins: { legend: { display: false } },
                    scales: { ...commonOptions.scales, y: { ticks: { color: labelColor }, grid: { display: false } } }
                }
            });
            
            // 3. En Çok Okuyan Öğrenciler Grafiği (Dikey Çubuk)
            new Chart(document.getElementById('enCokOkuyanOgrencilerChart'), {
                type: 'bar',
                data: {
                    labels: data.enCokOkuyanOgrenciler.labels,
                    datasets: [{
                        label: 'Okuduğu Kitap Sayısı',
                        data: data.enCokOkuyanOgrenciler.data,
                        backgroundColor: 'rgba(255, 193, 7, 0.7)',
                        borderColor: 'rgba(255, 193, 7, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                     ...commonOptions,
                     plugins: { legend: { display: false } },
                     scales: { ...commonOptions.scales, x: { ticks: { color: labelColor }, grid: { display: false } } }
                }
            });
            
            // 4. En Popüler Yazarlar Grafiği (Yatay Çubuk)
            new Chart(document.getElementById('enPopulerYazarlarChart'), {
                type: 'bar',
                data: {
                    labels: data.enPopulerYazarlar.labels,
                    datasets: [{
                        label: 'Okunma Sayısı',
                        data: data.enPopulerYazarlar.data,
                        backgroundColor: 'rgba(220, 53, 69, 0.7)',
                        borderColor: 'rgba(220, 53, 69, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    ...commonOptions,
                    indexAxis: 'y', // Yatay eksen
                    plugins: { legend: { display: false } },
                    scales: { ...commonOptions.scales, y: { ticks: { color: labelColor }, grid: { display: false } } }
                }
            });

        })
        .catch(error => {
            console.error('Raporlar yüklenirken bir hata oluştu:', error);
            showToast("Raporlar yüklenirken bir hata oluştu.", "error");
        });
});
</script>

<?php require_once 'footer.php'; ?>