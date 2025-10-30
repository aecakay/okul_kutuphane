<?php
require_once 'header.php';
// Güvenlik: Giriş yapılmamışsa login sayfasına yönlendir
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["okul_id"])){
    header("location: login.php");
    exit;
}
if (!isset($mysqli) || $mysqli->connect_errno) { require "config.php"; }

$okul_id = $_SESSION["okul_id"];
$current_user_id = $_SESSION["id"];
$is_super_admin = $_SESSION["username"] === 'admin';

// Süper admin ise tüm okulların, normal admin ise sadece kendi okulunun yöneticilerini listele
$sql = "SELECT Y.id, Y.kullanici_adi, O.okul_adi 
        FROM yoneticiler Y 
        JOIN okullar O ON Y.okul_id = O.id
        WHERE Y.kullanici_adi != 'admin' "; 
        
$params = [];
$types = "";

if (!$is_super_admin) {
    // Normal yöneticiler sadece kendi okullarının diğer yöneticilerini görebilir
    $sql .= " AND Y.okul_id = ?";
    $params[] = $okul_id;
    $types = "i";
}

$sql .= " ORDER BY O.okul_adi, Y.kullanici_adi";

$yoneticiler = [];
if($stmt = $mysqli->prepare($sql)) {
    if (!$is_super_admin) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    
    // DÜZELTME: get_result() yerine uyumlu yöntem kullanıldı
    $stmt->store_result();
    $stmt->bind_result($col_id, $col_kullanici_adi, $col_okul_adi);
    
    while($stmt->fetch()) {
        $yoneticiler[] = [
            'id' => $col_id,
            'kullanici_adi' => $col_kullanici_adi,
            'okul_adi' => $col_okul_adi
        ];
    }
    $stmt->close();
}

$mysqli->close();
?>

<h1>Yönetici Hesaplarını Yönetme</h1>
<p>Burada, sistemdeki ana yönetici ('admin') haricindeki tüm yöneticileri görebilirsiniz.</p>

<?php if ($is_super_admin): ?>
    <p class="warning" style="padding: 1rem;">Süper Admin olarak tüm okulların yöneticilerini silebilirsiniz. Ancak, kendinizi veya 'admin' kullanıcısını silemezsiniz.</p>
<?php endif; ?>

<div class="form-wrapper">
    <table id="yoneticiTable">
        <thead>
            <tr>
                <th style="width: 15%;">ID</th>
                <th>Kullanıcı Adı</th>
                <th>Okul Adı</th>
                <th style="width: 15%;">İşlem</th>
            </tr>
        </thead>
        <tbody>
            <?php if(!empty($yoneticiler)): ?>
                <?php foreach($yoneticiler as $yonetici): ?>
                    <tr data-id="<?php echo $yonetici['id']; ?>">
                        <td><?php echo $yonetici['id']; ?></td>
                        <td><?php echo htmlspecialchars($yonetici['kullanici_adi']); ?></td>
                        <td><?php echo htmlspecialchars($yonetici['okul_adi']); ?></td>
                        <td>
                            <button class="button-link small button-danger delete-btn" 
                                    data-id="<?php echo $yonetici['id']; ?>"
                                    data-name="<?php echo htmlspecialchars($yonetici['kullanici_adi']); ?>">
                                Sil
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" style="text-align: center;">Kayıtlı yönetici bulunmamaktadır.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.dataset.id;
            const userName = this.dataset.name;

            if(confirm(`'${userName}' adlı yöneticiyi silmek istediğinize emin misiniz?`)) {
                const formData = new FormData();
                formData.append('user_id', userId);

                fetch('yonetici_sil.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    showToast(data.message, data.success ? 'success' : 'error');
                    if(data.success) {
                        const row = document.querySelector(`tr[data-id='${userId}']`);
                        if(row) {
                            row.style.opacity = '0';
                            setTimeout(() => row.remove(), 300);
                        }
                    }
                })
                .catch(error => {
                    console.error('Silme hatası:', error);
                    showToast('Bir ağ hatası oluştu.', 'error');
                });
            }
        });
    });
});
</script>

<?php require_once 'footer.php'; ?>