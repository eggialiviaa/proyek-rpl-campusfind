<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-dark text-white p-4 text-center border-0" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);">
                    <div class="p-3 bg-white bg-opacity-10 rounded-circle d-inline-flex mb-2">
                        <i class="bi bi-shield-check text-warning fs-2"></i>
                    </div>
                    <h4 class="fw-bold mb-1">Verifikasi Kode &amp; Kata Sandi Baru</h4>
                    <p class="small text-white-50 mb-0">
                        Kode 6 digit telah dikirimkan ke <?= !empty($maskedEmail) ? '<strong class="text-white">' . htmlspecialchars($maskedEmail, ENT_QUOTES, 'UTF-8') . '</strong>' : 'email terdaftar Anda' ?> (berlaku 5 menit)
                    </p>
                </div>
                <div class="card-body p-4">
                    <div class="alert alert-light border small text-muted py-2 px-3 rounded-3 mb-3 d-flex align-items-center gap-2">
                        <i class="bi bi-info-circle text-primary flex-shrink-0"></i>
                        <div>
                            Kode OTP hanya berlaku <strong>5 menit</strong>. Jika tidak muncul di Kotak Masuk, periksa folder <strong>Spam / Junk</strong> Anda.
                        </div>
                    </div>
                    <?php if (!empty($flashError)): ?>
                        <div class="alert alert-danger py-2 small rounded-3 mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                            <div><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($flashInfo)): ?>
                        <div class="alert alert-info py-2 small rounded-3 mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-info-circle-fill flex-shrink-0"></i>
                            <div><?= htmlspecialchars($flashInfo, ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="/forgot-password/verify" id="form-verify-forgot">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                        <!-- Input OTP 6 Digit -->
                        <div class="mb-3 text-center">
                            <label for="otp_code" class="form-label fw-bold small text-dark mb-1">
                                Masukkan 6-Digit Kode Verifikasi OTP
                            </label>
                            <input type="text"
                                   id="otp_code"
                                   name="otp_code"
                                   class="form-control text-center font-monospace fs-3 fw-bold tracking-widest border-2"
                                   placeholder="000000"
                                   maxlength="6"
                                   pattern="\d{6}"
                                   inputmode="numeric"
                                   autocomplete="one-time-code"
                                   style="letter-spacing: 8px;"
                                   required
                                   autofocus>
                            <div class="extra-small text-muted mt-1">
                                <i class="bi bi-shield-exclamation text-danger me-1"></i> Jangan bagikan kode ini kepada siapa pun.
                            </div>
                        </div>

                        <!-- Kata Sandi Baru -->
                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold small text-dark mb-1">
                                Kata Sandi Baru
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-lock"></i></span>
                                <input type="password"
                                       id="password"
                                       name="password"
                                       class="form-control border-start-0 border-end-0"
                                       placeholder="Minimal 8 karakter (huruf besar, kecil, angka/simbol)"
                                       autocomplete="new-password"
                                       required>
                                <button type="button" class="btn btn-outline-secondary border-start-0" id="btn-toggle-password" aria-label="Tampilkan kata sandi baru" aria-pressed="false" onclick="togglePasswordVisibility('password', this)">
                                    <i class="bi bi-eye" id="toggle-icon-password"></i>
                                </button>
                            </div>
                            
                            <!-- Strength Indicator -->
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

                        <!-- Submit Button -->
                        <button type="submit" class="btn btn-primary w-100 py-2.5 fw-semibold rounded-pill shadow-sm mb-3" id="btn-submit-reset">
                            <i class="bi bi-check-circle me-1"></i> Simpan Kata Sandi Baru
                        </button>

                        <!-- Resend OTP dengan Countdown Timer 60 Detik -->
                        <div class="text-center pt-2 border-top">
                            <span class="small text-muted me-1">Tidak menerima email?</span>
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-semibold" id="btn-resend-otp" onclick="resendOtp()">
                                Kirim Ulang Kode OTP
                            </button>
                            <div id="resend-countdown" class="extra-small text-muted mt-1 d-none">
                                Harap tunggu <span id="timer-seconds">60</span> detik sebelum meminta kode baru.
                            </div>
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

let resendTimer = null;
let remainingCooldown = 60;

function startCooldown(seconds) {
    const btn = document.getElementById('btn-resend-otp');
    const cdBox = document.getElementById('resend-countdown');
    const timerSec = document.getElementById('timer-seconds');
    if (!btn || !cdBox || !timerSec) return;

    remainingCooldown = seconds;
    btn.disabled = true;
    btn.classList.add('text-muted');
    cdBox.classList.remove('d-none');
    timerSec.innerText = remainingCooldown;

    clearInterval(resendTimer);
    resendTimer = setInterval(() => {
        remainingCooldown--;
        timerSec.innerText = remainingCooldown;
        if (remainingCooldown <= 0) {
            clearInterval(resendTimer);
            btn.disabled = false;
            btn.classList.remove('text-muted');
            cdBox.classList.add('d-none');
        }
    }, 1000);
}

startCooldown(60);

async function resendOtp() {
    const btn = document.getElementById('btn-resend-otp');
    btn.disabled = true;

    try {
        const formData = new FormData();
        formData.append('_csrf_token', '<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>');

        const res = await fetch('/auth/resend-otp', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        });
        const data = await res.json();

        if (data.success) {
            if (window.AppToast) {
                AppToast.success(data.message || 'Kode verifikasi baru berhasil dikirimkan.');
            }
            startCooldown(60);
        } else {
            const msg = data.message || 'Gagal mengirim ulang kode OTP.';
            if (window.AppToast) {
                AppToast.error(msg);
            }
            if (data.cooldown && data.remaining_seconds) {
                startCooldown(data.remaining_seconds);
            } else {
                btn.disabled = false;
            }
        }
    } catch (err) {
        btn.disabled = false;
        if (window.AppToast) {
            AppToast.error('Terjadi gangguan koneksi jaringan saat mengirim ulang kode.');
        }
    }
}
</script>
