<?php
require_once 'header.php';
// Güvenlik: Giriş yapılmamışsa veya okul ID'si yoksa login sayfasına yönlendir
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["okul_id"])) {
    header("location: login.php");
    exit;
}
if (!isset($mysqli) || $mysqli->connect_errno) { require "config.php"; }

// Oturumdan mevcut okulun ID'sini al
$okul_id = $_SESSION["okul_id"];

// Bekleyen ve Bildirilen rezervasyonları çek (sadece o okula ait)
$sql = "SELECT 
            R.id as rezervasyon_id,
            K.kitap_adi,
            O.ogrenci_no,
            O.ad,
            O.soyad,
            R.rezervasyon_tarihi,
            R.durum
        FROM rezervasyonlar R
        JOIN kitaplar K ON R.kitap_id = K.id
        JOIN ogrenciler O ON R.ogrenci_id = O.id
        WHERE R.okul_id = ? AND (R.durum = 'bekliyor' OR R.durum = 'bildirildi')
        ORDER BY K.kitap_adi, R.rezervasyon_tarihi ASC";

$rezervasyonlar = [];
if($stmt = $mysqli->prepare($sql)) {
    $stmt->bind_param("i", $okul_id);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($rez_id, $kitap_adi, $ogrenci_no, $ad, $soyad, $rez_tarihi, $durum);
    
    while($stmt->fetch()) {
        $rezervasyonlar[] = [
            'rezervasyon_id' => $rez_id,
            'kitap_adi' => $kitap_adi,
            'ogrenci_no' => $ogrenci_no,
            'ad' => $ad,
            'soyad' => $soyad,
            'rezervasyon_tarihi' => $rez_tarihi,
            'durum' => $durum
        ];
    }
    $stmt->close();
}
$mysqli->close();
?>

<h1>Aktif Rezervasyonlar</h1>
<p>Öğrenciler tarafından sıraya girilerek beklenen veya kendileri için ayrılmış olan kitapların listesi.</p>

<div class="form-wrapper">
    <table id="rezervasyonTable">
        <thead>
            <tr>
                <th>Kitap Adı</th>
                <th>Öğrenci No</th>
                <th>Öğrenci Adı</th>
                <th>Rezervasyon Tarihi</th>
                <th>Durum</th>
                <th>İşlem</th>
            </tr>
        </thead>
        <tbody>
            <?php if(!empty($rezervasyonlar)): ?>
                <?php foreach($rezervasyonlar as $rez): ?>
                    <tr data-id="<?php echo $rez['rezervasyon_id']; ?>">
                        <td><?php echo htmlspecialchars($rez['kitap_adi']); ?></td>
                        <td><?php echo htmlspecialchars($rez['ogrenci_no']); ?></td>
                        <td><?php echo htmlspecialchars($rez['ad'] . ' ' . $rez['soyad']); ?></td>
                        <td><?php echo date('d.m.Y H:i', strtotime($rez['rezervasyon_tarihi'])); ?></td>
                        <td>
                            <?php 
                                $durum_class = '';
                                $durum_text = '';
                                if ($rez['durum'] == 'bekliyor') {
                                    $durum_class = 'durum-odunc'; // Sarı renk
                                    $durum_text = 'Sırada Bekliyor';
                                } else if ($rez['durum'] == 'bildirildi') {
                                    $durum_class = 'durum-rafta'; // Yeşil renk
                                    $durum_text = 'Kitap Ayrıldı';
                                }
                            ?>
                            <span class="durum <?php echo $durum_class; ?>"><?php echo $durum_text; ?></span>
                        </td>
                        <td>
                            <button class="button-link small button-danger iptal-btn" 
                                    data-id="<?php echo $rez['rezervasyon_id']; ?>"
                                    data-info="'<?php echo htmlspecialchars($rez['kitap_adi']); ?>' kitabının '<?php echo htmlspecialchars($rez['ad'] . ' ' . $rez['soyad']); ?>' adına olan rezervasyonu">
                                İptal Et
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 20px;">Aktif rezervasyon bulunmamaktadır.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.iptal-btn').forEach(button => {
        button.addEventListener('click', function() {
            const rezId = this.dataset.id;
            const rezInfo = this.dataset.info;

            if(confirm(`${rezInfo}nu iptal etmek istediğinize emin misiniz?`)) {
                const formData = new FormData();
                formData.append('rezervasyon_id', rezId);

                fetch('rezervasyon_iptal.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        showToast(data.message, 'success');
                        const row = document.querySelector(`tr[data-id='${rezId}']`);
                        if(row) {
                            row.style.opacity = '0';
                            setTimeout(() => row.remove(), 300);
                        }
                    } else {
                        showToast(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('İptal hatası:', error);
                    showToast('Bir ağ hatası oluştu.', 'error');
                });
            }
        });
    });
});
</script>

<?php require_once 'footer.php'; ?>