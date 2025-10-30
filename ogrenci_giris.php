<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Eğer öğrenci zaten giriş yapmışsa, doğrudan panele yönlendir
if(isset($_SESSION["student_loggedin"]) && $_SESSION["student_loggedin"] === true){
    header("location: ogrenci_panel.php");
    exit;
}

require_once "config.php";

// Okul listesini veritabanından çek
$okullar = [];
$sql_okullar = "SELECT id, okul_adi FROM okullar ORDER BY okul_adi";
if($result = $mysqli->query($sql_okullar)){
    while($row = $result->fetch_assoc()){
        $okullar[] = $row;
    }
    $result->free();
}

$okul_id = 0;
$ogrenci_no = "";
$login_err = "";

if($_SERVER["REQUEST_METHOD"] == "POST"){
    
    // Form verilerini doğrula
    if(empty($_POST["okul_id"])){
        $login_err = "Lütfen okulunuzu seçin.";
    } else {
        $okul_id = (int)$_POST["okul_id"];
    }

    if(empty(trim($_POST["ogrenci_no"]))){
        $login_err = "Lütfen öğrenci numaranızı girin.";
    } else {
        $ogrenci_no = trim($_POST["ogrenci_no"]);
    }

    if(empty($login_err)){
        // SQL SORGUSU GÜNCELLENDİ: Artık hem okul_id hem de ogrenci_no kontrol ediliyor
        $sql = "SELECT id, ad, soyad FROM ogrenciler WHERE okul_id = ? AND ogrenci_no = ?";
        
        if($stmt = $mysqli->prepare($sql)){
            $stmt->bind_param("is", $okul_id, $ogrenci_no);
            
            if($stmt->execute()){
                $stmt->store_result();
                
                if($stmt->num_rows == 1){
                    $stmt->bind_result($student_id, $ad, $soyad);
                    if($stmt->fetch()){
                        // Oturum değişkenlerini ayarla
                        $_SESSION["student_loggedin"] = true;
                        $_SESSION["student_id"] = $student_id;
                        $_SESSION["student_okul_id"] = $okul_id;
                        $_SESSION["student_ad_soyad"] = $ad . ' ' . $soyad;
                        
                        header("location: ogrenci_panel.php");
                    }
                } else { 
                    $login_err = "Geçersiz okul seçimi veya öğrenci numarası."; 
                }
            } else { 
                $login_err = "Bir hata oluştu. Lütfen tekrar deneyin."; 
            }
            $stmt->close();
        }
    }
    $mysqli->close();
}

require_once 'header.php';
?>

<h2>Öğrenci Girişi</h2>
    <?php if(!empty($login_err)){ echo '<p class="error">' . htmlspecialchars($login_err) . '</p>'; } ?>
    
    <div class="form-wrapper">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label>Okulunuzu Seçin</label>
                <select name="okul_id" id="okulSecimi" required>
                    <option value="">Lütfen bir okul seçin...</option>
                    <?php foreach($okullar as $okul): ?>
                        <option value="<?php echo $okul['id']; ?>" <?php if($okul_id == $okul['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($okul['okul_adi']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Öğrenci Numaranız</label>
                <input type="text" name="ogrenci_no" value="<?php echo htmlspecialchars($ogrenci_no); ?>" autofocus required>
            </div>
            
            <input type="submit" value="Giriş Yap">
        </form>
    </div>
    
<script>
document.addEventListener('DOMContentLoaded', function() {
    const okulSecimiDropdown = document.getElementById('okulSecimi');
    const okulAdiElementi = document.querySelector('.school-name');

    okulSecimiDropdown.addEventListener('change', function() {
        const secilenOkulAdi = this.options[this.selectedIndex].text;
        if (this.value !== "" && okulAdiElementi) {
            okulAdiElementi.textContent = secilenOkulAdi;
        } else if (okulAdiElementi) {
            okulAdiElementi.textContent = 'Okul Seçilmedi';
        }
    });

    if (okulSecimiDropdown.value !== "" && okulAdiElementi) {
        okulAdiElementi.textContent = okulSecimiDropdown.options[okulSecimiDropdown.selectedIndex].text;
    }
});
</script>

<?php require_once 'footer.php'; ?>