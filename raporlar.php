<?php
require_once 'header.php';
// Güvenlik: Giriş yapılmamışsa veya okul ID'si yoksa login sayfasına yönlendir
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["okul_id"])) {
    header("location: login.php");
    exit;
}
?>

<h1>Kütüphane Raporları</h1>

<div style="display: grid; grid-template-columns: 1fr; gap: 40px;">
    
    <div class="form-wrapper">
        <h3>Son 12 Aydaki Kitap Ödünç Verme Sayısı</h3>
        <canvas id="aylikIslemlerChart"></canvas>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 30px;">
        <div class="form-wrapper">
            <h3>En Çok Okunan 10 Kitap</h3>
            <canvas id="enCokOkunanKitaplarChart"></canvas>
        </div>
        <div class="form-wrapper">
            <h3>En Çok Kitap Alan 10 Öğrenci</h3>
            <canvas id="enCokOkuyanOgrencilerChart"></canvas>
        </div>
    </div>
    
    <div class="form-wrapper">
        <h3>En Popüler 10 Yazar</h3>
        <canvas id="enPopulerYazarlarChart"></canvas>
    </div>

</div>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Tema renklerine göre grafik seçeneklerini ayarla
    const isLightTheme = document.body.classList.contains('light-theme');
    const gridColor = isLightTheme ? 'rgba(0, 0, 0, 0.1)' : 'rgba(255, 255, 255, 0.1)';
    const labelColor = isLightTheme ? '#212529' : '#dee2e6';

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
                options: {
                    scales: {
                        y: { beginAtZero: true, ticks: { color: labelColor, stepSize: 1 }, grid: { color: gridColor } },
                        x: { ticks: { color: labelColor }, grid: { color: gridColor } }
                    },
                    plugins: { legend: { labels: { color: labelColor } } }
                }
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
                    indexAxis: 'y',
                    scales: {
                         x: { beginAtZero: true, ticks: { color: labelColor, stepSize: 1 }, grid: { color: gridColor } },
                         y: { ticks: { color: labelColor }, grid: { display: false } }
                    },
                    plugins: { legend: { display: false } }
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
                     scales: {
                        y: { beginAtZero: true, ticks: { color: labelColor, stepSize: 1 }, grid: { color: gridColor } },
                        x: { ticks: { color: labelColor }, grid: { display: false } }
                    },
                    plugins: { legend: { display: false } }
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
                    indexAxis: 'y',
                    responsive: true,
                    scales: {
                         x: { beginAtZero: true, ticks: { color: labelColor, stepSize: 1 }, grid: { color: gridColor } },
                         y: { ticks: { color: labelColor }, grid: { display: false } }
                    },
                    plugins: { legend: { display: false } }
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