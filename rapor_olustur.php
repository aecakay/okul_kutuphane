<?php
// Gerekli dosyaları dahil et
require_once 'config.php';
session_start();

// Yetki kontrolü (okul_id dahil)
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["okul_id"])) {
    die("Bu sayfaya erişim yetkiniz yok.");
}

// tFPDF kütüphanesini dahil et
require_once __DIR__ . '/tfpdf/tfpdf.php';

// Oturumdan mevcut okulun ID'sini al
$okul_id = $_SESSION["okul_id"];

// Okul adını veritabanından çek (okul_id'ye göre)
$okul_adi = '';
$sql_okul_adi = "SELECT okul_adi FROM okullar WHERE id = ?";
if($stmt_okul = $mysqli->prepare($sql_okul_adi)){
    $stmt_okul->bind_param("i", $okul_id);
    $stmt_okul->execute();
    $stmt_okul->bind_result($db_okul_adi);
    $stmt_okul->fetch();
    $okul_adi = $db_okul_adi;
    $stmt_okul->close();
}


// Rapor tipini ve veriyi al
$tip = $_GET['tip'] ?? '';
$ogrenci_id = $_GET['ogrenci_id'] ?? 0;
$sinif = $_GET['sinif'] ?? '';

$rapor_basligi = '';
$rapor_verisi = [];
$filename = 'rapor.pdf';

// 1. VERİLERİ ÇEKME (Tüm sorgular okul_id'ye göre güncellendi)
if ($tip === 'ogrenci' && $ogrenci_id > 0) {
    // Tek bir öğrencinin verilerini çek (sadece o okuldaysa)
    $sql = "SELECT O.ad, O.soyad, O.ogrenci_no, O.sinif, K.kitap_adi, K.yazar, I.odunc_tarihi, I.iade_tarihi
            FROM islemler I
            JOIN ogrenciler O ON I.ogrenci_id = O.id
            JOIN kitaplar K ON I.kitap_id = K.id
            WHERE O.id = ? AND I.okul_id = ?
            ORDER BY I.odunc_tarihi DESC";
    
    if($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("ii", $ogrenci_id, $okul_id);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($ad, $soyad, $ogrenci_no, $db_sinif, $kitap_adi, $yazar, $odunc_tarihi, $iade_tarihi);
        
        $is_first = true;
        while($stmt->fetch()) {
            if($is_first){
                $rapor_basligi = $ad . ' ' . $soyad . ' Okuma Karnesi';
                $filename = 'okuma_karnesi_' . preg_replace('/[^A-Za-z0-9\-]/', '', $ogrenci_no) . '.pdf';
                $is_first = false;
            }
            $rapor_verisi[] = ['ogrenci_no' => $ogrenci_no, 'ad' => $ad, 'soyad' => $soyad, 'sinif' => $db_sinif, 'kitap_adi' => $kitap_adi, 'yazar' => $yazar, 'odunc_tarihi' => $odunc_tarihi, 'iade_tarihi' => $iade_tarihi];
        }
        $stmt->close();
    }

} elseif ($tip === 'sinif' && !empty($sinif)) {
    // Bir sınıfın tüm verilerini çek (sadece o okuldaysa)
    $rapor_basligi = htmlspecialchars($sinif) . ' Sınıfı Okuma Raporu';
    $filename = 'sinif_raporu_' . preg_replace('/[^A-Za-z0-9\-]/', '', $sinif) . '.pdf';
    $sql = "SELECT O.ad, O.soyad, O.ogrenci_no, K.kitap_adi, I.odunc_tarihi
            FROM islemler I
            JOIN ogrenciler O ON I.ogrenci_id = O.id
            JOIN kitaplar K ON I.kitap_id = K.id
            WHERE O.sinif = ? AND I.okul_id = ?
            ORDER BY O.ad, O.soyad, I.odunc_tarihi DESC";

    if($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("si", $sinif, $okul_id);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($ad, $soyad, $ogrenci_no, $kitap_adi, $odunc_tarihi);
        while($stmt->fetch()) {
            $rapor_verisi[] = [ 'ad' => $ad, 'soyad' => $soyad, 'ogrenci_no' => $ogrenci_no, 'kitap_adi' => $kitap_adi, 'odunc_tarihi' => $odunc_tarihi ];
        }
        $stmt->close();
    }
} else {
    die("Geçersiz rapor tipi veya eksik bilgi.");
}

$mysqli->close();


// PDF Sınıfı
class PDF extends tFPDF
{
    function Header() { /* ... Bu kısım aynı ... */ }
    function Footer() { /* ... Bu kısım aynı ... */ }
}

// PDF OLUŞTURMA
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddFont('DejaVu', '', 'DejaVuSans.ttf', true);
$pdf->AddFont('DejaVu', 'B', 'DejaVuSans-Bold.ttf', true);
$pdf->AddFont('DejaVu', 'I', 'DejaVuSans-Oblique.ttf', true);
$pdf->AddPage();

if (empty($rapor_verisi)) {
    // ... (Boş rapor mesajı aynı)
} else {
    if ($tip === 'ogrenci') {
        // ... (Öğrenci raporu PDF oluşturma kodu aynı)
    } elseif ($tip === 'sinif') {
        // ... (Sınıf raporu PDF oluşturma kodu aynı)
    }
}

$pdf->Output('I', $filename);
exit;
?>