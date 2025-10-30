<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';
session_start();

$response = ['success' => false, 'message' => 'Bilinmeyen bir hata oluştu.'];

// 1. Yetki Kontrolü: Sadece giriş yapmış öğrenciler rezerve yapabilir.
if (!isset($_SESSION["student_loggedin"]) || $_SESSION["student_loggedin"] !== true) {
    $response['message'] = 'Bu işlemi yapmak için öğrenci girişi yapmalısınız.';
    http_response_code(403);
    echo json_encode($response);
    exit;
}

// 2. Gelen Veriyi ve Oturum Bilgilerini Al
$kitap_id = isset($_POST['kitap_id']) ? (int)$_POST['kitap_id'] : 0;
$ogrenci_id = $_SESSION['student_id'];

if ($kitap_id <= 0) {
    $response['message'] = 'Geçersiz kitap ID\'si gönderildi.';
    echo json_encode($response);
    exit;
}

$mysqli->begin_transaction();

try {
    // 3. Öğrencinin ve Kitabın okul_id'sini al
    $sql_okul_check = "SELECT 
                        (SELECT okul_id FROM ogrenciler WHERE id = ?) as ogrenci_okul_id,
                        (SELECT okul_id FROM kitaplar WHERE id = ?) as kitap_okul_id";
    $stmt_okul = $mysqli->prepare($sql_okul_check);
    $stmt_okul->bind_param("ii", $ogrenci_id, $kitap_id);
    $stmt_okul->execute();
    $stmt_okul->store_result();
    $stmt_okul->bind_result($ogrenci_okul_id, $kitap_okul_id);
    $stmt_okul->fetch();
    $stmt_okul->close();

    // Güvenlik: Öğrenci ve kitap farklı okullara aitse işlemi durdur
    if ($ogrenci_okul_id !== $kitap_okul_id) {
        throw new Exception('HATA: Öğrenci ve kitap farklı okullara ait. Rezervasyon yapılamaz.');
    }
    
    // İşlemler için okul_id'yi belirle
    $okul_id = $ogrenci_okul_id;

    // a) Kitabın hala rafta olup olmadığını kontrol et
    $sql_stok = "SELECT raftaki_adet FROM kitaplar WHERE id = ? AND okul_id = ? FOR UPDATE";
    $stmt_stok = $mysqli->prepare($sql_stok);
    $stmt_stok->bind_param("ii", $kitap_id, $okul_id);
    $stmt_stok->execute();
    $stmt_stok->bind_result($raftaki_adet);
    $stmt_stok->fetch();
    $stmt_stok->close();

    if ($raftaki_adet > 0) {
        throw new Exception('Bu kitap an itibarıyla rafa eklendi. Sayfayı yenileyip ödünç alabilirsiniz.');
    }

    // b) Öğrencinin bu kitaba zaten aktif bir rezervasyonu var mı?
    $sql_check = "SELECT id FROM rezervasyonlar WHERE kitap_id = ? AND ogrenci_id = ? AND okul_id = ? AND durum = 'bekliyor'";
    $stmt_check = $mysqli->prepare($sql_check);
    $stmt_check->bind_param("iii", $kitap_id, $ogrenci_id, $okul_id);
    $stmt_check->execute();
    $stmt_check->store_result();
    
    if($stmt_check->num_rows > 0) {
        throw new Exception('Bu kitap için zaten bir rezervasyonunuz bulunmaktadır.');
    }
    $stmt_check->close();

    // c) Her şey yolundaysa, rezervasyonu ekle (okul_id ile birlikte).
    $sql_insert = "INSERT INTO rezervasyonlar (okul_id, kitap_id, ogrenci_id, durum) VALUES (?, ?, ?, 'bekliyor')";
    $stmt_insert = $mysqli->prepare($sql_insert);
    $stmt_insert->bind_param("iii", $okul_id, $kitap_id, $ogrenci_id);
    
    if($stmt_insert->execute()){
        $mysqli->commit();
        $response['success'] = true;
        $response['message'] = 'Rezervasyon başarılı! Kitap iade edildiğinde sıraya alınacaksınız.';
    } else {
        throw new Exception('Rezervasyon eklenirken veritabanı hatası oluştu.');
    }
    $stmt_insert->close();

} catch (Exception $e) {
    $mysqli->rollback();
    $response['message'] = $e->getMessage();
}

$mysqli->close();
echo json_encode($response);
exit;
?>