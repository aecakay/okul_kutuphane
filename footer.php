</main> </div>

<div id="barcode-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Barkod Okuyucu</h3>
            <button id="barcode-modal-kapat" class="modal-close-btn">&times;</button>
        </div>
        <div class="modal-body">
            <div class="video-wrapper">
                <video id="barcode-video" style="width: 100%; height: auto;"></video>
                <div class="scan-line"></div>
            </div>
            <div id="barcode-modal-status" class="modal-status">Kamera başlatılıyor...</div>
            <div class="form-group" style="margin-top: 15px;">
                <label for="barcode-kamera-sec">Kamera Kaynağı:</label>
                <select name="barcode-kamera-sec" id="barcode-kamera-sec" class="modal-select"></select>
            </div>
        </div>
    </div>
</div>

<script>
    // TOAST BİLDİRİM FONKSİYONU
    function showToast(message, type = 'success') {
        let toast = document.createElement('div');
        const icon = type === 'success' 
            ? '<i class="fa-solid fa-check-circle" style="margin-right: 10px;"></i>' 
            : '<i class="fa-solid fa-times-circle" style="margin-right: 10px;"></i>';
        
        toast.className = `toast ${type}`;
        toast.innerHTML = icon + message;
        
        const existingToasts = document.querySelectorAll('.toast');
        existingToasts.forEach(t => t.remove());

        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('show');
        }, 100);

        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                if (toast.parentNode === document.body) {
                    document.body.removeChild(toast);
                }
            }, 500);
        }, 4000);
    }

    document.addEventListener('DOMContentLoaded', () => {
        // PHP'den gelen Session tabanlı toast mesajını göster
        <?php
            if(isset($_SESSION['toast_message'])){
                $toast_success = $_SESSION['toast_message']['success'] ? 'success' : 'error';
                $toast_message = addslashes($_SESSION['toast_message']['message']);
                echo "showToast('{$toast_message}', '{$toast_success}');";
                unset($_SESSION['toast_message']);
            }
        ?>
    });
</script>

</body>
</html>