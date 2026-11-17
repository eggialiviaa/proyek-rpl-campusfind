<?php
/**
 * app/Views/partials/cfind_widget.php
 * Floating Chatbot Widget "C-Find" — Campus Find (Prompt 26)
 *
 * Komponen tunggal yang dipakai ulang di seluruh layout (Beranda/Tamu, Civitas, Petugas, Admin).
 * Otomatis menyesuaikan profil dan kapabilitas sesuai session role server.
 * Riwayat percakapan tersimpan di server & status buka/tutup di sessionStorage.
 */
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Services\CFindService;

// Pemeriksaan ganda keamanan: jangan tampilkan di login atau halaman cetak
$currentUriPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$isLoginPage = ($view ?? '') === 'auth/login' || str_starts_with($currentUriPath, '/login') || !empty($hideCFind);
$isPrintPage = !empty($isPrint) || str_contains($currentUriPath, '/print') || str_contains($currentUriPath, '/cetak') || (isset($_GET['print']) && $_GET['print'] === '1');

if ($isLoginPage || $isPrintPage) {
    return;
}

$csrfToken = CsrfMiddleware::getToken();
$botName = CFindService::BOT_NAME;

// Ambil info role dan pengguna dari session server
$currentUser = AuthMiddleware::user();
$currentRole = strtolower(trim((string)($currentUser['role_name'] ?? $currentUser['role'] ?? 'guest')));
$currentUserId = $currentUser ? (int)$currentUser['id'] : null;
$userScopeKey = $currentUserId !== null ? "user_{$currentUserId}" : 'guest';

$cFindService = new CFindService();
$existingHistory = $cFindService->getHistory();
$showGuestTeaser = ($currentRole === 'guest' && !empty($isLandingPage));

// Ambil metrik dinamis dari sumber kebenaran tunggal (MetricDefinitionService)
$metricService = new \App\Services\MetricDefinitionService();
$centerStats = $metricService->getCenterItemsStats();
$poskoItemsCount = $centerStats['total'];
$storedItemsCount = $centerStats['breakdown']['stored'] ?? 0;
$reportsStats = $metricService->getReportsStats();
$reportsTotal = $reportsStats['total'];
$claimsStats = $metricService->getClaimsStats();
$claimsTotal = $claimsStats['total'];
$claimsCompleted = $claimsStats['completed'];

// Konfigurasi profil & pertanyaan cepat per role
switch ($currentRole) {
    case 'admin':
        $roleSubtitle = 'Asisten Administrasi Sistem';
        $roleBadge = 'Admin';
        $roleBadgeClass = 'bg-danger text-white';
        $adminName = trim((string)($currentUser['name'] ?? 'Administrator'));
        $adminSalutation = (stripos($adminName, 'admin') !== false) ? $adminName : "Administrator {$adminName}";
        $roleGreeting = 'Halo, <strong>' . htmlspecialchars($adminSalutation, ENT_QUOTES, 'UTF-8', false) . '</strong>! Saya <strong>' . htmlspecialchars($botName, ENT_QUOTES, 'UTF-8') . '</strong>, siap menyajikan ringkasan statistik sistem, agregat pengguna, dan jejak audit.';
        $chips = [
            ['text' => "Barang di Posko ({$poskoItemsCount})", 'metric_key' => 'metric:posko_items_count', 'icon' => 'bi-box-seam'],
            ['text' => "Barang Tersimpan di Rak ({$storedItemsCount})", 'metric_key' => 'metric:stored_items_count', 'icon' => 'bi-archive'],
            ['text' => "Total Laporan ({$reportsTotal})", 'metric_key' => 'metric:reports_count', 'icon' => 'bi-clipboard-data'],
            ['text' => "Total Klaim ({$claimsTotal})", 'metric_key' => 'metric:claims_count', 'icon' => 'bi-shield-check'],
            ['text' => "Klaim Selesai ({$claimsCompleted})", 'metric_key' => 'metric:claims_completed', 'icon' => 'bi-check-circle'],
            ['text' => 'Login Hari Ini', 'metric_key' => 'metric:login_activity', 'icon' => 'bi-person-check'],
            ['text' => 'Rekap Pelapor', 'metric_key' => 'metric:reporters_summary', 'icon' => 'bi-people'],
        ];
        break;

    case 'petugas':
        $roleSubtitle = 'Asisten Operasional Posko';
        $roleBadge = 'Petugas';
        $roleBadgeClass = 'bg-success text-white';
        $petugasName = trim((string)($currentUser['name'] ?? 'Petugas'));
        $petugasSalutation = (stripos($petugasName, 'petugas') !== false) ? $petugasName : "Petugas {$petugasName}";
        $roleGreeting = 'Halo, <strong>' . htmlspecialchars($petugasSalutation, ENT_QUOTES, 'UTF-8', false) . '</strong>! Saya <strong>' . htmlspecialchars($botName, ENT_QUOTES, 'UTF-8') . '</strong>, siap membantu ringkasan operasional posko, inventaris rak gudang, dan verifikasi klaim.';
        $chips = [
            ['text' => "Barang di Posko ({$poskoItemsCount})", 'metric_key' => 'metric:posko_items_count', 'icon' => 'bi-box-seam'],
            ['text' => "Barang Tersimpan di Rak ({$storedItemsCount})", 'metric_key' => 'metric:stored_items_count', 'icon' => 'bi-archive'],
            ['text' => 'Daftar Barang Posko', 'metric_key' => 'metric:posko_items_list', 'icon' => 'bi-list-ul'],
            ['text' => "Total Klaim ({$claimsTotal})", 'metric_key' => 'metric:claims_count', 'icon' => 'bi-shield-check'],
            ['text' => "Klaim Selesai ({$claimsCompleted})", 'metric_key' => 'metric:claims_completed', 'icon' => 'bi-check-circle'],
            ['text' => 'Rekap Pelapor', 'metric_key' => 'metric:reporters_summary', 'icon' => 'bi-people'],
            ['text' => 'Lokasi Posko & Jam Buka', 'metric_key' => 'metric:posko_info', 'icon' => 'bi-geo-alt'],
        ];
        break;

    case 'civitas':
        $roleSubtitle = 'Asisten Civitas Akademika';
        $roleBadge = 'Civitas';
        $roleBadgeClass = 'bg-primary text-white';
        $civitasName = trim((string)($currentUser['name'] ?? 'Civitas'));
        $roleGreeting = 'Halo, <strong>' . htmlspecialchars($civitasName, ENT_QUOTES, 'UTF-8', false) . '</strong>! Saya <strong>' . htmlspecialchars($botName, ENT_QUOTES, 'UTF-8') . '</strong>, asisten virtual Anda. Ada yang bisa saya bantu seputar laporan pribadi, status klaim, atau panduan kampus?';
        $chips = [
            ['text' => 'Klaim Saya', 'metric_key' => 'metric:my_claims', 'icon' => 'bi-clock-history'],
            ['text' => 'Katalog Publik', 'metric_key' => 'metric:public_catalog', 'icon' => 'bi-box-seam'],
            ['text' => 'Cara Lapor Hilang', 'metric_key' => 'metric:faq_lost_report', 'icon' => 'bi-patch-question'],
            ['text' => 'Cara Mengajukan Klaim', 'metric_key' => 'metric:faq_claim_guide', 'icon' => 'bi-shield-check'],
            ['text' => 'Lokasi Posko & Jam Buka', 'metric_key' => 'metric:posko_info', 'icon' => 'bi-geo-alt'],
        ];
        break;

    default: // guest / tamu
        $roleSubtitle = 'Asisten Panduan Publik';
        $roleBadge = 'Tamu';
        $roleBadgeClass = 'bg-secondary text-white';
        $roleGreeting = 'Halo! Saya <strong>' . htmlspecialchars($botName, ENT_QUOTES, 'UTF-8') . '</strong>, asisten virtual cerdas Campus Find. Ada yang bisa saya bantu seputar panduan pelaporan barang, tata cara klaim, atau lokasi posko?';
        $chips = [
            ['text' => 'Barang di Katalog', 'metric_key' => 'metric:public_catalog', 'icon' => 'bi-box-seam'],
            ['text' => 'Cara Lapor Hilang', 'metric_key' => 'metric:faq_lost_report', 'icon' => 'bi-patch-question'],
            ['text' => 'Cara Mengajukan Klaim', 'metric_key' => 'metric:faq_claim_guide', 'icon' => 'bi-shield-check'],
            ['text' => 'Lokasi Posko & Jam Buka', 'metric_key' => 'metric:posko_info', 'icon' => 'bi-geo-alt'],
        ];
        break;
}
?>

<?php if ($showGuestTeaser): ?>
<!-- Guest Landing Teaser Bubble (Phase 4B-3, Guest Landing Page Only) -->
<div id="cfind-teaser"
     class="cfind-teaser"
     role="status"
     aria-live="polite"
     hidden>
    <button type="button"
            id="cfind-teaser-btn"
            class="cfind-teaser-btn"
            aria-label="Need help? Ask C-Find">
        <span>Need help? Ask C-Find</span>
        <span class="cfind-teaser-wave" aria-hidden="true">&#x1F44B;</span>
    </button>
    <button type="button"
            id="cfind-teaser-close"
            class="cfind-teaser-close"
            aria-label="Close C-Find helper prompt">
        <span aria-hidden="true">&times;</span>
    </button>
</div>
<?php endif; ?>

<!-- Floating Launcher Button -->
<button type="button" 
        id="cfind-launcher" 
        class="cfind-launcher" 
        aria-expanded="false" 
        aria-controls="cfind-panel" 
        aria-label="Buka Chat <?= htmlspecialchars($botName) ?>"
        title="Tanya <?= htmlspecialchars($botName) ?>">
    <span class="cfind-launcher-icon" aria-hidden="true">
        <i data-lucide="sparkles" style="width: 22px; height: 22px;"></i>
    </span>
    <span class="cfind-launcher-badge d-none d-md-inline"><?= htmlspecialchars($botName) ?></span>
    <span class="cfind-launcher-pulse" aria-hidden="true"></span>
</button>

<!-- Chat Window Panel -->
<div id="cfind-panel" 
     class="cfind-panel" 
     role="dialog" 
     aria-modal="false" 
     aria-label="Jendela Chatbot <?= htmlspecialchars($botName) ?>"
     data-user-scope="<?= htmlspecialchars($userScopeKey, ENT_QUOTES, 'UTF-8') ?>"
     data-role="<?= htmlspecialchars($currentRole, ENT_QUOTES, 'UTF-8') ?>">
    
    <!-- Panel Header -->
    <div class="cfind-header">
        <div class="d-flex align-items-center gap-2">
            <div class="cfind-avatar position-relative flex-shrink-0">
                <i data-lucide="bot" class="text-white" style="width: 20px; height: 20px;"></i>
                <span class="cfind-status-dot online" title="Status Online"></span>
            </div>
            <div class="cfind-header-info">
                <div class="d-flex align-items-center gap-1">
                    <h2 class="cfind-title mb-0 fs-6 fw-bold text-dark"><?= htmlspecialchars($botName) ?></h2>
                    <span class="badge <?= $roleBadgeClass ?> px-1 py-0" style="font-size: 0.65rem;"><?= htmlspecialchars($roleBadge) ?></span>
                </div>
                <div class="cfind-subtitle text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($roleSubtitle) ?></div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-1">
            <button type="button" 
                    id="cfind-btn-reset" 
                    class="cfind-btn-icon" 
                    title="Mulai percakapan baru" 
                    aria-label="Mulai percakapan baru">
                <i data-lucide="rotate-ccw" style="width: 16px; height: 16px;"></i>
            </button>
            <button type="button" 
                    id="cfind-btn-close" 
                    class="cfind-btn-icon" 
                    title="Tutup chat" 
                    aria-label="Tutup Chat <?= htmlspecialchars($botName) ?>">
                <i data-lucide="x" style="width: 18px; height: 18px;"></i>
            </button>
        </div>
    </div>

    <!-- Messages Container -->
    <div id="cfind-messages" class="cfind-messages" role="log" aria-live="polite">
        <!-- Transparency Notice (Prompt 36C) -->
        <div class="cfind-transparency-banner small text-muted text-center py-1 px-2 mb-2 rounded-2 bg-light border border-light-subtle d-flex align-items-center justify-content-center gap-1" style="font-size: 0.75rem;">
            <i data-lucide="shield-check" class="text-primary me-1" style="width: 15px; height: 15px;"></i>
            <span>Percakapan dicatat dan dapat ditinjau Admin untuk keamanan dan kualitas layanan.</span>
        </div>

        <!-- Welcome Greeting Bubble -->
        <div class="cfind-msg-row bot">
            <div class="cfind-avatar-sm flex-shrink-0" aria-hidden="true">
                <i data-lucide="bot" style="width: 16px; height: 16px;"></i>
            </div>
            <div class="cfind-bubble bot">
                <p class="mb-0"><?= $roleGreeting ?></p>
            </div>
        </div>

        <!-- Persisted Server History Data (Prompt 27/29) -->
        <script id="cfind-history-data" type="application/json"><?= json_encode($existingHistory ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>

        <!-- Suggestion Chips Container (Role-Specific) -->
        <div id="cfind-chips" class="cfind-chips-wrap" <?= !empty($existingHistory) ? 'style="display: none;"' : '' ?>>
            <div class="cfind-chips-label text-muted">Contoh pertanyaan cepat:</div>
            <div class="d-flex flex-wrap gap-1 mt-1">
                <?php foreach ($chips as $chip): ?>
                    <button type="button" class="cfind-chip" 
                            data-question="<?= htmlspecialchars($chip['text'], ENT_QUOTES, 'UTF-8') ?>"
                            data-metric-key="<?= htmlspecialchars($chip['metric_key'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <i class="bi <?= htmlspecialchars($chip['icon'], ENT_QUOTES, 'UTF-8') ?> me-1"></i><?= htmlspecialchars($chip['text'], ENT_QUOTES, 'UTF-8') ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Typing Indicator (Hidden by default) -->
        <div id="cfind-typing" class="cfind-msg-row bot d-none" aria-hidden="true">
            <div class="cfind-avatar-sm flex-shrink-0">
                <i data-lucide="bot" style="width: 16px; height: 16px;"></i>
            </div>
            <div class="cfind-bubble bot typing">
                <span class="cfind-typing-text small me-2"><?= htmlspecialchars($botName) ?> sedang mengetik</span>
                <span class="cfind-dot"></span>
                <span class="cfind-dot"></span>
                <span class="cfind-dot"></span>
            </div>
        </div>
    </div>

    <!-- Floating New Message Notification Pill (Prompt 29D) -->
    <button type="button" id="cfind-new-msg-pill" class="cfind-new-msg-pill d-none" aria-label="Lihat pesan baru">
        Pesan baru ↓
    </button>

    <!-- Input Form Area (Aria-Inspired Floating Liquid Glass Capsule) -->
    <div class="cfind-input-area">
        <form id="cfind-form" class="cfind-form" autocomplete="off">
            <input type="hidden" name="_csrf_token" id="cfind-csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <div class="cfind-input-capsule">
                <input type="text" 
                       id="cfind-input" 
                       class="form-control cfind-input shadow-none" 
                       placeholder="Tanyakan seputar Campus Find..." 
                       maxlength="300"
                       aria-label="Pesan untuk <?= htmlspecialchars($botName) ?>">
                <div class="d-flex align-items-center justify-content-between px-2 pt-1 pb-1">
                    <div class="d-flex align-items-center gap-2 text-muted" style="font-size: 0.85rem;">
                        <span class="cfind-input-util-icon" title="Bantuan C-Find"><i data-lucide="plus-circle" style="width: 16px; height: 16px;"></i></span>
                        <span class="cfind-input-util-icon text-primary" title="AI Assistant"><i data-lucide="sparkles" style="width: 16px; height: 16px;"></i></span>
                        <span id="cfind-char-count" class="text-muted" style="font-size: 0.68rem;">0/300</span>
                    </div>
                    <button type="submit" 
                            id="cfind-send-btn" 
                            class="cfind-send-btn-round" 
                            aria-label="Kirim Pesan">
                        <i data-lucide="send" style="width: 15px; height: 15px;"></i>
                    </button>
                </div>
            </div>
            <div class="text-center mt-1">
                <span class="text-muted" style="font-size: 0.66rem;">Tekan Enter untuk mengirim</span>
            </div>
        </form>
    </div>

    <!-- Static Disclaimer Footer -->
    <div class="cfind-footer border-top text-center py-1 px-2 bg-light bg-opacity-75">
        <span class="text-muted" style="font-size: 0.68rem; line-height: 1.2; display: flex; align-items: center; justify-content: center; gap: 4px;">
            <i data-lucide="info" style="width: 13px; height: 13px;"></i><?= htmlspecialchars($botName) ?> adalah asisten otomatis; keputusan klaim tetap oleh Petugas.
        </span>
    </div>
</div>

<style>
/* ─────────────────────────────────────────────────────────────
   C-Find Chatbot Floating Widget Styles (Liquid Glass & Modern)
   Hierarchy: Base nav (1030) < C-Find (1039) < Scrim (1041) < Nav Active (1045) < Mobile Nav (1048) < Modal (1050+)
   ───────────────────────────────────────────────────────────── */
.cfind-launcher {
    position: fixed;
    right: calc(1.25rem + env(safe-area-inset-right));
    bottom: calc(1.25rem + env(safe-area-inset-bottom));
    min-width: 58px;
    height: 58px;
    padding: 0 16px;
    border-radius: 29px;
    background: linear-gradient(135deg, #14283C 0%, #0C1926 100%);
    color: #ffffff;
    border: 2px solid rgba(255, 255, 255, 0.65);
    box-shadow: 0 8px 24px rgba(20, 40, 60, 0.32), 0 2px 6px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
    z-index: 1039;
    transition: transform 0.26s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.26s ease;
}
.cfind-launcher:hover {
    transform: translateY(-2px) scale(1.04);
    box-shadow: 0 12px 30px rgba(20, 40, 60, 0.42);
}
.cfind-launcher:active {
    transform: scale(0.96);
}
.cfind-launcher:focus-visible {
    outline: 3px solid var(--cf-accent, #A77B5B);
    outline-offset: 3px;
}
.cfind-launcher-badge {
    font-weight: 700;
    font-size: 0.9rem;
    letter-spacing: -0.01em;
}
.cfind-launcher-pulse {
    position: absolute;
    top: 2px;
    right: 2px;
    width: 12px;
    height: 12px;
    background: var(--cf-accent, #A77B5B);
    border: 2px solid #ffffff;
    border-radius: 50%;
    animation: cfind-pulse-anim 2s infinite;
}
@keyframes cfind-pulse-anim {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(167, 123, 91, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(167, 123, 91, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(167, 123, 91, 0); }
}

/* Chat Panel (Liquid Glass Materialization - PAGE-01.2B.1.1 Phase D) */
.cfind-panel {
    position: fixed;
    right: calc(1.25rem + env(safe-area-inset-right));
    bottom: calc(5.25rem + env(safe-area-inset-bottom));
    width: 375px;
    max-width: calc(100vw - 2rem);
    height: 520px;
    max-height: calc(100vh - 120px);
    background: linear-gradient(
        180deg,
        rgba(255, 255, 255, 0.38) 0%,
        rgba(255, 255, 255, 0.18) 55%,
        rgba(255, 255, 255, 0.28) 100%
    );
    backdrop-filter: blur(28px) saturate(180%);
    -webkit-backdrop-filter: blur(28px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.55);
    border-radius: 1.25rem;
    box-shadow: 
        0 20px 48px rgba(12, 25, 38, 0.16),
        0 0 0 1px rgba(255, 255, 255, 0.35),
        inset 0 1px 0 rgba(255, 255, 255, 0.70),
        inset 0 -1px 0 rgba(255, 255, 255, 0.25);
    display: flex;
    flex-direction: column;
    z-index: 1039;
    overflow: hidden;
    opacity: 0;
    visibility: hidden;
    transform: translateY(14px) scale(0.96);
    transition: opacity 0.24s ease, transform 0.24s cubic-bezier(0.16, 1, 0.3, 1), visibility 0.24s;
}
.cfind-panel::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, 
        rgba(167, 123, 91, 0.55) 0%, 
        rgba(245, 158, 11, 0.6) 25%, 
        rgba(169, 184, 164, 0.65) 50%, 
        rgba(59, 130, 246, 0.6) 75%, 
        rgba(147, 51, 234, 0.5) 100%
    );
    pointer-events: none;
    border-bottom-left-radius: 1.25rem;
    border-bottom-right-radius: 1.25rem;
    z-index: 5;
}
.cfind-panel.is-open {
    opacity: 1;
    visibility: visible;
    transform: translateY(0) scale(1);
}

/* Header */
.cfind-header {
    padding: 0.85rem 1rem;
    background: rgba(255, 255, 255, 0.20);
    border-bottom: 1px solid rgba(255, 255, 255, 0.35);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.cfind-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #14283C 0%, #0C1926 100%);
    display: flex;
    align-items: center;
    justify-content: center;
}
.cfind-avatar-sm {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #e2e8f0;
    color: #334155;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
}
.cfind-status-dot.online {
    position: absolute;
    bottom: -1px;
    right: -1px;
    width: 10px;
    height: 10px;
    background: #10b981;
    border: 2px solid #ffffff;
    border-radius: 50%;
}
.cfind-btn-icon {
    background: transparent;
    border: none;
    color: #64748b;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
}
.cfind-btn-icon:hover {
    background: rgba(226, 232, 240, 0.7);
    color: #0f172a;
}

/* Messages Area */
.cfind-messages {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 1rem 0.85rem;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    scroll-behavior: smooth;
}
.cfind-msg-row {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    width: 100%;
}
.cfind-msg-row.user {
    justify-content: flex-end;
}
.cfind-bubble {
    max-width: 82%;
    padding: 0.65rem 0.85rem;
    border-radius: 1rem;
    font-size: 0.85rem;
    line-height: 1.45;
    word-break: break-word;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
}
.cfind-bubble.bot {
    background: rgba(255, 255, 255, 0.40) !important;
    backdrop-filter: blur(12px) saturate(160%);
    -webkit-backdrop-filter: blur(12px) saturate(160%);
    color: #14283C !important;
    border-bottom-left-radius: 0.25rem;
    border: 1px solid rgba(255, 255, 255, 0.60) !important;
    box-shadow: 0 2px 8px rgba(12, 25, 38, 0.04) !important;
}
.cfind-bubble.bot,
.cfind-bubble.bot p,
.cfind-bubble.bot span {
    color: #14283C !important;
}
.cfind-bubble.bot strong {
    color: #0C1926 !important;
    font-weight: 700 !important;
}
.cfind-bubble.bot a {
    color: #A77B5B !important;
    font-weight: 600 !important;
    text-decoration: underline !important;
}
.cfind-bubble.bot:has(.cfind-table-wrap),
.cfind-bubble.has-table {
    max-width: 96%;
    width: 96%;
}
.cfind-table-wrap {
    max-width: 100%;
    max-height: 380px;
    overflow: auto;
    -webkit-overflow-scrolling: touch;
    border: 1px solid rgba(20, 40, 60, 0.15);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.90);
    margin: 0.5rem 0;
    box-shadow: 0 2px 6px rgba(12, 25, 38, 0.05);
    position: relative;
}
.cfind-table {
    width: 100%;
    min-width: 280px;
    border-collapse: collapse;
    font-size: 0.76rem;
    line-height: 1.35;
    color: #14283C;
}
.cfind-table thead {
    background: rgba(248, 250, 252, 0.90);
    border-bottom: 2px solid rgba(20, 40, 60, 0.12);
}
.cfind-table th {
    padding: 6px 10px;
    font-weight: 600;
    color: #0C1926;
    text-align: left;
    white-space: nowrap;
    position: sticky;
    top: 0;
    z-index: 2;
    background: rgba(248, 250, 252, 0.95);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
}
.cfind-table td {
    padding: 6px 10px;
    border-bottom: 1px solid rgba(20, 40, 60, 0.08);
    vertical-align: middle;
    color: #14283C;
    white-space: nowrap;
}
.cfind-table tbody tr:last-child td {
    border-bottom: none;
}
.cfind-table tbody tr:hover {
    background: rgba(20, 40, 60, 0.04);
}
.cfind-badge {
    display: inline-block;
    padding: 2px 7px;
    border-radius: 4px;
    font-size: 0.68rem;
    font-weight: 600;
    line-height: 1.25;
    white-space: nowrap;
}
.cfind-badge-navy {
    background: #14283C;
    color: #ffffff;
}
.cfind-badge-blue {
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
}
.cfind-badge-dark {
    background: #0C1926;
    color: #f8fafc;
}
.cfind-badge-gray {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #cbd5e1;
}
.cfind-table-foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 4px 10px;
    background: rgba(248, 250, 252, 0.90);
    border-top: 1px solid rgba(20, 40, 60, 0.08);
    font-size: 0.7rem;
    color: #64748B;
}
.cfind-foot-note {
    font-size: 0.7rem;
    color: #64748B;
}
.cfind-btn-show-all {
    background: transparent;
    border: 1px solid #14283C;
    color: #14283C;
    font-size: 0.68rem;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.15s ease;
}
.cfind-btn-show-all:hover {
    background: #14283C;
    color: #ffffff;
}
.cfind-new-msg-pill {
    position: absolute;
    bottom: 68px;
    left: 50%;
    transform: translateX(-50%);
    background: #14283C;
    color: #ffffff;
    border: 1px solid rgba(255, 255, 255, 0.35);
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.74rem;
    font-weight: 600;
    box-shadow: 0 4px 14px rgba(20, 40, 60, 0.30);
    cursor: pointer;
    z-index: 25;
    transition: all 0.2s ease;
}
.cfind-new-msg-pill:hover {
    background: #0C1926;
    transform: translateX(-50%) translateY(-2px);
    box-shadow: 0 6px 18px rgba(20, 40, 60, 0.40);
}
.cfind-text-block {
    margin-bottom: 0.35rem;
}
.cfind-text-block:last-child {
    margin-bottom: 0;
}
.cfind-bubble.bot p:last-child {
    margin-bottom: 0;
}

/* User Message Bubble (Deep Navy Background & Pure White Text - PAGE-01.2B.1.1 Phase D) */
.cfind-bubble.user {
    background: linear-gradient(135deg, #14283C 0%, #0C1926 100%) !important;
    color: #FFFFFF !important;
    border: 1px solid rgba(255, 255, 255, 0.16) !important;
    border-bottom-right-radius: 0.25rem;
    box-shadow: 0 4px 12px rgba(12, 25, 38, 0.15) !important;
}
.cfind-bubble.user,
.cfind-bubble.user *,
.cfind-bubble.user p,
.cfind-bubble.user span,
.cfind-bubble.user strong,
.cfind-bubble.user em,
.cfind-bubble.user small,
.cfind-bubble.user a,
.cfind-bubble.user time,
.cfind-bubble.user .text-muted,
.cfind-bubble.user [class*="text-"] {
    color: #FFFFFF !important;
}
.cfind-bubble.user a {
    color: #FFFFFF !important;
    text-decoration: underline !important;
    font-weight: 600 !important;
}

/* Chips */
.cfind-chips-wrap {
    padding: 0.25rem 0.5rem;
    margin-top: 0.25rem;
}
.cfind-chips-label {
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #64748B;
}
.cfind-chip {
    background: rgba(255, 255, 255, 0.35);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border: 1px solid rgba(255, 255, 255, 0.60);
    color: #14283C;
    font-size: 0.76rem;
    font-weight: 500;
    padding: 0.40rem 0.80rem;
    border-radius: 9999px;
    cursor: pointer;
    transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
    text-align: left;
    box-shadow: 0 2px 6px rgba(12, 25, 38, 0.04);
}
.cfind-chip:hover {
    background: rgba(255, 255, 255, 0.65);
    border-color: rgba(255, 255, 255, 0.90);
    color: #0C1926;
    transform: translateY(-1.5px);
    box-shadow: 0 4px 14px rgba(12, 25, 38, 0.08);
}

/* Typing Dots */
.cfind-bubble.typing {
    display: flex;
    align-items: center;
    padding: 0.55rem 0.85rem;
}
.cfind-dot {
    width: 6px;
    height: 6px;
    background: #64748b;
    border-radius: 50%;
    margin: 0 2px;
    animation: cfind-bounce 1.4s infinite ease-in-out both;
}
.cfind-dot:nth-child(2) { animation-delay: -0.32s; }
.cfind-dot:nth-child(3) { animation-delay: -0.16s; }
@keyframes cfind-bounce {
    0%, 80%, 100% { transform: scale(0); }
    40% { transform: scale(1); }
}

/* Input Form Area (Aria-Inspired Floating Liquid Glass Capsule) */
.cfind-input-area {
    padding: 0.65rem 0.85rem 0.5rem 0.85rem;
    background: rgba(255, 255, 255, 0.18);
    border-top: 1px solid rgba(255, 255, 255, 0.28);
}
.cfind-input-capsule {
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border-radius: 18px;
    border: 1px solid rgba(255, 255, 255, 0.95);
    box-shadow: 0 4px 16px rgba(12, 25, 38, 0.06), inset 0 1px 1px #ffffff;
    padding: 0.25rem 0.4rem 0.25rem 0.4rem;
    transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}
.cfind-input-capsule:focus-within {
    background: rgba(255, 255, 255, 0.96);
    border-color: #14283C;
    box-shadow: 0 4px 20px rgba(20, 40, 60, 0.12), inset 0 1px 1px #ffffff;
}
.cfind-input {
    background: transparent !important;
    border: none !important;
    border-radius: 14px !important;
    font-size: 0.86rem;
    color: #14283C !important;
    padding: 0.45rem 0.65rem !important;
    box-shadow: none !important;
}
.cfind-input::placeholder {
    color: #64748B !important;
    opacity: 1 !important;
}
.cfind-input-util-icon {
    cursor: pointer;
    opacity: 0.7;
    transition: opacity 0.15s ease, transform 0.15s ease;
}
.cfind-input-util-icon:hover {
    opacity: 1;
    transform: scale(1.1);
}
.cfind-send-btn-round {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, #14283C 0%, #0C1926 100%);
    border: none;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.82rem;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(20, 40, 60, 0.28);
    transition: transform 0.18s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.18s ease;
}
.cfind-send-btn-round:hover {
    transform: scale(1.08);
    box-shadow: 0 4px 14px rgba(20, 40, 60, 0.45);
}
.cfind-send-btn-round:active {
    transform: scale(0.94);
}
.cfind-send-btn-round i {
    color: #ffffff !important;
}
.cfind-send-btn {
    border-radius: 0 0.75rem 0.75rem 0 !important;
    background-color: #14283C !important;
    border-color: #14283C !important;
    color: #FFFFFF !important;
}
.cfind-send-btn:hover {
    background-color: #0C1926 !important;
    border-color: #0C1926 !important;
    color: #FFFFFF !important;
}
.cfind-send-btn i {
    color: #FFFFFF !important;
}

/* Transparency Notice & Footer Refinement (PAGE-01.2B.1.1 Phase D) */
.cfind-transparency-banner {
    background: rgba(255, 255, 255, 0.35) !important;
    border: 1px solid rgba(255, 255, 255, 0.45) !important;
    color: #475569 !important;
}
.cfind-footer {
    background: rgba(255, 255, 255, 0.18) !important;
    border-top: 1px solid rgba(255, 255, 255, 0.28) !important;
}

/* Mobile Screens (< 576px) */
@media (max-width: 575.98px) {
    .cfind-launcher {
        right: 1rem;
        bottom: 1rem;
        min-width: 50px;
        height: 50px;
        padding: 0 12px;
    }
    .cfind-panel {
        right: 0.5rem;
        bottom: 0.5rem;
        width: calc(100vw - 1rem);
        height: calc(100dvh - 60px);
        max-height: calc(100dvh - 60px);
        border-radius: 1rem;
    }
}

/* Guest Landing Teaser Bubble (Phase 4B-3, Approved CampusFind Palette) */
.cfind-teaser {
    position: fixed;
    right: calc(1.25rem + env(safe-area-inset-right));
    bottom: calc(5.55rem + env(safe-area-inset-bottom));
    z-index: 1039;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    max-width: calc(100vw - 2.5rem);
    padding: 0.38rem 0.45rem 0.38rem 0.85rem;
    border-radius: 9999px;
    background-color: #14283C;
    color: #F7F8FA;
    border: 1px solid rgba(167, 123, 91, 0.55);
    box-shadow: 0 14px 32px rgba(20, 40, 60, 0.24), 0 4px 10px rgba(20, 40, 60, 0.14);
    opacity: 0;
    transform: translateY(10px) scale(0.96);
    pointer-events: none;
    transition: opacity 0.28s ease, transform 0.28s cubic-bezier(0.22, 1, 0.36, 1);
}
.cfind-teaser[hidden] {
    display: none !important;
}
.cfind-teaser.is-visible {
    opacity: 1;
    transform: translateY(0) scale(1);
    pointer-events: auto;
}
.cfind-teaser-btn {
    appearance: none;
    border: 0;
    background: transparent;
    color: #F7F8FA;
    font-size: 0.86rem;
    font-weight: 650;
    line-height: 1.3;
    padding: 0.25rem 0.2rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    cursor: pointer;
    white-space: nowrap;
}
.cfind-teaser-btn:focus-visible,
.cfind-teaser-close:focus-visible {
    outline: 2px solid #A77B5B;
    outline-offset: 2px;
    border-radius: 9999px;
}
.cfind-teaser-wave {
    display: inline-block;
    transform-origin: 70% 70%;
}
.cfind-teaser.is-visible .cfind-teaser-wave {
    animation: cfind-wave 0.85s ease-in-out 0.2s 2;
}
.cfind-teaser-close {
    appearance: none;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    border: 1px solid rgba(247, 248, 250, 0.22);
    background-color: rgba(247, 248, 250, 0.10);
    color: #F7F8FA;
    font-size: 1rem;
    line-height: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    flex-shrink: 0;
    transition: background-color 0.2s ease, border-color 0.2s ease;
}
.cfind-teaser-close:hover {
    background-color: #77543D;
    border-color: #A77B5B;
}
@keyframes cfind-wave {
    0% { transform: rotate(0deg); }
    20% { transform: rotate(16deg); }
    40% { transform: rotate(-10deg); }
    60% { transform: rotate(14deg); }
    80% { transform: rotate(-6deg); }
    100% { transform: rotate(0deg); }
}

/* Sembunyikan saat mencetak halaman */
@media print {
    #cfind-launcher,
    #cfind-panel,
    #cfind-teaser {
        display: none !important;
    }
}

/* Prefers Reduced Motion */
@media (prefers-reduced-motion: reduce) {
    .cfind-launcher,
    .cfind-panel,
    .cfind-bubble,
    .cfind-chip,
    .cfind-dot,
    .cfind-launcher-pulse,
    .cfind-teaser,
    .cfind-teaser-wave {
        transition: none !important;
        animation: none !important;
    }
}
</style>

<script>
(function () {
    'use strict';

    // DOM Elements
    const launcher = document.getElementById('cfind-launcher');
    const panel = document.getElementById('cfind-panel');
    const btnClose = document.getElementById('cfind-btn-close');
    const btnReset = document.getElementById('cfind-btn-reset');
    const form = document.getElementById('cfind-form');
    const input = document.getElementById('cfind-input');
    const charCount = document.getElementById('cfind-char-count');
    const messages = document.getElementById('cfind-messages');
    const typingIndicator = document.getElementById('cfind-typing');
    const chipsContainer = document.getElementById('cfind-chips');
    const csrfInput = document.getElementById('cfind-csrf');
    const newMsgPill = document.getElementById('cfind-new-msg-pill');

    if (!launcher || !panel) return;

    let isSending = false;

    // SessionStorage Keys:
    // - Preserve CFIND_USER_KEY user scope tracking
    // - Chat panel NEVER auto-reopens across reload or page navigation
    // - Guest teaser dismissal uses an independent sessionStorage key
    const CFIND_STORAGE_KEY = 'cfind_panel_open';
    const CFIND_USER_KEY = 'cfind_user_scope';
    const CFIND_TEASER_DISMISSED_KEY = 'cfind_guest_teaser_dismissed';
    const currentScope = panel.getAttribute('data-user-scope') || 'guest';

    // Always clear any legacy panel auto-open state so panel remains closed on reload/navigation
    try {
        sessionStorage.removeItem(CFIND_STORAGE_KEY);
        const lastScope = sessionStorage.getItem(CFIND_USER_KEY);
        if (lastScope && lastScope !== currentScope) {
            sessionStorage.removeItem(CFIND_STORAGE_KEY);
        }
        sessionStorage.setItem(CFIND_USER_KEY, currentScope);
    } catch (_) {}

    const teaserEl = document.getElementById('cfind-teaser');
    const teaserBtn = document.getElementById('cfind-teaser-btn');
    const teaserCloseBtn = document.getElementById('cfind-teaser-close');
    let teaserShowTimer = null;
    let teaserAutoDismissTimer = null;

    function hideGuestTeaser(persistDismissal) {
        if (teaserShowTimer) {
            clearTimeout(teaserShowTimer);
            teaserShowTimer = null;
        }
        if (teaserAutoDismissTimer) {
            clearTimeout(teaserAutoDismissTimer);
            teaserAutoDismissTimer = null;
        }
        if (persistDismissal) {
            try {
                sessionStorage.setItem(CFIND_TEASER_DISMISSED_KEY, '1');
            } catch (_) {}
        }
        if (!teaserEl) {
            return;
        }
        teaserEl.classList.remove('is-visible');
        teaserEl.hidden = true;
    }

    // Toggle Panel & Focus Management (explicit interaction only)
    function openPanel(focus = true) {
        hideGuestTeaser(false);
        panel.classList.add('is-open');
        launcher.setAttribute('aria-expanded', 'true');
        
        // Pastikan container pesan ter-scroll ke bawah
        requestAnimationFrame(() => {
            if (messages) messages.scrollTop = messages.scrollHeight;
        });

        if (focus && input) {
            setTimeout(() => {
                input.focus();
            }, 120);
        }
    }

    function closePanel() {
        panel.classList.remove('is-open');
        launcher.setAttribute('aria-expanded', 'false');
        launcher.focus();
    }

    // Expose Global Helper untuk integrasi Navigasi & Teaser (window.openCFind(true))
    window.openCFind = openPanel;
    window.closeCFind = closePanel;

    // Guest Landing Teaser Lifecycle (only rendered in DOM when Guest is on landing page)
    if (teaserEl) {
        let alreadyDismissed = false;
        try {
            alreadyDismissed = sessionStorage.getItem(CFIND_TEASER_DISMISSED_KEY) === '1';
        } catch (_) {}

        if (!alreadyDismissed && !panel.classList.contains('is-open')) {
            teaserShowTimer = setTimeout(function () {
                teaserShowTimer = null;
                if (panel.classList.contains('is-open')) {
                    return;
                }
                teaserEl.hidden = false;
                requestAnimationFrame(function () {
                    teaserEl.classList.add('is-visible');
                });
                teaserAutoDismissTimer = setTimeout(function () {
                    teaserAutoDismissTimer = null;
                    hideGuestTeaser(false);
                }, 6000);
            }, 1500);
        }

        if (teaserBtn) {
            teaserBtn.addEventListener('click', function () {
                hideGuestTeaser(true);
                window.openCFind(true);
            });
        }
        if (teaserCloseBtn) {
            teaserCloseBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                hideGuestTeaser(true);
            });
        }
    }

    launcher.addEventListener('click', function () {
        if (panel.classList.contains('is-open')) {
            closePanel();
        } else {
            openPanel(true);
        }
    });

    if (btnClose) {
        btnClose.addEventListener('click', closePanel);
    }

    // Close on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && panel.classList.contains('is-open')) {
            closePanel();
        }
    });

    // Character counter
    if (input && charCount) {
        input.addEventListener('input', function () {
            charCount.textContent = this.value.length + '/300';
        });
    }

    // Helper: Map status/role to palette tokens (navy/blue/black/gray/white)
    function getBadgeTokenClass(colKey, val) {
        const norm = String(val).toLowerCase();
        if (colKey === 'role_name') {
            if (norm.includes('admin')) return 'cfind-badge-navy';
            if (norm.includes('petugas')) return 'cfind-badge-blue';
            return 'cfind-badge-gray';
        }
        if (colKey === 'is_active') {
            if (norm.includes('aktif') && !norm.includes('non')) return 'cfind-badge-blue';
            return 'cfind-badge-gray';
        }
        // Status Laporan / Barang / Klaim
        if (norm.includes('tersimpan') || norm.includes('selesai') || norm.includes('disetujui') || norm.includes('aktif') || norm.includes('terverifikasi')) {
            return 'cfind-badge-blue';
        }
        if (norm.includes('klaim') || norm.includes('tinjau') || norm.includes('pemeriksaan') || norm.includes('penyelidikan')) {
            return 'cfind-badge-navy';
        }
        if (norm.includes('tolak') || norm.includes('tutup') || norm.includes('batal') || norm.includes('musnah')) {
            return 'cfind-badge-dark';
        }
        return 'cfind-badge-gray';
    }

    // Helper: Build Structured Table via DOM (Prompt 29D)
    function buildTableDOM(tableData) {
        const wrap = document.createElement('div');
        wrap.className = 'cfind-table-wrap';

        const table = document.createElement('table');
        table.className = 'cfind-table';

        // Columns
        const columns = Array.isArray(tableData.columns) ? tableData.columns : [];
        const thead = document.createElement('thead');
        const trHead = document.createElement('tr');
        columns.forEach(col => {
            const th = document.createElement('th');
            th.textContent = col.label || col.key || '';
            trHead.appendChild(th);
        });
        thead.appendChild(trHead);
        table.appendChild(thead);

        // Body
        const tbody = document.createElement('tbody');
        const rows = Array.isArray(tableData.rows) ? tableData.rows : [];

        function renderRow(rowData) {
            const tr = document.createElement('tr');
            columns.forEach(col => {
                const td = document.createElement('td');
                const rawVal = rowData[col.key] !== undefined && rowData[col.key] !== null ? String(rowData[col.key]) : '-';

                const isBadgeCol = ['status', 'role_name', 'item_condition', 'physical_condition', 'is_active'].includes(col.key);
                if (isBadgeCol && rawVal !== '-') {
                    const badge = document.createElement('span');
                    badge.className = 'cfind-badge ' + getBadgeTokenClass(col.key, rawVal);
                    badge.textContent = rawVal;
                    td.appendChild(badge);
                } else {
                    td.textContent = rawVal;
                }
                tr.appendChild(td);
            });
            return tr;
        }

        const allRows = Array.isArray(tableData.all_rows) && tableData.all_rows.length > 0 ? tableData.all_rows : rows;
        const total = typeof tableData.total === 'number' ? tableData.total : allRows.length;
        let currentlyShown = Math.min(tableData.shown || 50, allRows.length);

        for (let i = 0; i < currentlyShown; i++) {
            tbody.appendChild(renderRow(allRows[i]));
        }
        table.appendChild(tbody);
        wrap.appendChild(table);

        // Footer ("Menampilkan X dari Y" & "Muat 50 berikutnya") (Prompt 29D)
        const foot = document.createElement('div');
        foot.className = 'cfind-table-foot';

        const note = document.createElement('span');
        note.className = 'cfind-foot-note';
        function updateFootNote() {
            note.textContent = 'Menampilkan ' + currentlyShown + ' dari ' + total;
        }
        updateFootNote();
        foot.appendChild(note);

        if (total > currentlyShown) {
            const btnLoadMore = document.createElement('button');
            btnLoadMore.type = 'button';
            btnLoadMore.className = 'cfind-btn-show-all';
            btnLoadMore.textContent = 'Muat 50 berikutnya';
            btnLoadMore.addEventListener('click', function () {
                const nextTarget = Math.min(currentlyShown + 50, allRows.length);
                for (let i = currentlyShown; i < nextTarget; i++) {
                    tbody.appendChild(renderRow(allRows[i]));
                }
                currentlyShown = nextTarget;
                updateFootNote();
                if (currentlyShown >= total) {
                    btnLoadMore.remove();
                }
                // Prompt 29D: TANPA auto-scroll ke bawah dan tanpa menggeser posisi baca pengguna!
            });
            foot.appendChild(btnLoadMore);
        }
        wrap.appendChild(foot);

        return wrap;
    }

    // Helper: Build Safe Text DOM with **bold** formatting (No innerHTML, pure textContent & createTextNode)
    function buildTextDOM(textStr) {
        const p = document.createElement('p');
        p.className = 'cfind-text-block';

        const lines = String(textStr).split(/\r\n|\r|\n/);
        lines.forEach((line, lineIdx) => {
            if (lineIdx > 0) {
                p.appendChild(document.createElement('br'));
            }
            const parts = line.split(/(\*\*.*?\*\*)/g);
            parts.forEach(part => {
                if (part.startsWith('**') && part.endsWith('**') && part.length >= 4) {
                    const strong = document.createElement('strong');
                    strong.textContent = part.slice(2, -2);
                    p.appendChild(strong);
                } else if (part.length > 0) {
                    p.appendChild(document.createTextNode(part));
                }
            });
        });

        return p;
    }

    // Helper: Parse Markdown Table ke format Structured Block untuk dirender oleh buildTableDOM
    function parseMarkdownTableToBlocks(text) {
        const lines = text.split(/\r\n|\r|\n/);
        const introLines = [];
        const tableLines = [];
        let inTable = false;

        for (let i = 0; i < lines.length; i++) {
            const line = lines[i].trim();
            if (line.startsWith('|') && line.endsWith('|')) {
                inTable = true;
                tableLines.push(line);
            } else if (!inTable) {
                if (line) introLines.push(line);
            }
        }

        if (tableLines.length < 3) {
            return null;
        }

        const headerCells = tableLines[0].split('|').map(c => c.trim()).filter((c, idx, arr) => idx > 0 && idx < arr.length - 1);
        const columns = headerCells.map((h, idx) => ({
            key: idx === 0 ? 'row_no' : 'col_' + idx,
            label: h
        }));

        const rows = [];
        for (let i = 2; i < tableLines.length; i++) {
            const cells = tableLines[i].split('|').map(c => c.trim()).filter((c, idx, arr) => idx > 0 && idx < arr.length - 1);
            if (cells.length === 0) continue;
            const rowObj = {};
            columns.forEach((col, cIdx) => {
                rowObj[col.key] = cells[cIdx] || '-';
            });
            rows.push(rowObj);
        }

        const blocks = [];
        if (introLines.length > 0) {
            blocks.push({ type: 'text', content: introLines.join('\n') });
        }
        blocks.push({
            type: 'table',
            columns: columns,
            rows: rows,
            all_rows: rows,
            total: rows.length,
            shown: rows.length
        });

        return blocks;
    }

    // Append Message Bubble (DOM createElement, safe textContent, no innerHTML)
    function appendMessage(role, content) {
        const row = document.createElement('div');
        row.className = 'cfind-msg-row ' + (role === 'assistant' ? 'bot' : role);

        if (role === 'bot' || role === 'assistant') {
            const avatar = document.createElement('div');
            avatar.className = 'cfind-avatar-sm flex-shrink-0';
            avatar.setAttribute('aria-hidden', 'true');
            const icon = document.createElement('i');
            icon.className = 'bi bi-robot';
            avatar.appendChild(icon);
            row.appendChild(avatar);
        }

        const bubble = document.createElement('div');
        bubble.className = 'cfind-bubble ' + (role === 'assistant' ? 'bot' : role);

        let blocks = [];
        if (Array.isArray(content)) {
            blocks = content;
        } else if (content && typeof content === 'object' && content.type === 'table') {
            blocks = [content];
        } else if (typeof content === 'string') {
            const trimmed = content.trim();
            if ((trimmed.startsWith('[') && trimmed.endsWith(']')) || (trimmed.startsWith('{') && trimmed.endsWith('}'))) {
                try {
                    const parsed = JSON.parse(trimmed);
                    blocks = Array.isArray(parsed) ? parsed : [parsed];
                } catch (_) {
                    blocks = [{ type: 'text', content: content }];
                }
            } else if (trimmed.includes('| No |') || (trimmed.includes('|') && trimmed.includes('\n|') && trimmed.includes('---'))) {
                const parsedBlocks = parseMarkdownTableToBlocks(trimmed);
                if (parsedBlocks && parsedBlocks.length > 0) {
                    blocks = parsedBlocks;
                } else {
                    blocks = [{ type: 'text', content: content }];
                }
            } else {
                blocks = [{ type: 'text', content: content }];
            }
        } else {
            blocks = [{ type: 'text', content: String(content || '') }];
        }

        let hasTable = false;
        blocks.forEach(block => {
            if (!block) return;
            if (block.type === 'table') {
                hasTable = true;
                bubble.appendChild(buildTableDOM(block));
            } else {
                const textStr = block.content !== undefined ? String(block.content) : String(block);
                if (textStr.trim() !== '') {
                    bubble.appendChild(buildTextDOM(textStr));
                }
            }
        });

        if (hasTable) {
            bubble.classList.add('has-table');
        }

        row.appendChild(bubble);

        const scrollBottom = messages.scrollHeight - messages.scrollTop - messages.clientHeight;
        const isNearBottom = scrollBottom <= 80;

        // Insert before typing indicator
        if (typingIndicator) {
            messages.insertBefore(row, typingIndicator);
        } else {
            messages.appendChild(row);
        }

        // Auto-scroll ke bawah hanya jika user mengirim pesan atau sudah di dekat dasar (Prompt 29D)
        if (role === 'user' || isNearBottom) {
            messages.scrollTop = messages.scrollHeight;
            if (newMsgPill) newMsgPill.classList.add('d-none');
        } else {
            if (newMsgPill) newMsgPill.classList.remove('d-none');
        }
    }

    // Listener scroll untuk menghilangkan pill jika sudah di dasar, dan klik pill untuk scroll ke dasar
    if (messages && newMsgPill) {
        messages.addEventListener('scroll', function () {
            const scrollBottom = messages.scrollHeight - messages.scrollTop - messages.clientHeight;
            if (scrollBottom <= 80) {
                newMsgPill.classList.add('d-none');
            }
        });

        newMsgPill.addEventListener('click', function () {
            messages.scrollTo({ top: messages.scrollHeight, behavior: 'smooth' });
            newMsgPill.classList.add('d-none');
        });
    }

    // Muat Riwayat Tampilan Sesi Server via GET /api/cfind/history (Prompt 29C-3)
    async function loadHistoryFromServer() {
        try {
            const res = await fetch('/api/cfind/history', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            if (!res.ok) return;
            const data = await res.json();
            if (data && Array.isArray(data.messages) && data.messages.length > 0) {
                // Bersihkan pesan lama yang bukan welcome message default & bukan typing
                const oldRows = messages.querySelectorAll('.cfind-msg-row:not(:first-child):not(#cfind-typing)');
                oldRows.forEach(r => r.remove());

                data.messages.forEach(function (msg) {
                    appendMessage(msg.role === 'assistant' ? 'bot' : msg.role, msg.content);
                });
                if (chipsContainer) {
                    chipsContainer.style.display = 'none';
                }
            }
        } catch (_) {}
    }
    loadHistoryFromServer();

    // Show / Hide Typing Indicator
    function setTyping(show) {
        if (!typingIndicator) return;
        if (show) {
            typingIndicator.classList.remove('d-none');
            messages.scrollTop = messages.scrollHeight;
        } else {
            typingIndicator.classList.add('d-none');
        }
    }

    // Send Message Handler (mendukung kunci metrik & display_text untuk Chip Deterministik)
    async function sendMessage(text, displayText = null) {
        const clean = text.trim();
        if (!clean || isSending) return;

        isSending = true;
        if (input) {
            input.value = '';
        }
        if (charCount) {
            charCount.textContent = '0/300';
        }

        // Render User Message: gunakan displayText jika tersedia (misal teks chip yang ramah)
        const userShownText = (displayText && displayText.trim()) ? displayText.trim() : clean;
        appendMessage('user', userShownText);

        // Hide chips after user sends a message
        if (chipsContainer) {
            chipsContainer.style.display = 'none';
        }

        setTyping(true);

        const csrfToken = csrfInput ? csrfInput.value : '';

        // Timeout safety (60s)
        const timeoutController = new AbortController();
        const timeoutId = setTimeout(() => timeoutController.abort(), 62000);

        try {
            const bodyParams = new URLSearchParams({
                message: clean,
                _csrf_token: csrfToken,
            });
            if (displayText && displayText.trim()) {
                bodyParams.append('display_text', displayText.trim());
            }

            const res = await fetch('/api/cfind/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: bodyParams,
                signal: timeoutController.signal,
            });

            clearTimeout(timeoutId);
            setTyping(false);

            if (!res.ok) {
                const errData = await res.json().catch(() => null);
                const replyText = (errData && errData.reply) ? errData.reply : 'C-Find sedang sibuk, coba lagi.';
                appendMessage('bot', replyText);
                return;
            }

            let data = null;
            try {
                data = await res.json();
            } catch (jsonErr) {
                appendMessage('bot', 'C-Find sedang sibuk, coba lagi.');
                return;
            }
            const botReply = (data && data.reply) ? data.reply : 'C-Find sedang sibuk, coba lagi.';
            appendMessage('bot', botReply);

        } catch (err) {
            clearTimeout(timeoutId);
            setTyping(false);
            if (err.name === 'AbortError') {
                appendMessage('bot', 'Permintaan melebihi batas waktu (timeout). C-Find sedang sibuk, coba lagi.');
            } else {
                appendMessage('bot', 'Koneksi ke C-Find terganggu. Silakan periksa jaringan Anda atau coba sesaat lagi.');
            }
        } finally {
            isSending = false;
            if (input) input.focus();
        }
    }

    // Form Submit
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (input) sendMessage(input.value);
        });
    }

    // Chip Click: kirim kunci metrik deterministik bila ada, teks ramah sebagai display_text
    document.querySelectorAll('.cfind-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
            const metricKey = this.getAttribute('data-metric-key');
            const label = this.getAttribute('data-question') || this.textContent.trim();
            if (metricKey) {
                sendMessage(metricKey, label);
            } else {
                sendMessage(label);
            }
        });
    });

    // Reset Chat History dengan Konfirmasi AppDialog (Prompt 26 & 22D)
    if (btnReset) {
        btnReset.addEventListener('click', async function () {
            const confirmed = (typeof window.AppDialog !== 'undefined')
                ? await window.AppDialog.confirm({
                    title: 'Mulai Percakapan Baru',
                    message: 'Mulai percakapan baru dengan C-Find? Riwayat sesi saat ini akan dibersihkan.',
                    confirmText: 'Mulai Baru',
                    cancelText: 'Batal',
                    variant: 'warning',
                })
                : true;

            if (!confirmed) {
                return;
            }

            const csrfToken = csrfInput ? csrfInput.value : '';
            try {
                await fetch('/api/cfind/reset', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-Token': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new URLSearchParams({ _csrf_token: csrfToken }),
                });
            } catch (_) {}

            // Reset UI: bersihkan pesan kecuali bubble sambutan pertama dan typing indicator
            const rows = messages.querySelectorAll('.cfind-msg-row:not(:first-child):not(#cfind-typing)');
            rows.forEach(r => r.remove());

            if (chipsContainer) {
                chipsContainer.style.display = '';
            }

            if (input) {
                input.value = '';
                input.focus();
            }
            if (charCount) {
                charCount.textContent = '0/300';
            }
        });
    }

})();
</script>
