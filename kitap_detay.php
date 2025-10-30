<?php
require_once 'header.php';
if (!isset($mysqli) || $mysqli->connect_errno) { require "config.php"; }

$kitap = null;
$okuma_gecmisi = [];
$rezervasyon_durumu = null; // 'yapilabilir', 'zaten_var', 'giris_gerekli', 'stokta_var'
$mesaj = "";
$kitap_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($kitap_id > 0) {
    // 1. Kitap ve okul bilgilerini birlikte çek
    $sql = "SELECT 
                k.kitap_adi, k.yazar, k.isbn, k.basim_yili, k.sayfa_sayisi, k.kapak_url, 
                k.toplam_adet, k.raftaki_adet, k.okul_id, o.okul_adi 
            FROM kitaplar k
            JOIN okullar o ON k.okul_id = o.id
            WHERE k.id = ?";
            
    if($stmt = $mysqli->prepare($sql)){
        $stmt->bind_param("i", $kitap_id);
        if($stmt->execute()){
            $stmt->store_result();
            if($stmt->num_rows == 1){
                $stmt->bind_result($db_kitap_adi, $db_yazar, $db_isbn, $db_basim_yili, $db_sayfa_sayisi, $db_kapak_url, $db_toplam_adet, $db_raftaki_adet, $db_okul_id, $db_okul_adi);
                $stmt->fetch();
                $kitap = [
                    'kitap_adi' => $db_kitap_adi, 'yazar' => $db_yazar, 'isbn' => $db_isbn,
                    'basim_yili' => $db_basim_yili, 'sayfa_sayisi' => $db_sayfa_sayisi,
                    'kapak_url' => $db_kapak_url, 'toplam_adet' => $db_toplam_adet,
                    'raftaki_adet' => $db_raftaki_adet, 'okul_id' => $db_okul_id, 'okul_adi' => $db_okul_adi
                ];
            } else { $mesaj = '<p class="error">İstenen kitap bulunamadı.</p>'; }
        } else { $mesaj = '<p class="error">HATA: Sorgu çalıştırılamadı.</p>'; }
        $stmt->close();
    }

    if ($kitap) {
        // 2. Kitabın okuma geçmişini çek (sadece o okula ait işlemleri)
        $sql_gecmis = "SELECT O.ad, O.soyad, I.iade_tarihi
                       FROM islemler I
                       JOIN ogrenciler O ON I.ogrenci_id = O.id
                       WHERE I.kitap_id = ? AND I.okul_id = ? AND I.iade_tarihi IS NOT NULL
                       ORDER BY I.iade_tarihi DESC
                       LIMIT 10";
        if($stmt_gecmis = $mysqli->prepare($sql_gecmis)) {
            $stmt_gecmis->bind_param("ii", $kitap_id, $kitap['okul_id']);
            $stmt_gecmis->execute();
            $stmt_gecmis->store_result();
            $stmt_gecmis->bind_result($okur_ad, $okur_soyad, $iade_tarihi);
            while($stmt_gecmis->fetch()){
                $okuma_gecmisi[] = ['ad_soyad' => $okur_ad . ' ' . $okur_soyad, 'iade_tarihi' => $iade_tarihi];
            }
            $stmt_gecmis->close();
        }

        // 3. Rezervasyon durumunu kontrol et
        if ($kitap['raftaki_adet'] > 0) {
            $rezervasyon_durumu = 'stokta_var';
        } else {
            if (isset($_SESSION["student_loggedin"]) && $_SESSION["student_loggedin"] === true) {
                $ogrenci_id = $_SESSION["student_id"];
                $sql_rez_check = "SELECT id FROM rezervasyonlar WHERE kitap_id = ? AND ogrenci_id = ? AND okul_id = ? AND durum = 'bekliyor' LIMIT 1";
                if($stmt_rez = $mysqli->prepare($sql_rez_check)) {
                    $stmt_rez->bind_param("iii", $kitap_id, $ogrenci_id, $kitap['okul_id']);
                    $stmt_rez->execute();
                    $stmt_rez->store_result();
                    if($stmt_rez->num_rows > 0) {
                        $rezervasyon_durumu = 'zaten_var';
                    } else {
                        $rezervasyon_durumu = 'yapilabilir';
                    }
                    $stmt_rez->close();
                }
            } else {
                $rezervasyon_durumu = 'giris_gerekli';
            }
        }
    }
} else { 
    $mesaj = '<p class="error">Geçersiz kitap ID\'si.</p>'; 
}

if(isset($mysqli) && !$mysqli->connect_errno) { $mysqli->close(); }
?>

<?php if ($kitap): ?>
    <h1><?php echo htmlspecialchars($kitap['kitap_adi']); ?></h1>
    <p style="color: var(--text-secondary); margin-top: -15px; margin-bottom: 10px; font-size: 1.1rem;"><?php echo htmlspecialchars($kitap['yazar']); ?></p>
    <p style="color: var(--warning); margin-top: -10px; margin-bottom: 30px; font-size: 0.9rem; font-style: italic;"><?php echo htmlspecialchars($kitap['okul_adi']); ?></p>
    
    <div style="display: grid; grid-template-columns: 220px 1fr; gap: 40px;">
        <div>
            <?php if (!empty($kitap['kapak_url'])): ?>
                <img src="<?php echo htmlspecialchars($kitap['kapak_url']); ?>" alt="Kitap Kapağı" style="width: 100%; height: auto; border-radius: var(--border-radius); box-shadow: var(--box-shadow); margin-bottom: 20px;">
            <?php else: ?>
                <div style="width: 100%; height: 300px; background-color: var(--bg-sidebar); color: var(--text-secondary); display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-color); border-radius: var(--border-radius); margin-bottom: 20px;">Kapak Görseli Yok</div>
            <?php endif; ?>
            
            <h3>Durum</h3>
            <?php if ($kitap['raftaki_adet'] > 0): ?>
                <p><span class="durum durum-rafta">Rafta Mevcut</span></p>
                <p style="color:var(--text-secondary); font-size: 0.9em;">(<?php echo $kitap['raftaki_adet']; ?> / <?php echo $kitap['toplam_adet']; ?> adet rafta)</p>
            <?php else: ?>
                <p><span class="durum durum-odunc">Ödünçte</span></p>
                <div id="rezervasyonAlani" style="margin-top: 15px;">
                    <?php if($rezervasyon_durumu == 'yapilabilir'): ?>
                        <button id="rezerveEtBtn" data-kitap-id="<?php echo $kitap_id; ?>" class="button-link button-warning" style="width: 100%;">Sıraya Gir (Rezerve Et)</button>
                    <?php elseif($rezervasyon_durumu == 'zaten_var'): ?>
                        <p style="color: var(--success); font-weight: bold;">Bu kitap için zaten bekleme sırasındasınız.</p>
                    <?php elseif($rezervasyon_durumu == 'giris_gerekli'): ?>
                        <p style="font-size: 0.9em; color: var(--text-secondary);">Bu kitabı rezerve etmek için <a href="ogrenci_giris.php">öğrenci girişi</a> yapmalısınız.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div>
            <div class="form-wrapper" style="padding: 1.5rem;">
                <h3 style="margin-top:0;">Kitap Bilgileri</h3>
                <p><strong>ISBN:</strong> <?php echo !empty($kitap['isbn']) ? htmlspecialchars($kitap['isbn']) : '-'; ?></p>
                <p><strong>Basım Yılı:</strong> <?php echo !empty($kitap['basim_yili']) ? htmlspecialchars($kitap['basim_yili']) : '-'; ?></p>
                <p><strong>Sayfa Sayısı:</strong> <?php echo !empty($kitap['sayfa_sayisi']) ? htmlspecialchars($kitap['sayfa_sayisi']) : '-'; ?></p>
                <p><strong>Kütüphanedeki Kopya Sayısı:</strong> <?php echo htmlspecialchars($kitap['toplam_adet']); ?></p>
            </div>

            <div class="form-wrapper" style="margin-top: 30px; padding: 1.5rem;">
                <h3 style="margin-top:0;">Bu Kitabı Daha Önce Okuyanlar</h3>
                <?php if(!empty($okuma_gecmisi)): ?>
                    <ul style="list-style-type: none; padding-left: 0;">
                        <?php foreach($okuma_gecmisi as $gecmis): ?>
                            <li style="padding: 8px 0; border-bottom: 1px solid var(--border-color);"><?php echo htmlspecialchars($gecmis['ad_soyad']); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>Bu kitap daha önce hiç okunmamış veya henüz iade edilmemiş.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php else: ?>
    <h1>Hata</h1>
    <?php echo $mesaj; ?>
<?php endif; ?>

<br>
<p><a href="ogrenci_panel.php" style="text-decoration: underline;">« Kataloğa Geri Dön</a></p>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const rezerveEtBtn = document.getElementById('rezerveEtBtn');
    if (rezerveEtBtn) {
        rezerveEtBtn.addEventListener('click', function() {
            const kitapId = this.dataset.kitapId;
            const rezervasyonAlani = document.getElementById('rezervasyonAlani');

            if (!kitapId) {
                showToast('Kitap ID bulunamadı.', 'error');
                return;
            }

            // Butonu devre dışı bırak ve metni değiştir
            this.disabled = true;
            this.textContent = 'İşleniyor...';

            const formData = new FormData();
            formData.append('kitap_id', kitapId);

            fetch('rezervasyon_yap.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showToast(data.message, data.success ? 'success' : 'error');
                
                // Başarılı olursa, butonu kaldırıp yerine başarı mesajı göster
                if (data.success) {
                    rezervasyonAlani.innerHTML = '<p style="color: var(--success); font-weight: bold;">Bu kitap için bekleme sırasına girdiniz.</p>';
                } else {
                    // Başarısız olursa, butonu tekrar aktif et
                    this.disabled = false;
                    this.textContent = 'Sıraya Gir (Rezerve Et)';
                }
            })
            .catch(error => {
                console.error('Rezervasyon hatası:', error);
                showToast('Bir ağ hatası oluştu. Lütfen tekrar deneyin.', 'error');
                // Hata durumunda butonu tekrar aktif et
                this.disabled = false;
                this.textContent = 'Sıraya Gir (Rezerve Et)';
            });
        });
    }
});
</script>
<?php require_once 'footer.php'; ?>