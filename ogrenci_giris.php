<?php
// Oturum başlatılıyor
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Eğer öğrenci zaten giriş yapmışsa, doğrudan panele yönlendir
if (isset($_SESSION["student_loggedin"]) && $_SESSION["student_loggedin"] === true) {
    header("location: ogrenci_panel.php");
    exit;
}

require_once "config.php";

$ogrenci_no = "";
$login_err = "";

// Form gönderildiğinde
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty(trim($_POST["ogrenci_no"]))) {
        $login_err = "Lütfen okul numaranızı girin.";
    } else {
        $ogrenci_no = trim($_POST["ogrenci_no"]);
    }

    if (empty($login_err)) {
        // SQL SORGUSU GÜNCELLENDİ: Artık okul_id'yi de seçiyoruz
        $sql = "SELECT id, okul_id, ad, soyad FROM ogrenciler WHERE ogrenci_no = ?";

        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("s", $ogrenci_no);
            
            if ($stmt->execute()) {
                $stmt->store_result();
                
                // Öğrenci bulunduysa
                if ($stmt->num_rows == 1) {
                    // bind_result GÜNCELLENDİ: $okul_id eklendi
                    $stmt->bind_result($id, $okul_id, $ad, $soyad);
                    if ($stmt->fetch()) {
                        // Oturum değişkenlerini ayarla
                        $_SESSION["student_loggedin"] = true;
                        $_SESSION["student_id"] = $id;
                        $_SESSION["student_okul_id"] = $okul_id; // EN ÖNEMLİ DEĞİŞİKLİK
                        $_SESSION["student_ogrenci_no"] = $ogrenci_no;
                        $_SESSION["student_ad_soyad"] = $ad . ' ' . $soyad;

                        // Öğrenci paneline yönlendir
                        header("location: ogrenci_panel.php");
                    }
                } else {
                    // Öğrenci bulunamadı
                    $login_err = "Bu okul numarasına sahip bir öğrenci bulunamadı.";
                }
            } else {
                $login_err = "Bir hata oluştu. Lütfen tekrar deneyin.";
            }
            $stmt->close();
        }
    }
    $mysqli->close();
}

// Header'ı en sona taşıdık ki yönlendirme komutlarından önce bir HTML çıktısı olmasın
require_once 'header.php'; 
?>

<h2>Öğrenci Girişi</h2>
<p>Ödünç aldığınız kitapları görmek için okul numaranızla giriş yapın.</p>

<?php 
if(!empty($login_err)){
    echo '<p class="error">' . $login_err . '</p>';
} 
?>
    
<div class="form-wrapper" style="max-width: 400px; margin-left: auto; margin-right: auto;">
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="form-group">
            <label>Okul Numaranız</label>
            <input type="text" name="ogrenci_no" value="<?php echo htmlspecialchars($ogrenci_no); ?>" autofocus>
        </div>
        <input type="submit" value="Giriş Yap">
    </form>
</div>

<?php require_once 'footer.php'; ?>