<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: islemler.php"); // Yönlendirme artık islemler.php'ye
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
        $sql = "SELECT id, okul_id, kullanici_adi, sifre FROM yoneticiler WHERE kullanici_adi = ?";
        
        if($stmt = $mysqli->prepare($sql)){
            $stmt->bind_param("s", $param_username);
            $param_username = $username;
            
            if($stmt->execute()){
                $stmt->store_result();
                
                if($stmt->num_rows == 1){
                    $stmt->bind_result($id, $okul_id_db, $username_db, $hashed_password);
                    if($stmt->fetch()){
                        if(password_verify($password, $hashed_password)){
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["username"] = $username_db;
                            $_SESSION["okul_id"] = $okul_id_db;
                            
                            // DEĞİŞİKLİK BURADA
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

require_once 'header.php';
?>

<h2>Yönetici Girişi</h2>
    <?php if(!empty($login_err)){ echo '<p class="error">' . htmlspecialchars($login_err) . '</p>'; } ?>
    
    <div class="form-wrapper">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label>Kullanıcı Adı</label>
                <input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>" autofocus required>
                <?php if(!empty($username_err)){ echo '<p class="error">' . htmlspecialchars($username_err) . '</p>'; } ?>
            </div>
            <div class="form-group">
                <label>Şifre</label>
                <input type="password" name="password" required>
                <?php if(!empty($password_err)){ echo '<p class="error">' . htmlspecialchars($password_err) . '</p>'; } ?>
            </div>
            <input type="submit" value="Giriş Yap">
        </form>
    </div>

<?php require_once 'footer.php'; ?>