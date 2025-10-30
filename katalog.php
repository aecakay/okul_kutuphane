<?php
// BU SAYFA HALKA AÇIKTIR, GİRİŞ KONTROLÜ YOKTUR
require_once 'header.php';
if (!isset($mysqli) || $mysqli->connect_errno) { require "config.php"; }

// Sayfalama ve Arama Ayarları
$sayfa = isset($_GET['sayfa']) ? (int)$_GET['sayfa'] : 1;
if ($sayfa < 1) $sayfa = 1;
$kayit_per_sayfa = 20;
$offset = ($sayfa - 1) * $kayit_per_sayfa;
$arama_terimi = isset($_GET['ara']) ? trim($_GET['ara']) : '';
$sql_arama_terimi = '%' . $arama_terimi . '%';

// SQL WHERE koşulunu ve parametreleri hazırla
$where_sql_init = "";
$params_init = [];
$types_init = "";
if(!empty($arama_terimi)){
    $where_sql_init = " WHERE (kitap_adi LIKE ? OR yazar LIKE ? OR isbn LIKE ?)";
    $params_init = [$sql_arama_terimi, $sql_arama_terimi, $sql_arama_terimi];
    $types_init = "sss";
}

// Toplam Başlık Sayısı (Sayfalama için)
$sql_count_baslik = "SELECT COUNT(id) FROM kitaplar" . $where_sql_init;
$stmt_count_baslik = $mysqli->prepare($sql_count_baslik);
if(!empty($params_init)){ $stmt_count_baslik->bind_param($types_init, ...$params_init); }
$stmt_count_baslik->execute();
$stmt_count_baslik->store_result();
$stmt_count_baslik->bind_result($toplam_kayit_baslik);
$stmt_count_baslik->fetch();
$toplam_sayfa = ceil($toplam_kayit_baslik / $kayit_per_sayfa);
$stmt_count_baslik->close();

// Toplam Fiziksel Kitap Sayısı (Başlık için)
$sql_count_fiziksel = "SELECT SUM(toplam_adet) FROM kitaplar" . $where_sql_init;
$stmt_count_fiziksel = $mysqli->prepare($sql_count_fiziksel);
if(!empty($params_init)){ $stmt_count_fiziksel->bind_param($types_init, ...$params_init); }
$stmt_count_fiziksel->execute();
$stmt_count_fiziksel->store_result();
$stmt_count_fiziksel->bind_result($toplam_kayit_fiziksel);
$stmt_count_fiziksel->fetch();
if ($toplam_kayit_fiziksel === null) $toplam_kayit_fiziksel = 0;
$stmt_count_fiziksel->close();

?>

<div class="form-group" style="margin-top: 0; margin-bottom: 20px;">
    <label for="liveSearchInput" style="font-size: 1.2em; color: var(--text-primary); font-weight: 600;">Kitap Ara (Kitap Adı, Yazar veya ISBN)</label>
    <input type="text" id="liveSearchInput" placeholder="Aramak için yazmaya başlayın..." value="<?php echo htmlspecialchars($arama_terimi); ?>">
</div>
<h3 id="katalogBaslik" style="margin-top: 2px; border-bottom: none; padding-bottom: 0; margin-bottom: 15px;">
    Tüm Kitaplar (Toplam: <?php echo $toplam_kayit_fiziksel; ?> Kitap)
</h3>
<table>
    <thead><tr><th>Kitap Adı</th><th>Yazar</th><th>Durum</th></tr></thead>
    <tbody id="katalogTableBody">
    <?php
    $sql = "SELECT id, kitap_adi, yazar, raftaki_adet FROM kitaplar" . $where_sql_init;
    $sql .= " ORDER BY kitap_adi LIMIT ? OFFSET ?";
    $params = $params_init;
    $params[] = $kayit_per_sayfa; $types_init .= "i";
    $params[] = $offset;          $types_init .= "i";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param($types_init, ...$params);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($col_id, $col_kitap_adi, $col_yazar, $col_raftaki_adet);

    if($stmt->num_rows > 0){
        while($stmt->fetch()){
            echo "<tr>";
            echo '<td><a href="kitap_detay.php?id=' . $col_id . '">' . htmlspecialchars($col_kitap_adi) . '</a></td>';
            echo "<td>" . htmlspecialchars($col_yazar) . "</td>";
            if ($col_raftaki_adet > 0) {
                 echo '<td><span class="durum durum-rafta">Rafta</span></td>';
            } else {
                 echo '<td><span class="durum durum-odunc">Ödünçte</span></td>';
            }
            echo "</tr>";
        }
    } else {
        echo '<tr><td colspan="3">Kayıt bulunamadı.</td></tr>';
    }
    $stmt->close();
    ?>
    </tbody>
</table>

<nav id="paginationNav">
    <ul class="pagination">
        <li class="<?php if($sayfa <= 1){ echo 'disabled'; } ?>"> <a href="?sayfa=<?php echo $sayfa - 1; ?>&ara=<?php echo urlencode($arama_terimi); ?>">Önceki</a> </li>
        <?php for($i = 1; $i <= $toplam_sayfa; $i++): ?> <li class="<?php if($sayfa == $i) { echo 'active'; } ?>"> <a href="?sayfa=<?php echo $i; ?>&ara=<?php echo urlencode($arama_terimi); ?>"><?php echo $i; ?></a> </li> <?php endfor; ?>
        <li class="<?php if($sayfa >= $toplam_sayfa){ echo 'disabled'; } ?>"> <a href="?sayfa=<?php echo $sayfa + 1; ?>&ara=<?php echo urlencode($arama_terimi); ?>">Sonraki</a> </li>
    </ul>
</nav>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('liveSearchInput');
    const tableBody = document.getElementById('katalogTableBody');
    const paginationNav = document.getElementById('paginationNav');
    const tableTitle = document.getElementById('katalogBaslik');
    let searchTimeout; 
    
    function performLiveSearch(searchTerm, page = 1) {
        tableBody.style.opacity = '0.5';
        const url = `arama_katalog.php?ara=${encodeURIComponent(searchTerm)}&sayfa=${page}`;
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.error) { throw new Error(data.error); }
                tableBody.innerHTML = data.html_tablo;
                paginationNav.innerHTML = data.html_sayfalama;
                tableTitle.textContent = `Tüm Kitaplar (Toplam: ${data.toplam_kayit_fiziksel} Kitap)`;
                tableBody.style.opacity = '1';
                const newUrl = `${window.location.pathname}?ara=${encodeURIComponent(searchTerm)}&sayfa=${page}`;
                window.history.pushState({path: newUrl}, '', newUrl);
            })
            .catch(error => {
                console.error('Canlı arama hatası:', error);
                tableBody.innerHTML = '<tr><td colspan="3" style="color: var(--danger);">Arama sırasında bir hata oluştu.</td></tr>'; 
                tableBody.style.opacity = '1';
            });
    }

    if (searchInput) { 
        searchInput.addEventListener('input', debounce((e) => { 
            performLiveSearch(e.target.value, 1); 
        }, 300)); 
    }

    if (paginationNav) { 
        paginationNav.addEventListener('click', (e) => { 
            if (e.target.tagName === 'A') { 
                e.preventDefault(); 
                const href = e.target.getAttribute('href'); 
                if (!href || e.target.parentElement.classList.contains('disabled')) return; 
                
                const urlParams = new URLSearchParams(href.substring(href.indexOf('?')));
                const page = urlParams.get('sayfa') || 1; 
                const searchTerm = searchInput.value || '';
                
                performLiveSearch(searchTerm, page); 
            } 
        }); 
    }
});
function debounce(func, delay = 300) { let timeout; return function(...args) { clearTimeout(timeout); timeout = setTimeout(() => { func.apply(this, args); }, delay); }; }
</script>

<?php
if(isset($mysqli) && !$mysqli->connect_errno) { $mysqli->close(); }
require_once 'footer.php';
?>