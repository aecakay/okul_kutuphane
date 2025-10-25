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
?>

<h1>Geciken Kitaplar Raporu</h1>
<table>
    <thead>
        <tr>
            <th>Kitap Adı</th>
            <th>Öğrenci No</th>
            <th>Öğrenci Ad Soyad</th>
            <th>Ödünç Tarihi</th>
            <th>Son İade Tarihi</th>
            <th>Kaç Gün Gecikti?</th>
        </tr>
    </thead>
    <tbody>
    <?php
    // SQL SORGUSU GÜNCELLENDİ: Hem okul_id filtresi eklendi hem de get_result() kaldırıldı
    $sql = "SELECT
                kitaplar.kitap_adi, ogrenciler.ogrenci_no, ogrenciler.ad, ogrenciler.soyad,
                islemler.odunc_tarihi, islemler.son_iade_tarihi,
                DATEDIFF(CURDATE(), islemler.son_iade_tarihi) AS gun_farki
            FROM islemler
            JOIN kitaplar ON islemler.kitap_id = kitaplar.id
            JOIN ogrenciler ON islemler.ogrenci_id = ogrenciler.id
            WHERE
                islemler.okul_id = ?
                AND islemler.iade_tarihi IS NULL
                AND islemler.son_iade_tarihi < CURDATE()
            ORDER BY islemler.son_iade_tarihi ASC";

    if($stmt = $mysqli->prepare($sql)){
        $stmt->bind_param("i", $okul_id);
        $stmt->execute();
        $stmt->store_result();
        
        if($stmt->num_rows > 0){
            $stmt->bind_result($kitap_adi, $ogrenci_no, $ad, $soyad, $odunc_tarihi, $son_iade_tarihi, $gun_farki);
            while($stmt->fetch()){
                echo "<tr>";
                echo "<td>" . htmlspecialchars($kitap_adi) . "</td>";
                echo "<td>" . htmlspecialchars($ogrenci_no) . "</td>";
                echo "<td>" . htmlspecialchars($ad . ' ' . $soyad) . "</td>";
                echo "<td style='text-align: center;'>" . date("d.m.Y", strtotime($odunc_tarihi)) . "</td>";
                echo "<td style='color: #ff8c8c; font-weight: bold; text-align: center;'>" . date("d.m.Y", strtotime($son_iade_tarihi)) . "</td>";
                echo "<td style='color: #ff8c8c; font-weight: bold; text-align: center;'>" . htmlspecialchars($gun_farki) . " gün</td>";
                echo "</tr>";
            }
        } else {
            echo '<tr><td colspan="6">Gecikmiş kitap bulunmamaktadır.</td></tr>';
        }
        $stmt->close();
    } else {
         echo '<tr><td colspan="6">HATA: Sorgu çalıştırılamadı. ' . $mysqli->error . '</td></tr>';
    }
    
    if(isset($mysqli) && !$mysqli->connect_errno) {
        $mysqli->close();
    }
    ?>
    </tbody>
</table>

<?php require_once 'footer.php'; ?>