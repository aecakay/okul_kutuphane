<?php
// Hata raporlamayı açarak olası sorunları görelim
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'tfpdf/tfpdf.php'; // Türkçe karakter destekli PDF kütüphanesi
session_start();

// Güvenlik: Sadece giriş yapmış yöneticiler erişebilir
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["okul_id"])) {
    die("Bu sayfaya erişim yetkiniz bulunmamaktadır.");
}

$okul_id = $_SESSION["okul_id"];
$okul_adi_sorgu = $mysqli->query("SELECT okul_adi FROM okullar WHERE id = $okul_id");
$okul_adi = $okul_adi_sorgu->fetch_assoc()['okul_adi'] ?? 'Okul Adı Bulunamadı';

// PDF için özel sınıf tanımı
class PDF extends tFPDF
{
    private $okulAdi = '';
    private $raporBasligi = 'Kütüphane Raporu';

    function __construct($orientation='P', $unit='mm', $size='A4') {
        parent::__construct($orientation, $unit, $size);
        // Fontları en başta bir kez tanımla
        $this->AddFont('DejaVu', '', 'DejaVuSans.ttf', true);
        $this->AddFont('DejaVu', 'B', 'DejaVuSans-Bold.ttf', true);
    }

    function setOkulAdi($ad) { $this->okulAdi = $ad; }
    function setRaporBasligi($baslik) { $this->raporBasligi = $baslik; }

    function Header()
    {
        $this->SetFont('DejaVu', 'B', 14);
        $this->Cell(0, 10, $this->okulAdi, 0, 1, 'C');
        $this->SetFont('DejaVu', '', 12);
        $this->Cell(0, 10, $this->raporBasligi, 0, 1, 'C');
        $this->Ln(5);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('DejaVu', '', 8);
        $this->Cell(0, 10, 'Sayfa ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    function KarneKutusu($baslik, $data)
    {
        $this->SetFont('DejaVu', 'B', 12);
        $this->Cell(0, 10, $baslik, 0, 1, 'L');
        $this->SetFont('DejaVu', '', 9);
        $this->SetFillColor(245, 245, 245);
        $this->SetDrawColor(200, 200, 200);
        $lineHeight = 7;
        
        foreach ($data as $etiket => $deger) {
            $this->SetFont('DejaVu', 'B', 9);
            $this->Cell(60, $lineHeight, $etiket, 'LTB', 0, 'L', true);
            $this->SetFont('DejaVu', '', 9);
            $this->Cell(130, $lineHeight, $deger, 'RTB', 1, 'L');
        }
    }

    function BasicTable($header, $data)
    {
        $this->SetFillColor(230, 230, 230);
        $this->SetFont('DejaVu', 'B', 10);
        $w = [80, 50, 25, 30]; 
        foreach ($header as $i => $col) {
            $this->Cell($w[$i], 7, $col, 1, 0, 'C', true);
        }
        $this->Ln();

        $this->SetFont('DejaVu', '', 9);
        foreach ($data as $row) {
            $this->Cell($w[0], 6, $row[0], 1);
            $this->Cell($w[1], 6, $row[1], 1);
            $this->Cell($w[2], 6, $row[2], 1, 0, 'C');
            $this->Cell($w[3], 6, $row[3], 1, 0, 'C');
            $this->Ln();
        }
    }
}

// Formdan gelen verileri al
$rapor_tipi = $_POST['rapor_tipi'] ?? 'genel';
$ogrenci_id = (int)($_POST['ogrenci_id'] ?? 0);
$sinif = trim($_POST['sinif'] ?? '');
$bas_tarih = $_POST['bas_tarih'] ?? '';
$bit_tarih = $_POST['bit_tarih'] ?? '';

$rapor_basligi = "Genel Okuma Raporu";
$karne_data = [];
$karne_basligi = "";
$data = [];

$where_kosullari = ["I.okul_id = ?"];
$parametreler = [$okul_id];
$tipler = "i";

if ($rapor_tipi === 'ogrenci' && $ogrenci_id > 0) {
    $where_kosullari[] = "I.ogrenci_id = ?";
    $parametreler[] = $ogrenci_id;
    $tipler .= "i";
    
    $ogr_stmt = $mysqli->prepare("SELECT ad, soyad FROM ogrenciler WHERE id = ?");
    $ogr_stmt->bind_param("i", $ogrenci_id);
    $ogr_stmt->execute();
    $ogr_stmt->bind_result($ad, $soyad);
    $ogr_stmt->fetch();
    $rapor_basligi = ($ad . ' ' . $soyad) . ' Okuma Raporu';
    $karne_basligi = "Okuma Karnesi";
    $ogr_stmt->close();
    
    // --- ÖĞRENCİ İSTATİSTİK HESAPLAMALARI (UYUMLU KOD) ---
    $sql_stats = "SELECT MIN(I.odunc_tarihi), SUM(CASE WHEN I.iade_tarihi > I.son_iade_tarihi THEN 1 ELSE 0 END), SUM(K.sayfa_sayisi)
                  FROM islemler I JOIN kitaplar K ON I.kitap_id = K.id
                  WHERE I.ogrenci_id = ? AND I.okul_id = ? AND I.iade_tarihi IS NOT NULL";
    $stmt_stats = $mysqli->prepare($sql_stats);
    $stmt_stats->bind_param("ii", $ogrenci_id, $okul_id);
    $stmt_stats->execute();
    $stmt_stats->bind_result($ilk_tarih, $gecikme_sayisi, $toplam_sayfa);
    $stmt_stats->fetch();
    $stmt_stats->close();
    
    $karne_data['Okumaya Başlama Tarihi'] = $ilk_tarih ? date('d.m.Y', strtotime($ilk_tarih)) : '-';
    $karne_data['Toplam Okunan Sayfa'] = (int)($toplam_sayfa ?? 0);
    $karne_data['Toplam Gecikme Sayısı'] = (int)($gecikme_sayisi ?? 0);
    
    $sql_speed = "SELECT K.kitap_adi, DATEDIFF(I.iade_tarihi, I.odunc_tarihi) as okuma_suresi
                  FROM islemler I JOIN kitaplar K ON I.kitap_id = K.id
                  WHERE I.ogrenci_id = ? AND I.okul_id = ? AND I.iade_tarihi IS NOT NULL AND DATEDIFF(I.iade_tarihi, I.odunc_tarihi) >= 0";
    $stmt_speed = $mysqli->prepare($sql_speed);
    $stmt_speed->bind_param("ii", $ogrenci_id, $okul_id);
    $stmt_speed->execute();
    $stmt_speed->store_result();
    $stmt_speed->bind_result($kitap_adi_speed, $okuma_suresi);

    $okuma_sureleri = [];
    $en_hizli = ['sure' => 9999, 'kitap' => '-'];
    $en_yavas = ['sure' => 0, 'kitap' => '-'];
    while($stmt_speed->fetch()){
        $okuma_sureleri[] = (int)$okuma_suresi;
        if($okuma_suresi < $en_hizli['sure']) { $en_hizli = ['sure' => $okuma_suresi, 'kitap' => $kitap_adi_speed]; }
        if($okuma_suresi > $en_yavas['sure']) { $en_yavas = ['sure' => $okuma_suresi, 'kitap' => $kitap_adi_speed]; }
    }
    $stmt_speed->close();
    $karne_data['Ortalama Okuma Süresi'] = count($okuma_sureleri) > 0 ? round(array_sum($okuma_sureleri) / count($okuma_sureleri)) . ' gün' : '-';
    $karne_data['En Hızlı Okunan Kitap'] = $en_hizli['kitap'] . ' (' . ($en_hizli['sure'] < 9999 ? $en_hizli['sure'] : '?') . ' gün)';
    $karne_data['En Yavaş Okunan Kitap'] = $en_yavas['kitap'] . ' (' . $en_yavas['sure'] . ' gün)';
    
    $sql_yazar = "SELECT K.yazar, COUNT(K.yazar) as sayi FROM islemler I JOIN kitaplar K ON I.kitap_id = K.id
                  WHERE I.ogrenci_id = ? AND I.okul_id = ? AND K.yazar IS NOT NULL AND K.yazar != '' GROUP BY K.yazar ORDER BY sayi DESC LIMIT 1";
    $stmt_yazar = $mysqli->prepare($sql_yazar);
    $stmt_yazar->bind_param("ii", $ogrenci_id, $okul_id);
    $stmt_yazar->execute();
    $stmt_yazar->bind_result($fav_yazar, $yazar_sayi);
    $stmt_yazar->fetch();
    $stmt_yazar->close();
    $karne_data['Favori Yazar'] = $fav_yazar ? $fav_yazar . ' (' . $yazar_sayi . ' kitap)' : '-';

} elseif ($rapor_tipi === 'sinif' && !empty($sinif)) {
    $where_kosullari[] = "O.sinif = ?";
    $parametreler[] = $sinif;
    $tipler .= "s";
    $rapor_basligi = $sinif . ' Sınıfı Okuma Raporu';
    $karne_basligi = $sinif . ' Sınıf Karnesi';

    // --- SINIF İSTATİSTİK HESAPLAMALARI ---
    $sql_katilim = "SELECT COUNT(id) as toplam_ogrenci FROM ogrenciler WHERE sinif = ? AND okul_id = ?";
    $stmt_katilim = $mysqli->prepare($sql_katilim);
    $stmt_katilim->bind_param("si", $sinif, $okul_id);
    $stmt_katilim->execute();
    $stmt_katilim->bind_result($toplam_ogrenci);
    $stmt_katilim->fetch();
    $stmt_katilim->close();

    $sql_okuyan = "SELECT COUNT(DISTINCT I.ogrenci_id) FROM islemler I JOIN ogrenciler O ON I.ogrenci_id = O.id WHERE O.sinif = ? AND I.okul_id = ?";
    $stmt_okuyan = $mysqli->prepare($sql_okuyan);
    $stmt_okuyan->bind_param("si", $sinif, $okul_id);
    $stmt_okuyan->execute();
    $stmt_okuyan->bind_result($okuyan_ogrenci);
    $stmt_okuyan->fetch();
    $stmt_okuyan->close();

    $katilim_orani = ($toplam_ogrenci > 0) ? round(($okuyan_ogrenci / $toplam_ogrenci) * 100) . '%' : 'N/A';
    $karne_data['Okuma Katılım Oranı'] = $okuyan_ogrenci . ' / ' . $toplam_ogrenci . ' (' . $katilim_orani . ')';
    
    $sql_toplam_kitap = "SELECT COUNT(I.id) FROM islemler I JOIN ogrenciler O ON I.ogrenci_id = O.id WHERE O.sinif = ? AND I.okul_id = ?";
    $stmt_toplam_kitap = $mysqli->prepare($sql_toplam_kitap);
    $stmt_toplam_kitap->bind_param("si", $sinif, $okul_id);
    $stmt_toplam_kitap->execute();
    $stmt_toplam_kitap->bind_result($toplam_kitap);
    $stmt_toplam_kitap->fetch();
    $stmt_toplam_kitap->close();
    $karne_data['Öğrenci Başına Ortalama'] = ($toplam_ogrenci > 0) ? round($toplam_kitap / $toplam_ogrenci, 2) . ' Kitap' : 'N/A';
    
    $sql_kurtlar = "SELECT O.ad, O.soyad, COUNT(I.id) as kitap_sayisi FROM islemler I JOIN ogrenciler O ON I.ogrenci_id = O.id WHERE O.sinif = ? AND I.okul_id = ? GROUP BY I.ogrenci_id ORDER BY kitap_sayisi DESC LIMIT 3";
    $stmt_kurtlar = $mysqli->prepare($sql_kurtlar);
    $stmt_kurtlar->bind_param("si", $sinif, $okul_id);
    $stmt_kurtlar->execute();
    $stmt_kurtlar->store_result();
    $stmt_kurtlar->bind_result($ad_k, $soyad_k, $sayi_k);
    $kitap_kurtlari = [];
    while($stmt_kurtlar->fetch()){ $kitap_kurtlari[] = $ad_k . ' ' . $soyad_k . ' (' . $sayi_k . ')'; }
    $stmt_kurtlar->close();
    $karne_data['Sınıfın Kitap Kurtları'] = !empty($kitap_kurtlari) ? implode(', ', $kitap_kurtlari) : '-';

    $sql_pop_kitap = "SELECT K.kitap_adi, COUNT(I.id) as okuma_sayisi FROM islemler I JOIN kitaplar K ON I.kitap_id = K.id JOIN ogrenciler O ON I.ogrenci_id = O.id WHERE O.sinif = ? AND I.okul_id = ? GROUP BY I.kitap_id ORDER BY okuma_sayisi DESC LIMIT 1";
    $stmt_pop_kitap = $mysqli->prepare($sql_pop_kitap);
    $stmt_pop_kitap->bind_param("si", $sinif, $okul_id);
    $stmt_pop_kitap->execute();
    $stmt_pop_kitap->bind_result($pop_kitap, $pop_kitap_sayi);
    $stmt_pop_kitap->fetch();
    $stmt_pop_kitap->close();
    $karne_data['En Popüler Kitap'] = $pop_kitap ? $pop_kitap . ' (' . $pop_kitap_sayi . ' kez)' : '-';

    $sql_fav_yazar = "SELECT K.yazar, COUNT(I.id) as okuma_sayisi FROM islemler I JOIN kitaplar K ON I.kitap_id = K.id JOIN ogrenciler O ON I.ogrenci_id = O.id WHERE O.sinif = ? AND I.okul_id = ? AND K.yazar IS NOT NULL AND K.yazar != '' GROUP BY K.yazar ORDER BY okuma_sayisi DESC LIMIT 1";
    $stmt_fav_yazar = $mysqli->prepare($sql_fav_yazar);
    $stmt_fav_yazar->bind_param("si", $sinif, $okul_id);
    $stmt_fav_yazar->execute();
    $stmt_fav_yazar->bind_result($fav_yazar, $fav_yazar_sayi);
    $stmt_fav_yazar->fetch();
    $stmt_fav_yazar->close();
    $karne_data['Favori Yazar'] = $fav_yazar ? $fav_yazar . ' (' . $fav_yazar_sayi . ' kitap)' : '-';
}

if (!empty($bas_tarih)) { $where_kosullari[] = "I.odunc_tarihi >= ?"; $parametreler[] = $bas_tarih; $tipler .= "s"; }
if (!empty($bit_tarih)) { $where_kosullari[] = "I.odunc_tarihi <= ?"; $parametreler[] = $bit_tarih; $tipler .= "s"; }

// Ana liste sorgusu (UYUMLU KOD)
$sql = "SELECT K.kitap_adi, O.ogrenci_no, O.ad, O.soyad, I.odunc_tarihi, I.iade_tarihi
        FROM islemler I JOIN kitaplar K ON I.kitap_id = K.id JOIN ogrenciler O ON I.ogrenci_id = O.id
        WHERE " . implode(" AND ", $where_kosullari) . " ORDER BY I.id DESC";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param($tipler, ...$parametreler);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($kitap_adi, $ogrenci_no, $ad, $soyad, $odunc_tarihi, $iade_tarihi);

while ($stmt->fetch()) {
    $data[] = [
        $ogrenci_no . ' - ' . $ad . ' ' . $soyad,
        $kitap_adi,
        date('d.m.Y', strtotime($odunc_tarihi)),
        $iade_tarihi ? date('d.m.Y', strtotime($iade_tarihi)) : 'İade Edilmedi'
    ];
}
$stmt->close();
$mysqli->close();

// PDF oluşturma
$pdf = new PDF();
$pdf->setOkulAdi($okul_adi);
$pdf->setRaporBasligi($rapor_basligi);
$pdf->AliasNbPages();
$pdf->AddPage();

if (!empty($karne_data)) {
    $pdf->KarneKutusu($karne_basligi, $karne_data);
    $pdf->Ln(10);
}

$pdf->SetFont('DejaVu', 'B', 10);
$pdf->Cell(0, 6, 'İşlem Geçmişi', 0, 1, 'L');
$pdf->SetFont('DejaVu', '', 8);
$pdf->Cell(0, 6, 'Toplam ' . count($data) . ' işlem kaydı bulundu.', 0, 1, 'L');
$pdf->Ln(4);

$header = ['Öğrenci No / Ad Soyad', 'Kitap Adı', 'Alış Tarihi', 'İade Tarihi'];
if (!empty($data)) {
    $pdf->BasicTable($header, $data);
} else {
    $pdf->SetFont('DejaVu', '', 10);
    $pdf->Cell(0, 10, 'Bu filtrelere uygun kayıt bulunamadı.', 0, 1);
}

$pdf->Output('I', 'rapor.pdf', true);
exit;
?>