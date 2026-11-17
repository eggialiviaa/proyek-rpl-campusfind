<?php
/**
 * app/Views/partials/ai_scanner_modal.php
 * Scanning Animation Modal (TASK 11)
 *
 * Palet Warna Desain:
 * - Soft White: #f8f9fa
 * - Sage Green: #84a98c / #52796f
 * - Deep Olive: #354f52 / #2f3e46
 */
?>
<div class="modal fade" id="cf-ai-scanner-modal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: min(440px, 92vw); margin-left: auto; margin-right: auto;">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" style="background-color: var(--cf-scanner-bg, #f8f9fa); border: 1px solid var(--cf-scanner-border, rgba(132, 169, 140, 0.25));">
            <div class="modal-body text-center p-5">
                <!-- Visual Animated Radar / Laser Scanner -->
                <div class="cf-radar-container mx-auto mb-4">
                    <div class="cf-radar-ripple ripple-1"></div>
                    <div class="cf-radar-ripple ripple-2"></div>
                    <div class="cf-radar-scan-line"></div>
                    <div class="cf-radar-center">
                        <i class="bi bi-search-heart fs-3" style="color: var(--cf-scanner-sage-deep, #52796f);"></i>
                    </div>
                </div>

                <!-- Text Requirements -->
                <h4 class="fw-bold mb-2" style="color: var(--cf-scanner-text, #2f3e46); font-size: 1.25rem;">
                    Sedang mencari kemungkinan kecocokan...
                </h4>
                <p class="text-muted small mx-auto mb-4" style="max-width: 320px; line-height: 1.5;">
                    Campus Find sedang memeriksa deskripsi, kategori, lokasi, dan waktu laporan.
                </p>

                <!-- Soft Progress Bar in Sage Green -->
                <div class="progress mx-auto" style="height: 6px; max-width: 260px; background-color: var(--cf-scanner-border, rgba(132, 169, 140, 0.25)); border-radius: 10px; overflow: hidden;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%; background-color: var(--cf-scanner-sage, #84a98c);"></div>
                </div>
                <div class="text-muted mt-3" style="font-size: 0.75rem;">
                    <i class="bi bi-shield-check me-1"></i> Pemrosesan berjalan di latar belakang (non-blocking)
                </div>
            </div>
        </div>
    </div>
</div>

<style>
#cf-ai-scanner-modal {
    --cf-scanner-sage: #84a98c;
    --cf-scanner-sage-deep: #52796f;
    --cf-scanner-text: #2f3e46;
    --cf-scanner-bg: #f8f9fa;
    --cf-scanner-border: rgba(132, 169, 140, 0.25);
    --cf-scanner-ripple-glow: rgba(132, 169, 140, 0.18);
    --cf-scanner-sweep: rgba(82, 121, 111, 0.40);
    --cf-scanner-shadow: rgba(53, 79, 82, 0.12);
}
.cf-radar-container {
    width: 110px;
    height: 110px;
    position: relative;
    border-radius: 50%;
    background: radial-gradient(circle, var(--cf-scanner-ripple-glow) 0%, rgba(248, 249, 250, 0) 70%);
    display: flex;
    align-items: center;
    justify-content: center;
}
.cf-radar-ripple {
    position: absolute;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    border: 2px solid var(--cf-scanner-sage);
    animation: cfPulseRipple 2s cubic-bezier(0.215, 0.61, 0.355, 1) infinite;
    opacity: 0;
}
.cf-radar-ripple.ripple-2 {
    animation-delay: 0.75s;
}
.cf-radar-scan-line {
    position: absolute;
    top: 0;
    left: 50%;
    width: 50%;
    height: 50%;
    transform-origin: 0% 100%;
    background: linear-gradient(45deg, var(--cf-scanner-sweep), transparent);
    animation: cfRadarSweep 2.2s linear infinite;
    border-radius: 0 100% 0 0;
}
.cf-radar-center {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: #ffffff;
    box-shadow: 0 4px 12px var(--cf-scanner-shadow);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2;
}

@keyframes cfPulseRipple {
    0% { transform: scale(0.6); opacity: 0.8; }
    100% { transform: scale(1.35); opacity: 0; }
}
@keyframes cfRadarSweep {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@media (prefers-reduced-motion: reduce) {
    .cf-radar-ripple,
    .cf-radar-scan-line,
    .progress-bar-animated {
        animation: none !important;
    }
}
</style>

