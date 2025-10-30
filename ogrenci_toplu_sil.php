<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';
session_start();

$response = ['success' => false, 'message' => 'Bilinmeyen bir hata oluştu.'];

// Yetki Kontrolü
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["okul_id"])) {
    $response['message'] = 'Yetkisiz erişim.';
    http_response_code(403);
    echo json_encode($response);
    exit;
}

$okul_id = $_SESSION["okul_id"];
$idler_json = isset($_POST['idler']) ? $_POST['idler'] : '[]';
$idler = json_decode($idler_json);

if (empty($idler) || !is_array($idler)) {
    $response['message'] = 'Silinecek öğrenci seçilmedi veya geçersiz veri gönderildi.';
    echo json_encode($response);
    exit;
}

// Gelen ID'lerin sadece sayısal değerler olduğundan emin ol
$ogrenci_idler = array_map('intval', $idler);
$placeholders = implode(',', array_fill(0, count($ogrenci_idler), '?'));
$types = str_repeat('i', count($ogrenci_idler));

$silinen_sayisi = 0;
$silinmeyen_sayisi = 0;
$silinmeyen_ogrenciler = [];

$mysqli->begin_transaction();

try {
    // 1. ADIM: Silinmesi istenen öğrencilerden hangilerinin işlem geçmişi var?
    $sql_check = "SELECT DISTINCT ogrenci_id, O.ad, O.soyad 
                  FROM islemler I
                  JOIN ogrenciler O ON I.ogrenci_id = O.id
                  WHERE I.ogrenci_id IN ($placeholders) AND I.okul_id = ?";
                  
    $stmt_check = $mysqli->prepare($sql_check);
    // Dinamik olarak bind_param yap
    $params = array_merge($ogrenci_idler, [$okul_id]);
    $stmt_check->bind_param($types . 'i', ...$params);
    $stmt_check->execute();
    $stmt_check->store_result();
    
    $islem_gecmisi_olanlar = [];
    if($stmt_check->num_rows > 0) {
        $stmt_check->bind_result($ogrenci_id, $ad, $soyad);
        while($stmt_check->fetch()){
            $islem_gecmisi_olanlar[$ogrenci_id] = $ad . ' ' . $soyad;
        }
    }
    $stmt_check->close();

    // 2. ADIM: Silinebilecek ve silinemeyecek öğrencileri ayır
    $silinebilecek_idler = [];
    foreach($ogrenci_idler as $id){
        if(isset($islem_gecmisi_olanlar[$id])){
            $silinmeyen_sayisi++;
            $silinmeyen_ogrenciler[] = $islem_gecmisi_olanlar[$id];
        } else {
            $silinebilecek_idler[] = $id;
        }
    }

    // 3. ADIM: Silinebilecek öğrenci varsa, silme işlemlerini yap
    if(!empty($silinebilecek_idler)){
        $placeholders_sil = implode(',', array_fill(0, count($silinebilecek_idler), '?'));
        $types_sil = str_repeat('i', count($silinebilecek_idler));

        // a) Önce rezervasyonlarını sil
        $sql_delete_rez = "DELETE FROM rezervasyonlar WHERE ogrenci_id IN ($placeholders_sil) AND okul_id = ?";
        $stmt_delete_rez = $mysqli->prepare($sql_delete_rez);
        $params_rez = array_merge($silinebilecek_idler, [$okul_id]);
        $stmt_delete_rez->bind_param($types_sil . 'i', ...$params_rez);
        $stmt_delete_rez->execute();
        $stmt_delete_rez->close();

        // b) Sonra öğrencileri sil
        $sql_delete = "DELETE FROM ogrenciler WHERE id IN ($placeholders_sil) AND okul_id = ?";
        $stmt_delete = $mysqli->prepare($sql_delete);
        $params_ogrenci = array_merge($silinebilecek_idler, [$okul_id]);
        $stmt_delete->bind_param($types_sil . 'i', ...$params_ogrenci);
        $stmt_delete->execute();
        $silinen_sayisi = $stmt_delete->affected_rows;
        $stmt_delete->close();
    }
    
    $mysqli->commit();

    // 4. ADIM: Kullanıcıya detaylı rapor sun
    $response['success'] = true;
    $message = "İşlem tamamlandı. {$silinen_sayisi} öğrenci başarıyla silindi.";
    if($silinmeyen_sayisi > 0){
        $message .= " {$silinmeyen_sayisi} öğrenci ise işlem geçmişi bulunduğu için silinemedi: " . implode(', ', $silinmeyen_ogrenciler);
    }
    $response['message'] = $message;

} catch (Exception $e) {
    $mysqli->rollback();
    $response['message'] = "Toplu silme sırasında bir hata oluştu: " . $e->getMessage();
}

$mysqli->close();
echo json_encode($response);
exit;
?>