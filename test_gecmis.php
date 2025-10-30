<?php
// --- VERİTABANI SİMÜLASYONU ---
// Gerçek projede bu veriler veritabanından gelir.
// Türkçe karakterler içerdiğine dikkat edin.
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

// --- FİLTRE DEĞİŞKENLERİNİ ALMA ---
$baslangic = $_GET['baslangic'] ?? '2025-10-01';
$bitis = $_GET['bitis'] ?? '2025-11-30';
$arama = $_GET['arama'] ?? '';
$tip = $_GET['tip'] ?? 'tumu';

// --- VERİYİ FİLTRELEME MANTIĞI ---
$filteredData = array_filter($sampleData, function($item) use ($baslangic, $bitis, $arama, $tip) {
    if (!empty($baslangic) && $item['tarih'] < $baslangic) return false;
    if (!empty($bitis) && $item['tarih'] > $bitis) return false;
    if (!empty($arama) && mb_stripos($item['aciklama'], $arama, 0, 'UTF-8') === false) return false; // Türkçe karakterler için mb_stripos
    if ($tip !== 'tumu' && $item['tip'] !== $tip) return false;
    return true;
});

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Birleşik Test Sayfası (tFPDF)</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; padding: 20px; background-color: #f9f9f9; }
        .container { max-width: 900px; margin: auto; background: white; border: 1px solid #ddd; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        h1 { border-bottom: 2px solid #eee; padding-bottom: 10px; }
        form { background: #f4f7f9; padding: 20px; border-radius: 5px; display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
        form div { display: flex; flex-direction: column; }
        form label { margin-bottom: 5px; font-size: 0.9em; color: #555; }
        form input, form select { padding: 8px; border-radius: 4px; border: 1px solid #ccc; font-size: 1em; }
        .actions { display: flex; justify-content: space-between; align-items: center; margin: 25px 0; }
        .btn { padding: 10px 18px; border-radius: 5px; text-decoration: none; color: white; cursor: pointer; border: none; font-size: 1em; }
        .btn-filter { background: #3498db; }
        .btn-pdf { background: #2ecc71; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background: #f2f2f2; }
        .gelir { color: #27ae60; font-weight: bold; }
        .gider { color: #c0392b; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>İşlem Geçmişi ve Raporlama</h1>
        
        <form action="test_gecmis.php" method="GET">
            <div>
                <label for="baslangic">Başlangıç Tarihi</label>
                <input id="baslangic" type="date" name="baslangic" value="<?= htmlspecialchars($baslangic) ?>">
            </div>
            <div>
                <label for="bitis">Bitiş Tarihi</label>
                <input id="bitis" type="date" name="bitis" value="<?= htmlspecialchars($bitis) ?>">
            </div>
            <div>
                <label for="arama">Açıklamada Ara</label>
                <input id="arama" type="text" name="arama" placeholder="Örn: Fatura, Maaş..." value="<?= htmlspecialchars($arama) ?>">
            </div>
            <div>
                <label for="tip">İşlem Tipi</label>
                <select id="tip" name="tip">
                    <option value="tumu" <?= $tip == 'tumu' ? 'selected' : '' ?>>Tümü</option>
                    <option value="gelir" <?= $tip == 'gelir' ? 'selected' : '' ?>>Gelir</option>
                    <option value="gider" <?= $tip == 'gider' ? 'selected' : '' ?>>Gider</option>
                </select>
            </div>
            <button type="submit" class="btn btn-filter">Filtrele</button>
        </form>

        <div class="actions">
            <span><b><?= count($filteredData) ?></b> kayıt bulundu.</span>
            <?php
            // Mevcut filtreleri alıp PDF linki için bir query string oluşturuyoruz.
            $queryParams = http_build_query([
                'baslangic' => $baslangic,
                'bitis' => $bitis,
                'arama' => $arama,
                'tip' => $tip
            ]);
            ?>
            <a href="test_pdf_rapor.php?<?= $queryParams ?>" target="_blank" class="btn btn-pdf">
                PDF Olarak İndir
            </a>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Tarih</th>
                    <th>Açıklama</th>
                    <th>Tip</th>
                    <th>Tutar</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($filteredData)): ?>
                    <tr><td colspan="4" style="text-align: center;">Filtreye uygun kayıt bulunamadı.</td></tr>
                <?php else: ?>
                    <?php foreach ($filteredData as $item): ?>
                        <tr>
                            <td><?= date("d.m.Y", strtotime($item['tarih'])) ?></td>
                            <td><?= htmlspecialchars($item['aciklama']) ?></td>
                            <td>
                                <span class="<?= $item['tip'] ?>">
                                    <?= $item['tip'] == 'gelir' ? 'Gelir' : 'Gider' ?>
                                </span>
                            </td>
                            <td>
                                <span class="<?= $item['tip'] ?>">
                                    <?= number_format($item['tutar'], 2, ',', '.') ?> TL
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>