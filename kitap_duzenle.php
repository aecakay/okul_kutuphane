<?php
require_once 'header.php';
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["okul_id"])){
    header("location: login.php");
    exit;
}
if (!isset($mysqli) || $mysqli->connect_errno) { require "config.php"; }
$okul_id = $_SESSION["okul_id"];

$kitap_adi = $yazar = $isbn = $basim_yili = $sayfa_sayisi = $kapak_url = "";
$toplam_adet = 1; $raftaki_adet = 1; $mesaj = ""; $kitap_id = 0;

if($_SERVER["REQUEST_METHOD"] == "POST"){
    $kitap_id = (int)$_POST["id"];
    $kitap_adi = trim($_POST["kitap_adi"]);
    $yazar = trim($_POST["yazar"]);
    $isbn = trim($_POST["isbn"]);
    $basim_yili = trim($_POST["basim_yili"]);
    $sayfa_sayisi = !empty($_POST["sayfa_sayisi"]) ? (int)$_POST["sayfa_sayisi"] : null;
    $kapak_url = trim($_POST["kapak_url"]);
    $yeni_toplam_adet = isset($_POST["toplam_adet"]) && (int)$_POST["toplam_adet"] >= 0 ? (int)$_POST["toplam_adet"] : 0;
    
    $hata_var = false; $mevcut_toplam_adet = 0; $mevcut_raftaki_adet = 0;

    $sql_mevcut_stok = "SELECT toplam_adet, raftaki_adet FROM kitaplar WHERE id = ? AND okul_id = ?";
    if($stmt_stok = $mysqli->prepare($sql_mevcut_stok)){
        $stmt_stok->bind_param("ii", $kitap_id, $okul_id);
        $stmt_stok->execute();
        $stmt_stok->store_result();
        if($stmt_stok->num_rows == 1){
            $stmt_stok->bind_result($mevcut_toplam_adet, $mevcut_raftaki_adet);
            $stmt_stok->fetch();
        } else { $mesaj = '<p class="error">HATA: Güncellenecek kitap bulunamadı veya yetkiniz yok.</p>'; $hata_var = true; }
        $stmt_stok->close();
    }

    if (!$hata_var) {
        $odunc_adet = $mevcut_toplam_adet - $mevcut_raftaki_adet;
        if ($yeni_toplam_adet < $odunc_adet) {
            $mesaj = '<p class="error">HATA: Toplam adet, ödünçteki kitap sayısından (' . $odunc_adet . ') az olamaz.</p>'; $hata_var = true;
        }
    }

    if (!$hata_var && !empty($kitap_adi) && $kitap_id > 0){
        $fark = $yeni_toplam_adet - $mevcut_toplam_adet;
        $yeni_raftaki_adet = $mevcut_raftaki_adet + $fark;
        if ($yeni_raftaki_adet < 0) $yeni_raftaki_adet = 0;

        $sql = "UPDATE kitaplar SET kitap_adi = ?, yazar = ?, isbn = ?, basim_yili = ?, sayfa_sayisi = ?, kapak_url = ?, toplam_adet = ?, raftaki_adet = ? WHERE id = ? AND okul_id = ?";
        if($stmt = $mysqli->prepare($sql)){
            $stmt->bind_param("ssssisiiii", $kitap_adi, $yazar, $isbn, $basim_yili, $sayfa_sayisi, $kapak_url, $yeni_toplam_adet, $yeni_raftaki_adet, $kitap_id, $okul_id);
            if($stmt->execute()){
                header("location: kitaplar.php?guncelleme=basarili");
                exit;
            } else { $mesaj = '<p class="error">HATA: Kitap güncellenemedi. (' . $stmt->error . ')</p>'; }
            $stmt->close();
        }
    }
    $toplam_adet = $yeni_toplam_adet;
} else {
    if(isset($_GET["id"]) && !empty(trim($_GET["id"]))){
        $kitap_id = (int)trim($_GET["id"]);
        $sql = "SELECT kitap_adi, yazar, isbn, basim_yili, sayfa_sayisi, kapak_url, toplam_adet, raftaki_adet FROM kitaplar WHERE id = ? AND okul_id = ?";
        if($stmt = $mysqli->prepare($sql)){
            $stmt->bind_param("ii", $kitap_id, $okul_id);
            if($stmt->execute()){
                $stmt->store_result();
                if($stmt->num_rows == 1){
                    $stmt->bind_result($kitap_adi, $yazar, $isbn, $basim_yili, $sayfa_sayisi, $kapak_url, $toplam_adet, $raftaki_adet);
                    $stmt->fetch();
                } else { header("location: kitaplar.php?hata=yetkisiz"); exit; }
            } else{ $mesaj = '<p class="error">HATA: Sorgu çalıştırılamadı.</p>'; }
            $stmt->close();
        }
    } else { header("location: kitaplar.php"); exit; }
}
$mysqli->close();
?>

<h1>Kitap Düzenle</h1>
<?php echo $mesaj; ?>
<div class="form-wrapper">
    <form action="kitap_duzenle.php" method="post">
        <div style="display: grid; grid-template-columns: 1fr 170px; gap: 20px;">
            <div style="grid-column: 1 / 2;">
                <div class="form-group"> <label>Kitap Adı (*zorunlu)</label> <input type="text" name="kitap_adi" value="<?php echo htmlspecialchars($kitap_adi); ?>" required> </div>
                <div class="form-group"> <label>Yazar</label> <input type="text" name="yazar" value="<?php echo htmlspecialchars($yazar); ?>"> </div>
                <div class="form-group"> <label>ISBN</label> <input type="text" name="isbn" value="<?php echo htmlspecialchars($isbn); ?>"> </div>
                <div class="form-group">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                        <div> <label>Basım Yılı</label> <input type="text" name="basim_yili" value="<?php echo htmlspecialchars($basim_yili); ?>" placeholder="YYYY"> </div>
                        <div> <label>Sayfa Sayısı</label> <input type="number" name="sayfa_sayisi" value="<?php echo htmlspecialchars($sayfa_sayisi); ?>" min="0"> </div>
                        <div> <label>Toplam Adet</label> <input type="number" name="toplam_adet" value="<?php echo htmlspecialchars($toplam_adet); ?>" min="0" required> </div>
                    </div>
                </div>
                 <div style="margin-top: 15px; color: var(--text-secondary);"> <p><strong>Mevcut Durum:</strong> <?php echo $raftaki_adet; ?> adet rafta / <?php echo $toplam_adet; ?> adet toplam.</p> </div>
            </div>
            <div style="grid-column: 2 / 3;">
                <label>Kapak Fotoğrafı URL:</label>
                <input type="text" name="kapak_url" value="<?php echo htmlspecialchars($kapak_url); ?>" placeholder="https://..." style="margin-bottom: 10px;">
                <div class="cover-preview-wrapper" style="margin-bottom: 10px;">
                    <img id="coverPreviewEdit" src="<?php echo !empty($kapak_url) ? htmlspecialchars($kapak_url) : ''; ?>" alt="Kitap Kapağı" style="<?php echo empty($kapak_url) ? 'display: none;' : 'display: block; width: 100%; height: auto;'; ?>">
                </div>
            </div>
        </div>
        <br>
        <input type="hidden" name="id" value="<?php echo $kitap_id; ?>"/>
        <input type="submit" value="Bilgileri Güncelle">
    </form>
    <br>
    <a href="kitaplar.php" style="color: #aaa; text-decoration: underline;">« Geri Dön</a>
</div>

<?php require_once 'footer.php'; ?>