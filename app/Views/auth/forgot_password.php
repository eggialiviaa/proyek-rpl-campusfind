<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-dark text-white p-4 text-center border-0" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);">
                    <div class="p-3 bg-white bg-opacity-10 rounded-circle d-inline-flex mb-2">
                        <i class="bi bi-key-fill text-warning fs-2"></i>
                    </div>
                    <h4 class="fw-bold mb-1">Lupa Password?</h4>
                    <p class="small text-white-50 mb-0">Masukkan email terdaftar untuk menerima kode verifikasi OTP.</p>
                </div>
                <div class="card-body p-4">
                    <?php 
                    $displayError = $inlineError ?? $flashError ?? '';
                    $hasError = !empty($displayError);
                    ?>

                    <?php if ($hasError): ?>
                        <div id="forgot-inline-error" class="alert alert-danger py-2 px-3 small rounded-3 mb-3 d-flex align-items-center gap-2 border-danger-subtle bg-danger-subtle text-danger">
                            <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                            <div class="fw-medium"><?= htmlspecialchars($displayError, ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($flashInfo)): ?>
                        <div class="alert alert-info py-2 small rounded-3 mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-info-circle-fill"></i>
                            <div><?= htmlspecialchars($flashInfo, ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="/forgot-password" id="form-forgot-password" novalidate>
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        
                        <div class="mb-3">
                            <label for="identity" class="form-label fw-semibold small text-dark">
                                Email Terdaftar
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0">
                                    <i class="bi bi-envelope"></i>
                                </span>
                                <input type="text"
                                       id="identity"
                                       name="identity"
                                       class="form-control border-start-0 <?= $hasError ? 'is-invalid border-danger' : '' ?>"
                                       placeholder="nama@kampus.ac.id"
                                       value="<?= htmlspecialchars($prefillIdentity ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                       required
                                       autofocus>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2.5 fw-semibold rounded-pill shadow-sm mb-3" id="btn-submit-forgot">
                            <i class="bi bi-send me-1"></i> Kirim Kode Verifikasi
                        </button>

                        <div class="text-center pt-2 border-top">
                            <span class="text-muted small">Ingat kata sandi Anda?</span>
                            <a href="/login" class="small fw-semibold text-decoration-none ms-1">Kembali Masuk</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const inputIdentity = document.getElementById('identity');
    const inlineError = document.getElementById('forgot-inline-error');
    if (inputIdentity) {
        inputIdentity.addEventListener('input', function() {
            this.classList.remove('is-invalid', 'border-danger');
            if (inlineError) {
                inlineError.style.display = 'none';
            }
        });
    }
});
</script>
