<?php
// Oturumu başlat (zaten aktif değilse)
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once "config.php"; // Veritabanı bağlantısını aç

$okul_adi = 'Kütüphane Yönetim Sistemi'; // Varsayılan başlık

// Hangi oturumun aktif olduğunu kontrol et ve Okul ID'sini belirle
$target_okul_id = null;
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true && isset($_SESSION["okul_id"])) {
    // Yönetici oturumu
    $target_okul_id = $_SESSION["okul_id"];
} elseif (isset($_SESSION["student_loggedin"]) && $_SESSION["student_loggedin"] === true && isset($_SESSION["student_okul_id"])) {
    // Öğrenci oturumu KONTROLÜ EKLENDİ
    $target_okul_id = $_SESSION["student_okul_id"];
}

// Eğer geçerli bir okul ID'si bulunduysa, adı veritabanından çek
if ($target_okul_id !== null) {
    $okul_id = $target_okul_id;
    $sql_okul_adi = "SELECT okul_adi FROM okullar WHERE id = ?";
    
    if($stmt = $mysqli->prepare($sql_okul_adi)){
        $stmt->bind_param("i", $okul_id);
        if($stmt->execute()){
            $stmt->store_result();
            if($stmt->num_rows == 1){
                $stmt->bind_result($db_okul_adi);
                $stmt->fetch();
                $okul_adi = $db_okul_adi; // Okul adını başarıyla aldık
            }
        }
        $stmt->close();
    }
} 


// Aktif sayfanın dosya adını al
$current_page = basename($_SERVER['PHP_SELF']);
$active_map = [
    'index.php' => 'islemler.php', 'islemler.php' => 'islemler.php',
    'kitaplar.php' => 'kitaplar.php', 'kitap_duzenle.php' => 'kitaplar.php',
    'ogrenciler.php' => 'ogrenciler.php', 'ogrenci_duzenle.php' => 'ogrenciler.php',
    'gecikenler.php' => 'gecikenler.php', 'gecmis.php' => 'gecmis.php',
    'raporlar.php' => 'raporlar.php',
    'pdf_rapor.php' => 'pdf_rapor.php',
    'rezervasyonlar.php' => 'rezervasyonlar.php',
    'sinif_atlat.php' => 'sinif_atlat.php', 
    'ayarlar.php' => 'ayarlar.php', 'katalog.php' => 'katalog.php',
    'kitap_detay.php' => 'katalog.php', 'login.php' => 'login.php',
    'ogrenci_giris.php' => 'ogrenci_giris.php', 'ogrenci_panel.php' => 'ogrenci_giris.php',
    'okul_yonetimi.php' => 'okul_yonetimi.php',
    'yonetici_yonetimi.php' => 'yonetici_yonetimi.php'
];
$active_menu_item = $active_map[$current_page] ?? '';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RafTertip - <?php echo htmlspecialchars($okul_adi); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/@zxing/library@0.20.0/umd/index.min.js"></script>
</head>
<body>

    <div class="container">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-content">
                <div class="profile-header">
                    <img src="logo.png" alt="Logo" class="logo" onerror="this.style.display='none'">
                    <div class="title-container">
                        <p class="name">RafTertip</p>
                        <p class="website">Kütüphane Yönetim Sistemi</p>
                        <p class="school-name"><?php echo htmlspecialchars($okul_adi); ?></p>
                    </div>
                </div>
                
                <nav id="nav-menu">
                    <?php if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
                        
                        <a href="islemler.php" class="<?php echo ($active_menu_item == 'islemler.php') ? 'active' : ''; ?>"> <i class="fas fa-handshake fa-fw"></i><span>Kitap Al / Ver</span> </a>
                        <a href="rezervasyonlar.php" class="<?php echo ($active_menu_item == 'rezervasyonlar.php') ? 'active' : ''; ?>"> <i class="fas fa-clock fa-fw"></i><span>Rezervasyonlar</span> </a>
                        <a href="gecikenler.php" class="<?php echo ($active_menu_item == 'gecikenler.php') ? 'active' : ''; ?>" style="color: #ffc107;"> <i class="fas fa-exclamation-triangle fa-fw"></i><span>Gecikenler</span> </a>
                        <a href="gecmis.php" class="<?php echo ($active_menu_item == 'gecmis.php') ? 'active' : ''; ?>"> <i class="fas fa-history fa-fw"></i><span>İşlem Geçmişi</span> </a>
                        <a href="raporlar.php" class="<?php echo ($active_menu_item == 'raporlar.php') ? 'active' : ''; ?>"> <i class="fas fa-chart-pie fa-fw"></i><span>Grafik Raporlar</span> </a>
                        <a href="pdf_rapor.php" class="<?php echo ($active_menu_item == 'pdf_rapor.php') ? 'active' : ''; ?>"> <i class="fas fa-file-pdf fa-fw"></i><span>PDF Rapor Oluştur</span> </a>
                        
                        <hr style="border-color: rgba(255,255,255,0.1); margin: 10px 15px;">

                        <a href="ogrenciler.php" class="<?php echo ($active_menu_item == 'ogrenciler.php') ? 'active' : ''; ?>"> <i class="fas fa-users fa-fw"></i><span>Öğrenci Yönetimi</span> </a>
                        <a href="kitaplar.php" class="<?php echo ($active_menu_item == 'kitaplar.php') ? 'active' : ''; ?>"> <i class="fas fa-book fa-fw"></i><span>Kitap Yönetimi</span> </a>

                        <hr style="border-color: rgba(255,255,255,0.1); margin: 10px 15px;">

                        <?php if(isset($_SESSION["username"]) && $_SESSION["username"] === 'admin'): ?>
                            <a href="okul_yonetimi.php" class="<?php echo ($active_menu_item == 'okul_yonetimi.php') ? 'active' : ''; ?>"> <i class="fas fa-building fa-fw"></i><span>Okul Yönetimi</span> </a>
                            <a href="yonetici_yonetimi.php" class="<?php echo ($active_menu_item == 'yonetici_yonetimi.php') ? 'active' : ''; ?>"> <i class="fas fa-users-cog fa-fw"></i><span>Yönetici Hesapları</span> </a>
                        <?php endif; ?>
                        
                        <a href="sinif_atlat.php" class="<?php echo ($active_menu_item == 'sinif_atlat.php') ? 'active' : ''; ?>"> <i class="fas fa-arrow-up-right-from-square fa-fw"></i><span>Sınıf Atlatma</span> </a>
                        <a href="ayarlar.php" class="<?php echo ($active_menu_item == 'ayarlar.php') ? 'active' : ''; ?>"> <i class="fas fa-cog fa-fw"></i><span>Ayarlar</span> </a>
                        <a href="logout.php"> <i class="fas fa-sign-out-alt fa-fw"></i><span>Çıkış Yap</span> </a>

                    <?php else: ?>
                        <a href="ogrenci_giris.php" class="<?php echo ($active_menu_item == 'ogrenci_giris.php') ? 'active' : ''; ?>"> <i class="fas fa-user-graduate fa-fw"></i><span>Öğrenci Girişi</span> </a>
                        <a href="login.php" class="<?php echo ($active_menu_item == 'login.php') ? 'active' : ''; ?>"> <i class="fas fa-sign-in-alt fa-fw"></i><span>Yönetici Girişi</span> </a>
                    <?php endif; ?>
                </nav>
            </div>
        </aside>
        
        <main class="content" id="main-content">