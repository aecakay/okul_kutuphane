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

$ogrenci_no = $ad = $soyad = $sinif = "";
$mesaj = "";
$ogrenci_id = 0;

// GÜNCELLEME (POST) İŞLEMİ
if($_SERVER["REQUEST_METHOD"] == "POST"){
    $ogrenci_id = (int)$_POST["id"];
    $ogrenci_no = trim($_POST["ogrenci_no"]);
    $ad = trim($_POST["ad"]);
    $soyad = trim($_POST["soyad"]);
    $sinif = trim($_POST["sinif"]);

    if(!empty($ogrenci_no) && !empty($ad) && !empty($soyad) && $ogrenci_id > 0){
        // SQL SORGUSU GÜNCELLENDİ: Sadece o okula ait öğrenciyi günceller
        $sql = "UPDATE ogrenciler SET ogrenci_no = ?, ad = ?, soyad = ?, sinif = ? WHERE id = ? AND okul_id = ?";
        if($stmt = $mysqli->prepare($sql)){
            // bind_param GÜNCELLENDİ: "ssssii" oldu (okul_id eklendi)
            $stmt->bind_param("ssssii", $ogrenci_no, $ad, $soyad, $sinif, $ogrenci_id, $okul_id);
            
            if($stmt->execute()){
                // Etkilenen satır sayısını kontrol et
                if($stmt->affected_rows > 0) {
                    header("location: ogrenciler.php?guncelleme=basarili");
                    exit;
                } else {
                    // Güncellenecek öğrenci bulunamadı (ya ID yanlış ya da başka bir okula ait)
                    $mesaj = '<p class="error">HATA: Öğrenci bulunamadı veya bu öğrenciyi düzenleme yetkiniz yok.</p>';
                }
            } else{
                $mesaj = '<p class="error">HATA: Öğrenci güncellenemedi (Öğrenci No daha önce kaydedilmiş olabilir).</p>';
            }
            $stmt->close();
        }
    }
} else {
    // SAYFAYI YÜKLEME (GET) İŞLEMİ
    if(isset($_GET["id"]) && !empty(trim($_GET["id"]))){
        $ogrenci_id = (int)trim($_GET["id"]);
        // SQL SORGUSU GÜNCELLENDİ: Sadece o okula ait öğrencinin bilgilerini getirir
        $sql = "SELECT ogrenci_no, ad, soyad, sinif FROM ogrenciler WHERE id = ? AND okul_id = ?";
        if($stmt = $mysqli->prepare($sql)){
            // bind_param GÜNCELLENDİ: "ii" oldu (okul_id eklendi)
            $stmt->bind_param("ii", $ogrenci_id, $okul_id);
            
            if($stmt->execute()){
                $stmt->store_result();
                if($stmt->num_rows == 1){
                    $stmt->bind_result($ogrenci_no, $ad, $soyad, $sinif);
                    $stmt->fetch();
                } else{
                    // Öğrenci ya yok ya da bu okula ait değil, her iki durumda da yönlendir
                    header("location: ogrenciler.php?hata=yetkisiz");
                    exit;
                }
            } else{
                $mesaj = '<p class="error">HATA: Sorgu çalıştırılamadı.</p>';
            }
            $stmt->close();
        }
    } else {
        header("location: ogrenciler.php");
        exit;
    }
}
$mysqli->close();
?>

<h1>Öğrenci Düzenle</h1>
<?php echo $mesaj; ?>
<div class="form-wrapper">
    <form action="ogrenci_duzenle.php" method="post">
        <div>
            <label>Öğrenci No (*zorunlu)</label>
            <input type="text" name="ogrenci_no" value="<?php echo htmlspecialchars($ogrenci_no); ?>" required>
        </div>
        <div>
            <label>Ad (*zorunlu)</label>
            <input type="text" name="ad" value="<?php echo htmlspecialchars($ad); ?>" required>
        </div>
        <div>
            <label>Soyad (*zorunlu)</label>
            <input type="text" name="soyad" value="<?php echo htmlspecialchars($soyad); ?>" required>
        </div>
        <div>
            <label>Sınıf</label>
            <input type="text" name="sinif" value="<?php echo htmlspecialchars($sinif); ?>" placeholder="Örn: 9/A, 11/C">
        </div>
        <br>
        <input type="hidden" name="id" value="<?php echo $ogrenci_id; ?>"/>
        <input type="submit" value="Bilgileri Güncelle">
    </form>
    <br>
    <a href="ogrenciler.php" style="color: #aaa; text-decoration: underline;">« Geri Dön</a>
</div>

<?php require_once 'footer.php'; ?>