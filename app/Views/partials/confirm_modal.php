<?php
/**
 * app/Views/partials/confirm_modal.php
 * Komponen Dialog Terpadu (AppDialog): Konfirmasi Tindakan & Hasil Penting
 * 
 * Desain & Aksesibilitas (Prompt 32D):
 * - Selalu berada di tengah layar (modal-dialog-centered)
 * - Tiga bentuk, SATU komponen (AppDialog / AppToast)
 * - Touch target tombol >= 44px (min-height: 44px, min-width: 44px)
 * - Tanpa horizontal overflow di perangkat mobile (max-width: min(440px, 92vw))
 * - Aksesibilitas: aria-labelledby, aria-describedby, focus trap, Esc menutup modal
 */
?>
<div class="modal fade" id="cf-confirm-modal" tabindex="-1" aria-labelledby="cfConfirmModalLabel" aria-describedby="cfConfirmModalDesc" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: min(440px, 92vw); margin-left: auto; margin-right: auto;">
        <div class="modal-content shadow-lg border-0 rounded-4 overflow-hidden" style="background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); box-shadow: var(--cf-shadow-modal, 0 20px 45px rgba(20, 40, 60, 0.2));">
            
            <!-- Header Modal -->
            <div class="modal-header border-0 pb-1 pt-4 px-4">
                <div class="d-flex align-items-center gap-3 w-100">
                    <span id="cf-confirm-icon-wrapper" class="cf-confirm-icon-wrapper d-inline-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 46px; height: 46px; background: var(--cf-primary-light, rgba(20, 40, 60, 0.08));">
                        <i id="cf-confirm-icon" class="bi bi-question-circle-fill text-primary fs-4"></i>
                    </span>
                    <div class="flex-grow-1 overflow-hidden">
                        <h5 class="modal-title fs-5 fw-bold text-dark mb-0 lh-sm" id="cfConfirmModalLabel">
                            Konfirmasi Tindakan
                        </h5>
                    </div>
                    <button type="button" class="btn-close shadow-none flex-shrink-0" data-bs-dismiss="modal" aria-label="Tutup" style="min-width: 44px; min-height: 44px; padding: 12px;"></button>
                </div>
            </div>

            <!-- Body Modal -->
            <div class="modal-body py-3 px-4">
                <p id="cfConfirmModalDesc" class="modal-body-text text-secondary mb-0 lh-base" style="font-size: 0.95rem; word-break: break-word;">
                    Apakah Anda yakin ingin melanjutkan tindakan ini?
                </p>
            </div>

            <!-- Footer Modal (Tombol Interaksi) -->
            <div class="modal-footer border-0 pt-2 pb-4 px-4 d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary px-4 flex-grow-1 rounded-pill fw-semibold" id="cf-confirm-btn-cancel" data-bs-dismiss="modal" style="min-height: 44px; min-width: 44px; font-size: 0.95rem;">
                    Batal
                </button>
                <button type="button" class="btn btn-primary px-4 flex-grow-1 rounded-pill fw-semibold shadow-sm" id="cf-confirm-btn-ok" style="min-height: 44px; min-width: 120px; font-size: 0.95rem; background: #14283C; color: #FFFFFF; border: 1px solid rgba(20, 40, 60, 0.2);">
                    Lanjutkan
                </button>
            </div>

        </div>
    </div>
</div>
<?php require_once __DIR__ . '/ai_scanner_modal.php'; ?>

