<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden border-top border-4 border-warning">
                <div class="card-header bg-white p-4 text-center border-0">
                    <div class="p-3 bg-warning-subtle text-warning rounded-circle d-inline-flex mb-2">
                        <i class="bi bi-shield-lock-fill fs-2"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-1">Wajib Buat Kata Sandi Baru</h4>
                    <p class="small text-muted mb-0">
                        Akun Anda saat ini menggunakan kata sandi sementara dari Administrator. Demi keamanan, Anda wajib membuat kata sandi pribadi sebelum melanjutkan.
                    </p>
                </div>
                <div class="card-body p-4 pt-0">
                    <?php if (!empty($flashError)): ?>
                        <div class="alert alert-danger py-2 small rounded-3 mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                            <div><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($flashWarning)): ?>
                        <div class="alert alert-warning py-2 small rounded-3 mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-info-circle-fill flex-shrink-0"></i>
                            <div><?= htmlspecialchars($flashWarning, ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="/change-password-mandatory" id="form-mandatory-change">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                        <!-- Kata Sandi Baru -->
                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold small text-dark mb-1">
                                Kata Sandi Baru Anda
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-key"></i></span>
                                <input type="password"
                                       id="password"
                                       name="password"
                                       class="form-control border-start-0 border-end-0"
                                       placeholder="Minimal 8 karakter (huruf besar, kecil, angka/simbol)"
                                       autocomplete="new-password"
                                       required
                                       autofocus>
                                <button type="button" class="btn btn-outline-secondary border-start-0" id="btn-toggle-password" aria-label="Tampilkan kata sandi baru" aria-pressed="false" onclick="togglePasswordVisibility('password', this)">
                                    <i class="bi bi-eye" id="toggle-icon-password"></i>
                                </button>
                            </div>
                            
                            <div class="progress mt-2" style="height: 5px;">
                                <div id="pwd-strength-bar" class="progress-bar bg-danger" role="progressbar" style="width: 0%;"></div>
                            </div>
                            <div id="pwd-strength-feedback" class="extra-small text-muted mt-1">
                                Kekuatan: <span class="fw-semibold">Belum dimasukkan</span>
                            </div>
                        </div>

                        <!-- Konfirmasi Kata Sandi -->
                        <div class="mb-4">
                            <label for="password_confirmation" class="form-label fw-semibold small text-dark mb-1">
                                Ulangi Kata Sandi Baru
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-check2-circle"></i></span>
                                <input type="password"
                                       id="password_confirmation"
                                       name="password_confirmation"
                                       class="form-control border-start-0 border-end-0"
                                       placeholder="Ketik ulang kata sandi baru"
                                       autocomplete="new-password"
                                       required>
                                <button type="button" class="btn btn-outline-secondary border-start-0" id="btn-toggle-password-confirmation" aria-label="Tampilkan konfirmasi kata sandi" aria-pressed="false" onclick="togglePasswordVisibility('password_confirmation', this)">
                                    <i class="bi bi-eye" id="toggle-icon-password_confirmation"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-warning w-100 py-2.5 fw-bold text-dark rounded-pill shadow-sm mb-3">
                            <i class="bi bi-shield-check me-1"></i> Simpan Kata Sandi &amp; Masuk ke Dasbor
                        </button>

                        <div class="text-center pt-2 border-top">
                            <a href="/logout" class="small text-muted text-decoration-none">
                                <i class="bi bi-box-arrow-left me-1"></i> Keluar dari sesi saat ini
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById('toggle-icon-' + inputId);
    if (!input) return;
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    if (icon) {
        icon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
    }
    if (btn) {
        btn.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
        btn.setAttribute('aria-label', isPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
    }
}

document.getElementById('password')?.addEventListener('input', function(e) {
    const val = e.target.value;
    const bar = document.getElementById('pwd-strength-bar');
    const feedback = document.getElementById('pwd-strength-feedback');
    if (!bar || !feedback) return;

    let score = 0;
    if (val.length >= 8) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[a-z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[\W_]/.test(val)) score++;

    if (val.length === 0) {
        bar.style.width = '0%';
        bar.className = 'progress-bar bg-secondary';
        feedback.innerHTML = 'Kekuatan: <span class="fw-semibold">Belum dimasukkan</span>';
    } else if (score <= 2) {
        bar.style.width = '30%';
        bar.className = 'progress-bar bg-danger';
        feedback.innerHTML = 'Kekuatan: <span class="text-danger fw-semibold">Lemah (Minimal 8 karakter, kombinasi huruf besar, kecil, angka/simbol)</span>';
    } else if (score === 3 || score === 4) {
        bar.style.width = '70%';
        bar.className = 'progress-bar bg-warning';
        feedback.innerHTML = 'Kekuatan: <span class="text-warning fw-semibold">Cukup Kuat</span>';
    } else {
        bar.style.width = '100%';
        bar.className = 'progress-bar bg-success';
        feedback.innerHTML = 'Kekuatan: <span class="text-success fw-semibold">Sangat Kuat</span>';
    }
});
</script>
