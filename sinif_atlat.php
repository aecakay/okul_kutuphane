<?php
require_once 'header.php';
// Güvenlik: Giriş yapılmamışsa veya okul ID'si yoksa login sayfasına yönlendir
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["okul_id"])){
    header("location: login.php");
    exit;
}
if (!isset($mysqli) || $mysqli->connect_errno) { require "config.php"; }

// Oturumdan mevcut okulun ID'sini al
$okul_id = $_SESSION["okul_id"];

// Mevcut sınıfları ve öğrenci sayılarını çek (sadece o okula ait)
// DÜZELTME: get_result() yerine uyumlu yöntem kullanıldı
$siniflar = [];
$sql_siniflar = "SELECT sinif, COUNT(id) AS ogrenci_sayisi 
                 FROM ogrenciler 
                 WHERE okul_id = ? AND sinif IS NOT NULL AND sinif != ''
                 GROUP BY sinif 
                 ORDER BY sinif ASC";
if($stmt = $mysqli->prepare($sql_siniflar)) {
    $stmt->bind_param("i", $okul_id);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($sinif_adi, $ogrenci_sayisi);
    while($stmt->fetch()) {
        $siniflar[] = [
            'sinif' => $sinif_adi,
            'ogrenci_sayisi' => $ogrenci_sayisi
        ];
    }
    $stmt->close();
}

// Mezuniyet sınıfını belirleme (Örn: 12. sınıf)
$mezuniyet_sinifi = 12; 

// Sınıf Atlatma Haritasını (Map) Oluştur
function get_sinif_map($siniflar) {
    global $mezuniyet_sinifi;
    $sinif_map = [];
    foreach ($siniflar as $sinif_data) {
        $sinif = $sinif_data['sinif'];
        
        if (preg_match('/^\D*(\d+)/', $sinif, $matches)) {
            $mevcut_sayi = (int)$matches[1];
            $harf_kismi = preg_replace('/^\D*\d+/', '', $sinif);

            if ($mevcut_sayi >= $mezuniyet_sinifi) {
                $yeni_sinif = "MEZUN OLUYOR";
            } else {
                $yeni_sinif = ($mevcut_sayi + 1) . $harf_kismi;
            }
            $sinif_map[$sinif] = $yeni_sinif;
        } else {
            $sinif_map[$sinif] = 'AYNISI KALSIN';
        }
    }
    return $sinif_map;
}

$sinif_map = get_sinif_map($siniflar);
?>

<h1>Toplu Sınıf Atlatma</h1>
<p>Bu işlem, mevcut öğrencileri otomatik olarak bir üst sınıfa taşır. Mezun olan öğrenciler (12. sınıf ve üstü) "MEZUN" olarak işaretlenecektir.</p>

<div class="form-wrapper">
    <h3>Sınıf Atlatma Onayı (Mevcut Durum)</h3>
    
    <div style="margin-bottom: 20px;">
        <p style="color: var(--danger); font-size: 0.9em; font-weight: bold;">
        DİKKAT: Bu işlem geri alınamaz! Lütfen ilerlemeden önce veri tabanınızın yedeğini aldığınızdan emin olun.
        </p>
    </div>
    
    <table style="width: 100%;">
        <thead>
            <tr>
                <th style="width: 30%;">Mevcut Sınıf</th>
                <th style="width: 20%; text-align: center;">Öğrenci Sayısı</th>
                <th style="width: 50%;">Hedef (Yeni) Sınıf</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($siniflar as $sinif_data): ?>
            <?php $sinif = $sinif_data['sinif']; ?>
            <tr>
                <td><?php echo htmlspecialchars($sinif); ?></td>
                <td style="text-align: center;"><?php echo $sinif_data['ogrenci_sayisi']; ?></td>
                <td>
                    <?php 
                        $yeni_sinif = $sinif_map[$sinif];
                        $style = '';
                        if($yeni_sinif == 'MEZUN OLUYOR') { $style = 'color: var(--danger); font-weight: bold;'; }
                        if($yeni_sinif == 'AYNISI KALSIN') { $style = 'color: var(--text-secondary); font-style: italic;'; }
                        echo '<span style="' . $style . '">' . htmlspecialchars($yeni_sinif) . '</span>';
                    ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div style="margin-top: 30px; text-align: center;">
        <button id="atlatmaOnayBtn" class="button-link button-danger" style="width: 100%;">
            Tüm Sınıfları Atlatma İşlemini Başlat
        </button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const onayBtn = document.getElementById('atlatmaOnayBtn');
    
    onayBtn.addEventListener('click', function() {
        if (confirm('DİKKAT: Bu işlem geri alınamaz! Tüm öğrencilerin sınıf bilgileri yukarıdaki tabloya göre güncellenecektir. Emin misiniz?')) {
            this.disabled = true;
            this.textContent = 'İşleniyor, lütfen bekleyin...';
            
            fetch('sinif_atlat_islem.php', { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    this.disabled = false;
                    this.textContent = 'Tüm Sınıfları Atlatma İşlemini Başlat';
                }
            })
            .catch(error => {
                console.error('İşlem hatası:', error);
                showToast('Bir ağ hatası oluştu. İşlem başlatılamadı.', 'error');
                this.disabled = false;
                this.textContent = 'Tüm Sınıfları Atlatma İşlemini Başlat';
            });
        }
    });
});
</script>

<?php require_once 'footer.php'; ?>