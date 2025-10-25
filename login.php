<?php
// Oturumu başlat
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Eğer yönetici zaten giriş yapmışsa, doğrudan ana sayfaya yönlendir
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: islemler.php");
    exit;
}

require_once "config.php";

$username = $password = "";
$username_err = $password_err = $login_err = "";

if($_SERVER["REQUEST_METHOD"] == "POST"){
    if(empty(trim($_POST["username"]))){ $username_err = "Lütfen kullanıcı adınızı girin."; }
    else { $username = trim($_POST["username"]); }
    if(empty(trim($_POST["password"]))){ $password_err = "Lütfen şifrenizi girin."; }
    else { $password = trim($_POST["password"]); }

    if(empty($username_err) && empty($password_err)){
        // SQL SORGUSU GÜNCELLENDİ: Artık okul_id'yi de seçiyoruz
        $sql = "SELECT id, okul_id, kullanici_adi, sifre FROM yoneticiler WHERE kullanici_adi = ?";
        
        if($stmt = $mysqli->prepare($sql)){
            $stmt->bind_param("s", $param_username);
            $param_username = $username;
            
            if($stmt->execute()){
                $stmt->store_result();
                
                if($stmt->num_rows == 1){
                    // bind_result GÜNCELLENDİ: $okul_id eklendi
                    $stmt->bind_result($id, $okul_id, $username_db, $hashed_password);
                    if($stmt->fetch()){
                        if(password_verify($password, $hashed_password)){
                            // Oturum değişkenlerini ayarla
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["username"] = $username_db;
                            $_SESSION["okul_id"] = $okul_id; // EN ÖNEMLİ DEĞİŞİKLİK
                            
                            // Giriş başarılıysa, kullanıcıyı "Kitap Al / Ver" sayfasına yönlendir
                            header("location: islemler.php");
                        } else { 
                            $login_err = "Geçersiz kullanıcı adı veya şifre."; 
                        }
                    }
                } else { 
                    $login_err = "Geçersiz kullanıcı adı veya şifre."; 
                }
            } else { 
                $login_err = "HATA: Bir şeyler ters gitti."; 
            }
            $stmt->close();
        }
    }
    $mysqli->close();
}

// Header'ı en sona taşıdık ki yönlendirme (header) komutlarından önce bir HTML çıktısı olmasın
require_once 'header.php';
?>

<h2>Yönetici Girişi</h2>
    <?php if(!empty($login_err)){ echo '<p class="error">' . $login_err . '</p>'; } ?>
    
    <div class="form-wrapper">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group"> <label>Kullanıcı Adı</label>
                <input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>" autofocus>
                <?php if(!empty($username_err)){ echo '<p class="error">' . $username_err . '</p>'; } ?>
            </div>
            <div class="form-group"> <label>Şifre</label>
                <input type="password" name="password">
                <?php if(!empty($password_err)){ echo '<p class="error">' . $password_err . '</p>'; } ?>
            </div>
            <input type="submit" value="Giriş Yap">
        </form>
    </div>

<?php require_once 'footer.php'; ?>