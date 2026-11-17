<?php
/**
 * app/Views/partials/flash.php
 * Flash Message Renderer (Prompt 32D)
 *
 * Menerjemahkan flash message session ke dalam komponen resmi:
 * - Hasil Penting ('success', 'danger', 'error', 'warning') -> Modal Hasil di Tengah (AppDialog.result) dengan tombol "Mengerti"
 * - Info Ringan ('info') -> Toast Tengah-Atas (AppToast.info) di bawah bar menu
 */

if (empty($flashMessages)) {
    return;
}

$defaultTitles = [
    'success' => 'Tindakan Berhasil',
    'danger'  => 'Perhatian / Terjadi Kesalahan',
    'error'   => 'Terjadi Kesalahan',
    'warning' => 'Peringatan Sistem',
    'info'    => 'Informasi',
];

$currentUriPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$isLoginPage = ($view ?? '') === 'auth/login' || str_starts_with($currentUriPath, '/login');
$isForgotPasswordPage = ($view ?? '') === 'auth/forgot_password' || str_starts_with($currentUriPath, '/forgot-password');
?>
<script>
(function() {
    function renderCampusFindFlashes() {
        <?php foreach ($flashMessages as $type => $message): ?>
            <?php
            $safeType = in_array($type, ['success', 'error', 'danger', 'warning', 'info'], true) ? $type : 'info';
            $normType = ($safeType === 'danger' || $safeType === 'error') ? 'danger' : $safeType;

            $msgText = '';
            $actionUrl = null;
            $actionText = null;
            $cancelText = null;
            if (is_array($message)) {
                $actionUrl = $message['action_url'] ?? $message['url'] ?? null;
                $actionText = $message['action_text'] ?? $message['label'] ?? ($actionUrl ? 'Lihat Laporan' : null);
                $cancelText = $message['cancel_text'] ?? null;

                if (isset($message['message'])) {
                    $msgText = (string)$message['message'];
                } elseif (isset($message['text'])) {
                    $msgText = (string)$message['text'];
                } else {
                    $cleanArr = array_diff_key($message, array_flip(['action_url', 'action_text', 'cancel_text', 'url', 'label']));
                    $msgText = implode(' ', array_filter($cleanArr, 'is_scalar'));
                }

                // Bersihkan URL aksi mentah agar tidak tampil literal di dalam teks deskripsi
                if (!empty($actionUrl)) {
                    $msgText = trim(str_replace($actionUrl, '', $msgText));
                }
            } else {
                $msgText = (string)$message;
            }

            $jsonMsg = json_encode($msgText, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
            $title = (is_array($message) && !empty($message['title'])) ? (string)$message['title'] : ($defaultTitles[$normType] ?? 'Pemberitahuan');
            $jsonTitle = json_encode($title, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
            ?>
            <?php if (($isLoginPage || $isForgotPasswordPage) && ($normType === 'danger' || $normType === 'error')): ?>
                // TASK 02C & 02D: Pada halaman login dan lupa password, pesan error ditangani inline di dalam form
                // Dilarang menampilkan popup modal di tengah layar maupun toast untuk validasi form.
            <?php elseif ($normType === 'info' && empty($actionUrl)): ?>
                if (window.AppToast && typeof window.AppToast.show === 'function') {
                    window.AppToast.show('info', <?= $jsonMsg ?>);
                }
            <?php else: ?>
                if (window.AppDialog && typeof window.AppDialog.result === 'function') {
                    window.AppDialog.result({
                        title: <?= $jsonTitle ?>,
                        message: <?= $jsonMsg ?>,
                        variant: '<?= $normType ?>',
                        okText: <?= json_encode($actionText ?: ($actionUrl ? 'Lihat Laporan' : 'Mengerti')) ?>,
                        actionUrl: <?= json_encode($actionUrl) ?>,
                        actionText: <?= json_encode($actionText ?: ($actionUrl ? 'Lihat Laporan' : null)) ?>,
                        cancelText: <?= json_encode($cancelText ?: ($actionUrl ? 'Tutup' : null)) ?>
                    });
                } else if (window.AppToast && typeof window.AppToast.show === 'function') {
                    window.AppToast.show('<?= $normType ?>', <?= $jsonMsg ?>);
                }
            <?php endif; ?>
        <?php endforeach; ?>
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', renderCampusFindFlashes);
    } else {
        renderCampusFindFlashes();
    }
})();
</script>

