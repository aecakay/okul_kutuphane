<?php
require_once 'header.php';
// Güvenlik: Giriş yapılmamışsa veya okul ID'si yoksa login sayfasına yönlendir
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["okul_id"])) {
    header("location: login.php");
    exit;
}
if (!isset($mysqli) || $mysqli->connect_errno) { require "config.php"; }

$okul_id = $_SESSION["okul_id"];

// --- YENİ EKLENEN BÖLÜM: Sınıf listesini veritabanından çekme ---
$siniflar = [];
$sql_siniflar = "SELECT DISTINCT sinif FROM ogrenciler WHERE okul_id = ? AND sinif IS NOT NULL AND sinif != '' ORDER BY sinif ASC";
if ($stmt_sinif = $mysqli->prepare($sql_siniflar)) {
    $stmt_sinif->bind_param("i", $okul_id);
    $stmt_sinif->execute();
    $stmt_sinif->bind_result($db_sinif);
    while($stmt_sinif->fetch()) {
        $siniflar[] = $db_sinif;
    }
    $stmt_sinif->close();
}
// --- YENİ BÖLÜM SONU ---
?>

<div class="form-wrapper">
    <h3><i class="fas fa-file-pdf fa-fw"></i> PDF Rapor Oluştur</h3>
    <p>Belirli bir öğrenci, sınıf veya tarih aralığı için detaylı okuma raporları oluşturun.</p>

    <form action="rapor_olustur.php" method="post" id="raporForm" target="_blank">

        <div class="form-group">
            <label for="raporTipiSelect">Rapor Tipi</label>
            <select name="rapor_tipi" id="raporTipiSelect" required>
                <option value="" disabled selected>Lütfen bir rapor tipi seçin...</option>
                <option value="ogrenci">Öğrenci Bazlı Rapor</option>
                <option value="sinif">Sınıf Bazlı Rapor</option>
                <option value="genel">Genel Rapor</option>
            </select>
        </div>

        <div class="form-group autocomplete-wrapper" id="ogrenci_filtre_alani" style="display: none;">
            <label for="ogrenci_arama">Öğrenci Ara</label>
            <input type="text" id="ogrenci_arama" placeholder="Öğrenci no, ad veya soyad ile arama yapın...">
            <input type="hidden" name="ogrenci_id" id="selected_ogrenci_id" value="0">
            <div class="autocomplete-items" id="ogrenci_sonuclari"></div>
        </div>

        <div class="form-group" id="sinif_filtre_alani" style="display: none;">
            <label for="sinif_select">Sınıf Seçimi</label>
            <select name="sinif" id="sinif_select">
                <option value="">Tüm Sınıflar</option>
                <?php foreach($siniflar as $sinif): ?>
                    <option value="<?php echo htmlspecialchars($sinif); ?>"><?php echo htmlspecialchars($sinif); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px;">
            <div class="form-group">
                <label for="bas_tarih">Başlangıç Tarihi (İsteğe Bağlı)</label>
                <input type="date" name="bas_tarih" id="bas_tarih">
            </div>
            <div class="form-group">
                <label for="bit_tarih">Bitiş Tarihi (İsteğe Bağlı)</label>
                <input type="date" name="bit_tarih" id="bit_tarih">
            </div>
        </div>

        <div class="form-group" style="margin-top: 20px;">
            <input type="submit" value="Rapor Oluştur">
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const raporTipiSelect = document.getElementById('raporTipiSelect');
    const ogrenciFiltreAlani = document.getElementById('ogrenci_filtre_alani');
    const sinifFiltreAlani = document.getElementById('sinif_filtre_alani');
    const ogrenciAramaInput = document.getElementById('ogrenci_arama');
    const sinifSelect = document.getElementById('sinif_select');

    // Rapor tipi değiştiğinde ilgili filtre alanını göster/gizle
    raporTipiSelect.addEventListener('change', function() {
        const secim = this.value;

        // Tüm alanları varsayılan olarak gizle ve 'required' özelliğini kaldır
        ogrenciFiltreAlani.style.display = 'none';
        ogrenciAramaInput.required = false;
        sinifFiltreAlani.style.display = 'none';
        sinifSelect.required = false;

        if (secim === 'ogrenci') {
            ogrenciFiltreAlani.style.display = 'block';
            ogrenciAramaInput.required = true;
        } else if (secim === 'sinif') {
            sinifFiltreAlani.style.display = 'block';
            sinifSelect.required = true; // Sınıf seçimi zorunlu
        }
    });

    // Öğrenci arama için autocomplete fonksiyonu
    setupAutocomplete("ogrenci_arama", "ogrenci_sonuclari", "selected_ogrenci_id", "arama_ogrenci.php", null);
});


// Standart Autocomplete fonksiyonu
function setupAutocomplete(inputId, resultsId, hiddenId, sourceUrl, onSelectCallback) {
    const input = document.getElementById(inputId);
    const resultsContainer = document.getElementById(resultsId);
    const hiddenInput = document.getElementById(hiddenId);
    let fetchTimeout;
    
    input.addEventListener("input", function() {
        let val = this.value;
        closeAllLists();
        hiddenInput.value = "0";
        if (onSelectCallback) onSelectCallback(null);
        if (!val || val.length < 1) return false;

        clearTimeout(fetchTimeout);
        fetchTimeout = setTimeout(() => {
            fetch(`${sourceUrl}?term=${encodeURIComponent(val)}`)
                .then(res => res.json())
                .then(data => {
                    resultsContainer.innerHTML = "";
                    if (!data || data.length === 0 || data.error) return;
                    resultsContainer.style.display = 'block';

                    data.forEach(item => {
                        let div = document.createElement("DIV");
                        div.innerHTML = item.label.replace(new RegExp(val.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&'), "gi"), (match) => `<strong>${match}</strong>`);
                        div.addEventListener("click", function() {
                            input.value = item.label; // Input'a tam etiketi yaz
                            hiddenInput.value = item.id;
                            if (onSelectCallback) onSelectCallback(item);
                            closeAllLists();
                        });
                        resultsContainer.appendChild(div);
                    });
                })
                .catch(err => console.error(err));
        }, 300);
    });

    function closeAllLists() {
        document.querySelectorAll(".autocomplete-items").forEach(el => {
            el.innerHTML = "";
            el.style.display = 'none';
        });
    }

    document.addEventListener("click", (e) => {
        if (!e.target.closest('.autocomplete-wrapper')) {
            closeAllLists();
        }
    });
}
</script>

<?php require_once 'footer.php'; ?>