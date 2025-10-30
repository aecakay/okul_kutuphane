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

<div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); margin-bottom: 1.5rem;">
    <h1 style="border-bottom: none; margin-bottom: 0; padding-bottom: 0.75rem;">Geciken Kitaplar Raporu</h1>
    <a href="rapor_gecikenler_pdf.php" target="_blank" class="button-link" style="padding: 0.5rem 1rem; font-size: 0.9rem;">
        <i class="fas fa-file-pdf fa-fw"></i> PDF Rapor Al
    </a>
</div>

<table>
    <thead>
        <tr>
            <th>Sınıf</th>
            <th>Öğrenci No</th>
            <th>Öğrenci Ad Soyad</th>
            <th>Kitap Adı</th>
            <th>Ödünç Tarihi</th>
            <th>Son İade Tarihi</th>
            <th>Kaç Gün Gecikti?</th>
        </tr>
    </thead>
    <tbody>
    <?php
    // DÜZELTME BURADA: ogrenci_no'ya "+ 0" eklenerek sayısal sıralama yapılması sağlandı.
    $sql = "SELECT
                ogrenciler.sinif,
                ogrenciler.ogrenci_no,
                ogrenciler.ad,
                ogrenciler.soyad,
                kitaplar.kitap_adi,
                islemler.odunc_tarihi,
                islemler.son_iade_tarihi,
                DATEDIFF(CURDATE(), islemler.son_iade_tarihi) AS gun_farki
            FROM islemler
            JOIN kitaplar ON islemler.kitap_id = kitaplar.id
            JOIN ogrenciler ON islemler.ogrenci_id = ogrenciler.id
            WHERE
                islemler.okul_id = ?
                AND islemler.iade_tarihi IS NULL
                AND islemler.son_iade_tarihi < CURDATE()
            ORDER BY
                ogrenciler.sinif ASC, ogrenciler.ogrenci_no + 0 ASC";

    if($stmt = $mysqli->prepare($sql)){
        $stmt->bind_param("i", $okul_id);
        $stmt->execute();
        $stmt->store_result();
        
        if($stmt->num_rows > 0){
            $stmt->bind_result($sinif, $ogrenci_no, $ad, $soyad, $kitap_adi, $odunc_tarihi, $son_iade_tarihi, $gun_farki);
            while($stmt->fetch()){
                echo "<tr>";
                echo "<td>" . htmlspecialchars($sinif) . "</td>";
                echo "<td>" . htmlspecialchars($ogrenci_no) . "</td>";
                echo "<td>" . htmlspecialchars($ad . ' ' . $soyad) . "</td>";
                echo "<td>" . htmlspecialchars($kitap_adi) . "</td>";
                echo "<td style='text-align: center;'>" . date("d.m.Y", strtotime($odunc_tarihi)) . "</td>";
                echo "<td style='color: #ff8c8c; font-weight: bold; text-align: center;'>" . date("d.m.Y", strtotime($son_iade_tarihi)) . "</td>";
                echo "<td style='color: #ff8c8c; font-weight: bold; text-align: center;'>" . htmlspecialchars($gun_farki) . " gün</td>";
                echo "</tr>";
            }
        } else {
            echo '<tr><td colspan="7">Gecikmiş kitap bulunmamaktadır.</td></tr>';
        }
        $stmt->close();
    } else {
         echo '<tr><td colspan="7">HATA: Sorgu çalıştırılamadı. ' . $mysqli->error . '</td></tr>';
    }
    
    if(isset($mysqli) && !$mysqli->connect_errno) {
        $mysqli->close();
    }
    ?>
    </tbody>
</table>

<?php require_once 'footer.php'; ?>