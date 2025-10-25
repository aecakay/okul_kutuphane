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

// Veritabanındaki mevcut sınıfları çek (sadece o okula ait)
// DÜZELTME: get_result() yerine uyumlu yöntem kullanıldı
$siniflar = [];
$sql_siniflar = "SELECT DISTINCT sinif FROM ogrenciler WHERE okul_id = ? AND sinif IS NOT NULL AND sinif != '' ORDER BY sinif ASC";
if($stmt = $mysqli->prepare($sql_siniflar)) {
    $stmt->bind_param("i", $okul_id);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($sinif_adi);
    while($stmt->fetch()) {
        $siniflar[] = $sinif_adi;
    }
    $stmt->close();
}
?>

<h1>PDF Rapor Oluşturucu</h1>
<p>Belirli bir öğrencinin veya bir sınıfın tamamının okuma karnesini PDF formatında indirin.</p>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
    
    <div class="form-wrapper">
        <h3>Öğrenci Okuma Raporu</h3>
        <form action="rapor_olustur.php" method="get" target="_blank">
            <input type="hidden" name="tip" value="ogrenci">
            <div class="form-group autocomplete-wrapper">
                <label for="ogrenci_arama">Raporu Oluşturulacak Öğrenci:</label>
                <input type="text" id="ogrenci_arama" autocomplete="off" required placeholder="Öğrenci aramak için yazın...">
                <input type="hidden" name="ogrenci_id" id="ogrenci_id" value="0">
                <div class="autocomplete-items" id="ogrenci_sonuclari"></div>
            </div>
            <input type="submit" value="Öğrenci Raporunu Oluştur">
        </form>
    </div>

    <div class="form-wrapper">
        <h3>Sınıf Okuma Raporu</h3>
        <form action="rapor_olustur.php" method="get" target="_blank">
            <input type="hidden" name="tip" value="sinif">
            <div class="form-group">
                <label for="sinif_sec">Raporu Oluşturulacak Sınıf:</label>
                <select name="sinif" id="sinif_sec" required>
                    <option value="">-- Sınıf Seçin --</option>
                    <?php foreach($siniflar as $sinif): ?>
                        <option value="<?php echo htmlspecialchars($sinif); ?>"><?php echo htmlspecialchars($sinif); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="submit" value="Sınıf Raporunu Oluştur">
        </form>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Öğrenci arama autocomplete kurulumu (arama_ogrenci.php zaten okul ID'si ile çalışıyor)
    setupAutocomplete("ogrenci_arama", "ogrenci_sonuclari", "ogrenci_id", "arama_ogrenci.php", null);

    // Formların gönderilmeden önce seçimin yapıldığından emin ol
    document.querySelector('form[action="rapor_olustur.php"][target="_blank"]').addEventListener('submit', function(e) {
        if (this.querySelector('[name=tip]').value === 'ogrenci' && document.getElementById('ogrenci_id').value === '0') {
            e.preventDefault();
            showToast('Lütfen arama sonuçlarından geçerli bir öğrenci seçin.', 'error');
        }
    });
});

// Autocomplete fonksiyonu (değişiklik yok)
function setupAutocomplete(inputId, resultsId, hiddenId, sourceUrl, onSelectCallback) {
    const input = document.getElementById(inputId);
    const resultsContainer = document.getElementById(resultsId);
    const hiddenInput = document.getElementById(hiddenId);
    let fetchTimeout;
    input.addEventListener("input", function() {
        let val = this.value; closeAllLists();
        hiddenInput.value = "0";
        if (!val || val.length < 1) return;
        clearTimeout(fetchTimeout);
        fetchTimeout = setTimeout(() => {
            fetch(`${sourceUrl}?term=${encodeURIComponent(val)}`).then(res => res.json()).then(data => {
                resultsContainer.innerHTML = "";
                if (!data || data.length === 0 || data.error) return;
                resultsContainer.style.display = 'block';
                data.forEach(item => {
                    let div = document.createElement("DIV");
                    div.innerHTML = item.label.replace(new RegExp(val, "gi"), (match) => `<strong>${match}</strong>`);
                    div.addEventListener("click", function() {
                        input.value = item.value;
                        hiddenInput.value = item.id;
                        closeAllLists();
                    });
                    resultsContainer.appendChild(div);
                });
            }).catch(err => console.error(err));
        }, 300);
    });
    function closeAllLists() { document.querySelectorAll(".autocomplete-items").forEach(el => { el.innerHTML = ""; el.style.display = 'none'; }); }
    document.addEventListener("click", (e) => { if (!e.target.closest('.autocomplete-wrapper')) closeAllLists(); });
}
</script>

<?php require_once 'footer.php'; ?>