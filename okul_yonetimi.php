<?php
require_once 'header.php';

// GÜVENLİK: Sadece "admin" kullanıcısı bu sayfayı görebilir.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["username"] !== 'admin') {
    header("location: index.php");
    exit;
}
if (!isset($mysqli) || $mysqli->connect_errno) { require "config.php"; }

$current_okul_id = $_SESSION["okul_id"];

// Mevcut okulları listele
$okullar = [];
$sql_okullar = "SELECT id, okul_adi, subdomain, olusturma_tarihi FROM okullar ORDER BY id ASC";
if($result = $mysqli->query($sql_okullar)) {
    while($row = $result->fetch_assoc()) {
        $okullar[] = $row;
    }
    $result->free();
}
$mysqli->close();
?>

<h1>Okul Yönetimi</h1>
<p>Bu bölümden sisteme yeni okullar ekleyebilir ve mevcut okulları silebilirsiniz. Sadece ana admin bu sayfayı görebilir.</p>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px;">
    
    <div class="form-wrapper">
        <h3>Yeni Okul Ekle</h3>
        <form id="yeniOkulForm">
            <div class="form-group">
                <label for="yeni_okul_adi">Okul Adı:</label>
                <input type="text" name="yeni_okul_adi" id="yeni_okul_adi" required>
            </div>
            <div class="form-group">
                <label for="yeni_okul_subdomain">Alt Alan Adı (Subdomain):</label>
                <input type="text" name="yeni_okul_subdomain" id="yeni_okul_subdomain" required placeholder="ornek-okul">
                <small>Benzersiz olmalı, boşluk ve Türkçe karakter içermemeli.</small>
            </div>
            <hr style="border-color: var(--border-color); margin: 20px 0;">
            <div class="form-group">
                <label for="yeni_yonetici_kullanici_adi">Okul Yöneticisi Kullanıcı Adı:</label>
                <input type="text" name="yeni_yonetici_kullanici_adi" id="yeni_yonetici_kullanici_adi" required>
            </div>
            <div class="form-group">
                <label for="yeni_yonetici_sifre">Okul Yöneticisi Şifresi:</label>
                <input type="password" name="yeni_yonetici_sifre" id="yeni_yonetici_sifre" required>
                <small>En az 6 karakter olmalı.</small>
            </div>
            <input type="submit" value="Yeni Okulu ve Yöneticiyi Oluştur">
        </form>
    </div>

    <div class="form-wrapper">
        <h3>Sistemdeki Okullar</h3>
        <table id="okullarTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Okul Adı</th>
                    <th>Subdomain</th>
                    <th>İşlem</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!empty($okullar)): ?>
                    <?php foreach($okullar as $okul): ?>
                        <tr data-id="<?php echo $okul['id']; ?>">
                            <td><?php echo $okul['id']; ?></td>
                            <td><?php echo htmlspecialchars($okul['okul_adi']); ?></td>
                            <td><?php echo htmlspecialchars($okul['subdomain']); ?></td>
                            <td>
                                <?php if ($okul['id'] == 1 || $okul['id'] == $current_okul_id): ?>
                                    <span class="durum durum-odunc small">Korunuyor</span>
                                <?php else: ?>
                                    <button class="button-link small button-danger delete-okul-btn" 
                                            data-id="<?php echo $okul['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($okul['okul_adi']); ?>">
                                        Sil
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align: center;">Sistemde kayıtlı okul bulunmamaktadır.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const yeniOkulForm = document.getElementById('yeniOkulForm');
    const deleteOkulButtons = document.querySelectorAll('.delete-okul-btn');

    // 1. OKUL EKLEME MANTIĞI (AJAX)
    yeniOkulForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const submitButton = this.querySelector('input[type="submit"]');
        submitButton.disabled = true;
        submitButton.value = 'Oluşturuluyor...';

        const formData = new FormData(this);

        fetch('okul_ekle_ajax.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            showToast(data.message, data.success ? 'success' : 'error');
            if (data.success) {
                this.reset();
                setTimeout(() => window.location.reload(), 2000);
            } else {
                submitButton.disabled = false;
                submitButton.value = 'Yeni Okulu ve Yöneticiyi Oluştur';
            }
        })
        .catch(error => {
            console.error('İşlem hatası:', error);
            showToast('Bir ağ hatası oluştu. İşlem gerçekleştirilemedi.', 'error');
            submitButton.disabled = false;
            submitButton.value = 'Yeni Okulu ve Yöneticiyi Oluştur';
        });
    });

    // 2. OKUL SİLME MANTIĞI (AJAX)
    deleteOkulButtons.forEach(button => {
        button.addEventListener('click', function() {
            const okulId = this.dataset.id;
            const okulName = this.dataset.name;

            if (confirm(`KRİTİK UYARI: '${okulName}' adlı okulu silmek, bu okula ait TÜM ÖĞRENCİ, KİTAP ve İŞLEM verilerini sistemden kalıcı olarak silecektir. Bu işlem geri alınamaz. Emin misiniz?`)) {
                
                this.disabled = true;
                this.textContent = 'Siliniyor...';

                const formData = new FormData();
                formData.append('okul_id', okulId);

                fetch('okul_sil.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    showToast(data.message, data.success ? 'success' : 'error');
                    if (data.success) {
                        const row = document.querySelector(`tr[data-id='${okulId}']`);
                        if (row) {
                            row.style.opacity = '0';
                            setTimeout(() => row.remove(), 300);
                        }
                    } else {
                        this.disabled = false;
                        this.textContent = 'Sil';
                    }
                })
                .catch(error => {
                    console.error('Silme hatası:', error);
                    showToast('Bir ağ hatası oluştu.', 'error');
                    this.disabled = false;
                    this.textContent = 'Sil';
                });
            }
        });
    });
});
</script>

<?php require_once 'footer.php'; ?>