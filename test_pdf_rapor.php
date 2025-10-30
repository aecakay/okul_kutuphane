<?php
// --- VERİTABANI SİMÜLASYONU VE FİLTRELEME (test_gecmis.php ile aynı) ---
$sampleData = [
    ['id' => 1, 'tarih' => '2025-10-01', 'aciklama' => 'Maaş Ödemesi', 'tip' => 'gelir', 'tutar' => 15000],
    ['id' => 2, 'tarih' => '2025-10-02', 'aciklama' => 'Market Alışverişi', 'tip' => 'gider', 'tutar' => 750],
    ['id' => 3, 'tarih' => '2025-10-05', 'aciklama' => 'Elektrik Faturası', 'tip' => 'gider', 'tutar' => 450],
    ['id' => 4, 'tarih' => '2025-10-10', 'aciklama' => 'Kira Ödemesi', 'tip' => 'gider', 'tutar' => 5000],
    ['id' => 5, 'tarih' => '2025-10-15', 'aciklama' => 'Proje Geliri (İş Bankası)', 'tip' => 'gelir', 'tutar' => 2500],
    ['id' => 6, 'tarih' => '2025-10-20', 'aciklama' => 'İnternet Faturası Ödemesi', 'tip' => 'gider', 'tutar' => 200],
    ['id' => 7, 'tarih' => '2025-11-01', 'aciklama' => 'Maaş Ödemesi', 'tip' => 'gelir', 'tutar' => 15000],
    ['id' => 8, 'tarih' => '2025-11-03', 'aciklama' => 'Akşam Yemeği Gideri', 'tip' => 'gider', 'tutar' => 800],
];

$baslangic = $_GET['baslangic'] ?? '2025-10-01';
$bitis = $_GET['bitis'] ?? '2025-11-30';
$arama = $_GET['arama'] ?? '';
$tip = $_GET['tip'] ?? 'tumu';

$filteredData = array_filter($sampleData, function($item) use ($baslangic, $bitis, $arama, $tip) {
    if (!empty($baslangic) && $item['tarih'] < $baslangic) return false;
    if (!empty($bitis) && $item['tarih'] > $bitis) return false;
    if (!empty($arama) && mb_stripos($item['aciklama'], $arama, 0, 'UTF-8') === false) return false;
    if ($tip !== 'tumu' && $item['tip'] !== $tip) return false;
    return true;
});


// --- PDF OLUŞTURMA (tFPDF ile) ---

// 1. tFPDF kütüphanesini dahil et
require('tfpdf/tfpdf.php');

// 2. tFPDF nesnesini oluştur (P = Portrait, mm = milimetre, A4 = sayfa boyutu)
$pdf = new tFPDF('P', 'mm', 'A4');

// 3. TÜRKÇE KARAKTERLER İÇİN FONT TANIMLA (EN ÖNEMLİ ADIM)
// 'DejaVu' -> Fontun adı (istediğini yazabilirsin)
// '' -> Stil (Boş = regular, B = bold, I = italic)
// 'DejaVuSans.ttf' -> Font dosyasının adı
// true -> Unicode subset'i göm (Gerekli)
$pdf->AddFont('DejaVu', '', 'DejaVuSans.ttf', true);
$pdf->AddFont('DejaVu', 'B', 'DejaVuSans-Bold.ttf', true); // Bold için

// 4. Yeni bir sayfa ekle ve fontu ayarla
$pdf->AddPage();
$pdf->SetFont('DejaVu', 'B', 16); // Kalın başlık fontu

// 5. PDF Başlığını Yazdır
$pdf->Cell(0, 10, 'İşlem Geçmişi Raporu', 0, 1, 'C'); // 0:Genişlik(0=otomatik), 10:Yükseklik, Metin, 0:Kenarlık, 1:Satır atla, C:Ortala
$pdf->Ln(10); // 10mm boşluk bırak

// 6. Tablo başlıklarını oluştur
$pdf->SetFont('DejaVu', 'B', 11);
$pdf->Cell(30, 10, 'Tarih', 1, 0, 'C');
$pdf->Cell(95, 10, 'Açıklama', 1, 0, 'C');
$pdf->Cell(25, 10, 'Tip', 1, 0, 'C');
$pdf->Cell(40, 10, 'Tutar', 1, 1, 'C'); // Son hücrede satır atla (1)

// 7. Verileri tabloya yazdır
$pdf->SetFont('DejaVu', '', 10);
foreach ($filteredData as $item) {
    $pdf->Cell(30, 8, date("d.m.Y", strtotime($item['tarih'])), 1);
    $pdf->Cell(95, 8, $item['aciklama'], 1);
    $pdf->Cell(25, 8, ($item['tip'] == 'gelir' ? 'Gelir' : 'Gider'), 1);
    $pdf->Cell(40, 8, number_format($item['tutar'], 2, ',', '.') . ' TL', 1, 1, 'R'); // Sağa yasla (R)
}

// 8. PDF dosyasını kullanıcıya gönder
// 'D' -> Download (İndirmeyi zorla)
// 'rapor.pdf' -> İndirilecek dosyanın adı
$pdf->Output('D', 'islem_raporu.pdf');
exit;
?>