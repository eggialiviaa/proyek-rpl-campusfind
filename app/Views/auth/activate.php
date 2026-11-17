<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-primary text-white p-4 text-center border-0">
                    <div class="p-3 bg-white bg-opacity-25 rounded-circle d-inline-flex mb-2">
                        <i class="bi bi-shield-check fs-2"></i>
                    </div>
                    <h4 class="fw-bold mb-1">Informasi Akses Akun Campus Find</h4>
                    <p class="small text-white-50 mb-0">Kebijakan Akun Mahasiswa, Dosen, dan Tenaga Kependidikan</p>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($flashError)): ?>
                        <div class="alert alert-danger py-2 small rounded-3 mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                            <div><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($flashWarning)): ?>
                        <div class="alert alert-warning py-2 small rounded-3 mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i>
                            <div><?= htmlspecialchars($flashWarning, ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($flashInfo)): ?>
                        <div class="alert alert-info py-2 small rounded-3 mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-info-circle-fill flex-shrink-0"></i>
                            <div><?= htmlspecialchars($flashInfo, ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    <?php endif; ?>

                    <!-- Informasi Utama Sesuai Kebijakan TASK 02C -->
                    <div class="p-3 bg-light rounded-3 border mb-4">
                        <div class="d-flex align-items-start gap-3">
                            <i class="bi bi-info-circle-fill text-primary fs-4 mt-1 flex-shrink-0"></i>
                            <div>
                                <h6 class="fw-bold mb-1 text-dark">Pemberian Hak Akses &amp; Aktivasi Akun</h6>
                                <p class="small text-dark mb-2 fw-medium">
                                    Untuk mendapatkan akses Campus Find, silakan hubungi Administrator atau staf kampus.
                                </p>
                                <p class="small text-muted mb-0">
                                    Sistem Campus Find tidak menyediakan pendaftaran atau aktivasi akun secara mandiri oleh publik. Seluruh akun civitas didaftarkan secara resmi oleh Administrator melalui sinkronisasi data akademik dengan kredensial kata sandi sementara.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2 mb-3">
                        <a href="/login" class="btn btn-primary py-2.5 fw-semibold rounded-pill shadow-sm text-center" id="btn-goto-login">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Masuk ke Halaman Login
                        </a>
                        <a href="/forgot-password" class="btn btn-outline-secondary py-2 fw-semibold rounded-pill text-center" id="btn-goto-forgot">
                            <i class="bi bi-key me-1"></i> Pemulihan Kata Sandi Akun Aktif
                        </a>
                    </div>

                    <div class="text-center pt-2 border-top">
                        <span class="text-muted small">Punya pertanyaan seputar layanan Lost &amp; Found? Kunjungi Posko Lost &amp; Found di Gedung Rektorat / Hubungi Layanan Kampus.</span>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
