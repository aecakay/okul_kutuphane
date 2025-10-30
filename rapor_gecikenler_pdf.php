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

    // YENİ DÜZENLEME: Tablo fonksiyonu güncellendi
    function OverdueTable($header, $data)
    {
        $this->SetFillColor(230, 230, 230);
        $this->SetFont('DejaVu', 'B', 9);
        // Yeni sütun genişlikleri (Sınıf, No, Ad Soyad, Kitap Adı, Alış, İade, Gecikme)
        $w = [20, 22, 40, 50, 20, 20, 18]; 
        foreach ($header as $i => $col) {
            $this->Cell($w[$i], 7, $col, 1, 0, 'C', true);
        }
        $this->Ln();

        $this->SetFont('DejaVu', '', 8);
        foreach ($data as $row) {
            $this->Cell($w[0], 6, $row[0], 1, 0, 'C'); // Sınıf
            $this->Cell($w[1], 6, $row[1], 1, 0, 'C'); // Öğrenci No
            $this->Cell($w[2], 6, $row[2], 1);          // Ad Soyad
            $this->Cell($w[3], 6, $row[3], 1);          // Kitap Adı
            $this->Cell($w[4], 6, $row[4], 1, 0, 'C'); // Alış Tarihi
            $this->Cell($w[5], 6, $row[5], 1, 0, 'C'); // İade Tarihi
            $this->Cell($w[6], 6, $row[6], 1, 0, 'C'); // Gecikme
            $this->Ln();
        }
    }
}

// Veri Çekme
$data = [];
// YENİ DÜZENLEME: SQL Sorgusu gecikenler.php ile aynı olacak şekilde güncellendi
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
            // YENİ DÜZENLEME: Data dizisi yeni sıraya göre dolduruldu
            $data[] = [
                $sinif,
                $ogrenci_no,
                $ad . ' ' . $soyad,
                $kitap_adi,
                date("d.m.Y", strtotime($odunc_tarihi)),
                date("d.m.Y", strtotime($son_iade_tarihi)),
                $gun_farki . ' gün'
            ];
        }
    }
    $stmt->close();
}
$mysqli->close();

// PDF oluşturma
$pdf = new PDF();
$pdf->setOkulAdi($okul_adi);
$pdf->setRaporBasligi("Geciken Kitaplar Raporu");
$pdf->AliasNbPages();
$pdf->AddPage();

$pdf->SetFont('DejaVu', '', 10);
$pdf->Cell(0, 6, 'Rapor Tarihi: ' . date('d.m.Y'), 0, 1, 'L');
$pdf->Cell(0, 6, 'Toplam ' . count($data) . ' adet gecikmiş kitap bulunmaktadır.', 0, 1, 'L');
$pdf->Ln(4);

// YENİ DÜZENLEME: Başlıklar yeni sıraya göre güncellendi
$header = ['Sınıf', 'Öğr. No', 'Ad Soyad', 'Kitap Adı', 'Alış T.', 'İade T.', 'Gecikme'];
if (!empty($data)) {
    $pdf->OverdueTable($header, $data);
} else {
    $pdf->SetFont('DejaVu', '', 12);
    $pdf->Cell(0, 10, 'Gecikmiş kitap bulunmamaktadır.', 0, 1, 'C');
}

$pdf->Output('I', 'geciken_kitaplar_raporu.pdf', true);
exit;
?>