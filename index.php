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

// 1. TEMEL İSTATİSTİKLER (okul_id'ye göre)
$stmt = $mysqli->prepare("SELECT COUNT(id) FROM kitaplar WHERE okul_id = ?");
$stmt->bind_param("i", $okul_id);
$stmt->execute();
$stmt->bind_result($toplam_kitap_baslik);
$stmt->fetch();
$stmt->close();

$stmt = $mysqli->prepare("SELECT SUM(toplam_adet) FROM kitaplar WHERE okul_id = ?");
$stmt->bind_param("i", $okul_id);
$stmt->execute();
$stmt->bind_result($toplam_fiziksel_kitap);
$stmt->fetch();
$stmt->close();
$toplam_fiziksel_kitap = $toplam_fiziksel_kitap ?? 0;

$stmt = $mysqli->prepare("SELECT SUM(raftaki_adet) FROM kitaplar WHERE okul_id = ?");
$stmt->bind_param("i", $okul_id);
$stmt->execute();
$stmt->bind_result($raftaki_toplam_kitap);
$stmt->fetch();
$stmt->close();
$raftaki_toplam_kitap = $raftaki_toplam_kitap ?? 0;

$odunc_kitap_sayisi = $toplam_fiziksel_kitap - $raftaki_toplam_kitap;

$stmt = $mysqli->prepare("SELECT COUNT(id) FROM ogrenciler WHERE okul_id = ?");
$stmt->bind_param("i", $okul_id);
$stmt->execute();
$stmt->bind_result($toplam_ogrenci);
$stmt->fetch();
$stmt->close();

// 2. SON ÖDÜNÇ VERİLEN 5 KİTAP (get_result() KULLANILMAYAN YÖNTEM)
$son_islemler = [];
$sql_son_islemler = "SELECT K.kitap_adi, O.ad, O.soyad, I.odunc_tarihi
                     FROM islemler I 
                     JOIN kitaplar K ON I.kitap_id = K.id 
                     JOIN ogrenciler O ON I.ogrenci_id = O.id
                     WHERE I.okul_id = ?
                     ORDER BY I.id DESC LIMIT 5";
if($stmt_son = $mysqli->prepare($sql_son_islemler)) {
    $stmt_son->bind_param("i", $okul_id);
    $stmt_son->execute();
    $stmt_son->store_result();
    $stmt_son->bind_result($kitap_adi, $ad, $soyad, $odunc_tarihi);
    while($stmt_son->fetch()) {
        $son_islemler[] = ['kitap_adi' => $kitap_adi, 'ad' => $ad, 'soyad' => $soyad, 'odunc_tarihi' => $odunc_tarihi];
    }
    $stmt_son->close();
}


// 3. EN ÇOK OKUNAN 5 KİTAP BAŞLIĞI (get_result() KULLANILMAYAN YÖNTEM)
$cok_okunanlar = [];
$sql_cok_okunan = "SELECT K.kitap_adi, COUNT(I.id) AS okuma_sayisi
                   FROM islemler I 
                   JOIN kitaplar K ON I.kitap_id = K.id
                   WHERE I.okul_id = ?
                   GROUP BY I.kitap_id ORDER BY okuma_sayisi DESC LIMIT 5";
if($stmt_okunan = $mysqli->prepare($sql_cok_okunan)){
    $stmt_okunan->bind_param("i", $okul_id);
    $stmt_okunan->execute();
    $stmt_okunan->store_result();
    $stmt_okunan->bind_result($kitap_adi, $okuma_sayisi);
    while($stmt_okunan->fetch()) {
        $cok_okunanlar[] = ['kitap_adi' => $kitap_adi, 'okuma_sayisi' => $okuma_sayisi];
    }
    $stmt_okunan->close();
}

// 4. EN ÇOK KİTAP OKUYAN 5 ÖĞRENCİ (get_result() KULLANILMAYAN YÖNTEM)
$cok_okuyanlar = [];
$sql_cok_okuyan = "SELECT O.ad, O.soyad, COUNT(I.id) AS kitap_sayisi
                   FROM islemler I 
                   JOIN ogrenciler O ON I.ogrenci_id = O.id
                   WHERE I.okul_id = ?
                   GROUP BY I.ogrenci_id ORDER BY kitap_sayisi DESC LIMIT 5";
if($stmt_okuyan = $mysqli->prepare($sql_cok_okuyan)){
    $stmt_okuyan->bind_param("i", $okul_id);
    $stmt_okuyan->execute();
    $stmt_okuyan->store_result();
    $stmt_okuyan->bind_result($ad, $soyad, $kitap_sayisi);
    while($stmt_okuyan->fetch()) {
        $cok_okuyanlar[] = ['ad' => $ad, 'soyad' => $soyad, 'kitap_sayisi' => $kitap_sayisi];
    }
    $stmt_okuyan->close();
}
?>

<h1>Yönetim Paneli</h1>
<p style="font-size: 1.2em;">Hoş geldiniz, <?php echo htmlspecialchars($_SESSION["username"]); ?>!</p>

<div class="dashboard">
    <div class="box"> <h2><?php echo $toplam_kitap_baslik; ?></h2> <p>Farklı Kitap Başlığı</p> </div>
    <div class="box"> <h2><?php echo $toplam_fiziksel_kitap; ?></h2> <p>Toplam Kitap Adedi</p> </div>
    <div class="box"> <h2><?php echo $toplam_ogrenci; ?></h2> <p>Kayıtlı Öğrenci</p> </div>
    <div class="box"> <h2><?php echo $odunc_kitap_sayisi; ?></h2> <p>Ödünçteki Kitap Adedi</p> </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; margin-top: 40px;">

    <div class="dashboard-list-section"> <h3>Son Ödünç Verilenler</h3>
        <?php if(!empty($son_islemler)): ?>
            <ul class="dashboard-list"> <?php foreach($son_islemler as $row): ?>
                    <li> <strong><?php echo htmlspecialchars($row['kitap_adi']); ?></strong>
                        <span class="item-meta"> <?php echo htmlspecialchars($row['ad'] . ' ' . $row['soyad']); ?> - <?php echo date("d.m.Y", strtotime($row['odunc_tarihi'])); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>Henüz ödünç verme işlemi yapılmamış.</p>
        <?php endif; ?>
    </div>

    <div class="dashboard-list-section"> <h3>En Çok Okunan Kitap Başlıkları</h3>
        <?php if(!empty($cok_okunanlar)): ?>
             <ol class="dashboard-ordered-list"> <?php foreach($cok_okunanlar as $row): ?>
                    <li>
                        <?php echo htmlspecialchars($row['kitap_adi']); ?> 
                        <span>(<?php echo $row['okuma_sayisi']; ?> kez)</span> </li>
                <?php endforeach; ?>
            </ol>
        <?php else: ?>
            <p>Henüz yeterli veri yok.</p>
        <?php endif; ?>
    </div>

    <div class="dashboard-list-section"> <h3>En Çok Kitap Alan Öğrenciler</h3>
        <?php if(!empty($cok_okuyanlar)): ?>
            <ol class="dashboard-ordered-list"> <?php foreach($cok_okuyanlar as $row): ?>
                    <li>
                        <?php echo htmlspecialchars($row['ad'] . ' ' . $row['soyad']); ?> 
                        <span>(<?php echo $row['kitap_sayisi']; ?> kitap)</span> </li>
                <?php endforeach; ?>
            </ol>
        <?php else: ?>
            <p>Henüz yeterli veri yok.</p>
        <?php endif; ?>
    </div>

</div>
<?php
if(isset($mysqli) && $mysqli instanceof mysqli && !$mysqli->connect_errno) {
    $mysqli->close();
}
require_once 'footer.php';
?>