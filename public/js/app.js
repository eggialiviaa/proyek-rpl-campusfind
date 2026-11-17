/**
 * Campus Find — app.js
 * Liquid Glass UI & Micro-interactions Helper
 */

'use strict';

document.addEventListener('DOMContentLoaded', function () {

    // 0. Inisialisasi Lucide Icons Modern SVG Engine
    initLucideIcons();

    // 1. Inisialisasi Bootstrap Tooltips & Popovers
    initBootstrapTooltips();

    // 2. Inisialisasi Flash Messages (auto-dismiss & smooth close)
    initFlashMessages();

    // 3. Highlight Active Navigation
    highlightActiveNavLink();

    // 4. Inisialisasi Sidebar Mobile Toggle (App Shell)
    initSidebarToggle();

    // 5. Inisialisasi Confirmation Modal Helper
    initConfirmDialogs();

});

/**
 * Bootstrap Tooltips & Popovers
 */
function initBootstrapTooltips() {
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipTriggerList.forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
    popoverTriggerList.forEach(function (el) {
        new bootstrap.Popover(el);
    });
}

/**
 * Flash Messages: auto-dismiss setelah 5 detik
 */
function initFlashMessages() {
    const flashItems = document.querySelectorAll('.cf-flash');

    flashItems.forEach(function (flash, index) {
        const delay = 5000 + (index * 500);
        setTimeout(function () {
            dismissFlash(flash);
        }, delay);

        const closeBtn = flash.querySelector('.cf-flash-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                dismissFlash(flash);
            });
        }
    });
}

function dismissFlash(el) {
    el.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
    el.style.opacity = '0';
    el.style.transform = 'translateX(100%)';
    setTimeout(function () {
        if (el.parentNode) {
            el.parentNode.removeChild(el);
        }
    }, 300);
}

/**
 * Tandai active nav link berdasarkan URL path
 */
function highlightActiveNavLink() {
    const currentPath = window.location.pathname;

    // Catatan PAGE-00B.2.1: Navigasi sidebar terautentikasi (.cf-sidebar-link) dikelola secara
    // presisi & deterministik oleh server-side rendering (sidebar.php). Prefix matching publik
    // di bawah tidak boleh menimpa active state sidebar agar tidak terjadi duplikasi active.
    const navLinks = document.querySelectorAll('.cf-navbar .nav-link, .cf-links a, .cf-m a');
    navLinks.forEach(function (link) {
        const href = link.getAttribute('href');
        if (!href) return;

        if (href === currentPath || (href !== '/' && href !== '/dashboard' && currentPath.startsWith(href))) {
            link.classList.add('active');
            link.setAttribute('aria-current', 'page');
        }
    });
}

/**
 * Sidebar Mobile Toggle (Drawer on Tablet/Mobile)
 */
function initSidebarToggle() {
    // Guard: Jika berada di dalam authenticated app_shell, drawer dikelola oleh initCampusFindAppShell()
    if (document.getElementById('cf-shell-wrapper')) {
        return;
    }

    const toggleBtn = document.getElementById('cf-sidebar-toggle');
    const sidebar   = document.getElementById('cf-sidebar');
    const backdrop  = document.getElementById('cf-sidebar-backdrop');

    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function () {
            sidebar.classList.toggle('show');
            if (backdrop) {
                backdrop.classList.toggle('show');
            }
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', function () {
            if (sidebar) sidebar.classList.remove('show');
            backdrop.classList.remove('show');
        });
    }
}

/**
 * Global AppDialog Helper (Bootstrap 5 Modal based confirmation & alert)
 * Menggantikan dialog bawaan browser (confirm, alert, prompt).
 */
const AppDialog = {
    /**
     * Konfirmasi interaktif dengan Promise
     * @param {Object} opts { title, message, confirmText, cancelText, variant }
     * @returns {Promise<boolean>}
     */
    confirm: function (opts) {
        opts = opts || {};
        return new Promise(function (resolve) {
            const modalEl = document.getElementById('cf-confirm-modal');
            if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                console.warn('AppDialog: Modal element or Bootstrap 5 Modal not found.');
                resolve(true);
                return;
            }

            const triggerEl   = opts.triggerEl || document.activeElement;
            const title       = opts.title || 'Konfirmasi Tindakan';
            const message     = opts.message || 'Apakah Anda yakin ingin melanjutkan tindakan ini?';
            const variant     = opts.variant || 'primary';
            const confirmText = opts.confirmText || (variant === 'danger' ? 'Hapus' : (variant === 'warning' ? 'Setel Ulang' : 'Lanjutkan'));
            const cancelText  = opts.cancelText || 'Batal';

            const titleEl     = modalEl.querySelector('#cfConfirmModalLabel');
            const descEl      = modalEl.querySelector('#cfConfirmModalDesc');
            const okBtn       = modalEl.querySelector('#cf-confirm-btn-ok');
            const cancelBtn   = modalEl.querySelector('#cf-confirm-btn-cancel');
            const iconWrapper = modalEl.querySelector('#cf-confirm-icon-wrapper');
            const iconEl      = modalEl.querySelector('#cf-confirm-icon');

            if (titleEl) titleEl.textContent = title;
            if (descEl)  descEl.textContent  = message;

            if (cancelBtn) {
                cancelBtn.textContent = cancelText;
                cancelBtn.style.display = '';
            }

            if (okBtn) {
                okBtn.textContent = confirmText;
                okBtn.className = 'btn px-4 flex-grow-1 rounded-pill fw-semibold shadow-sm';
                if (variant === 'danger') {
                    okBtn.classList.add('btn-danger');
                } else if (variant === 'warning') {
                    okBtn.classList.add('btn-warning', 'text-dark');
                } else if (variant === 'success') {
                    okBtn.classList.add('btn-success');
                } else {
                    okBtn.classList.add('btn-primary');
                }
            }

            if (iconWrapper && iconEl) {
                if (variant === 'danger') {
                    iconWrapper.style.background = 'var(--cf-danger-bg)';
                    iconEl.className = 'bi bi-exclamation-triangle-fill text-danger fs-4';
                } else if (variant === 'warning') {
                    iconWrapper.style.background = 'var(--cf-warning-bg)';
                    iconEl.className = 'bi bi-exclamation-circle-fill text-warning fs-4';
                } else if (variant === 'success') {
                    iconWrapper.style.background = 'var(--cf-success-bg)';
                    iconEl.className = 'bi bi-check-circle-fill text-success fs-4';
                } else {
                    iconWrapper.style.background = 'var(--cf-primary-light)';
                    iconEl.className = 'bi bi-question-circle-fill text-primary fs-4';
                }
            }

            let resolved = false;
            const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);

            function handleConfirm() {
                resolved = true;
                bsModal.hide();
                resolve(true);
            }

            function handleHidden() {
                if (okBtn) okBtn.removeEventListener('click', handleConfirm);
                modalEl.removeEventListener('hidden.bs.modal', handleHidden);
                if (!resolved) {
                    resolve(false);
                }
                if (triggerEl && typeof triggerEl.focus === 'function') {
                    try { triggerEl.focus(); } catch (_) {}
                }
            }

            if (okBtn) okBtn.addEventListener('click', handleConfirm, { once: true });
            modalEl.addEventListener('hidden.bs.modal', handleHidden, { once: true });

            modalEl.addEventListener('shown.bs.modal', function onShown() {
                modalEl.removeEventListener('shown.bs.modal', onShown);
                if (variant === 'danger' && cancelBtn) {
                    cancelBtn.focus();
                } else if (okBtn) {
                    okBtn.focus();
                }
            }, { once: true });

            bsModal.show();
        });
    },

    /**
     * Modal Hasil Penting di Tengah Layar (Prompt 32D)
     * Menampilkan hasil dengan satu tombol "Mengerti", ikon status, judul, dan penjelasan spesifik
     * 
     * @param {Object} opts { title, message, okText, variant, triggerEl }
     * @returns {Promise<void>}
     */
    result: function (opts) {
        opts = opts || {};
        return new Promise(function (resolve) {
            const modalEl = document.getElementById('cf-confirm-modal');
            if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                AppToast.show(opts.variant || 'info', opts.message || '');
                resolve();
                return;
            }

            let triggerEl     = opts.triggerEl || document.activeElement;
            let title         = opts.title || 'Pemberitahuan Hasil';
            let message       = opts.message || '';
            const okText      = opts.okText || 'Mengerti';
            const variant     = (opts.variant === 'error' || opts.variant === 'danger') ? 'danger' : (opts.variant || 'success');

            // Dynamic time-based greeting for login notifications (sesuai jam sistem / bawaan)
            if (typeof message === 'string' && (message.includes('Selamat datang kembali') || message.includes('Selamat'))) {
                const now = new Date();
                const h = now.getHours();
                const timeGreeting = (h >= 4 && h < 11) ? 'Selamat pagi' : ((h >= 11 && h < 15) ? 'Selamat siang' : ((h >= 15 && h < 18) ? 'Selamat sore' : 'Selamat malam'));
                message = message.replace(/^Selamat datang kembali,\s*([^!]+)!\s*/i, `${timeGreeting}, $1! `);
                message = message.replace(/^Selamat (pagi|siang|sore|malam),\s*([^!]+)!\s*/i, `${timeGreeting}, $2! `);
            }

            const titleEl     = modalEl.querySelector('#cfConfirmModalLabel');
            const descEl      = modalEl.querySelector('#cfConfirmModalDesc');
            const okBtn       = modalEl.querySelector('#cf-confirm-btn-ok');
            const cancelBtn   = modalEl.querySelector('#cf-confirm-btn-cancel');
            const iconWrapper = modalEl.querySelector('#cf-confirm-icon-wrapper');
            const iconEl      = modalEl.querySelector('#cf-confirm-icon');

            // Bersihkan URL mentah jika terdapat di dalam pesan teks modal
            if (opts.actionUrl && typeof message === 'string' && message.includes(opts.actionUrl)) {
                message = message.replace(opts.actionUrl, '').trim();
            }

            if (titleEl) titleEl.textContent = title;
            if (descEl)  descEl.textContent  = message;

            // Tombol "Mengerti" hanya menutup notifikasi; jika ada actionUrl/actionText, sediakan tombol aksi & Tutup
            if (cancelBtn) {
                if (opts.actionUrl || opts.actionText || opts.showCancel || opts.cancelText) {
                    cancelBtn.style.display = '';
                    cancelBtn.textContent = opts.cancelText || 'Tutup';
                    cancelBtn.className = 'btn btn-outline-secondary px-4 rounded-pill fw-semibold';
                } else {
                    cancelBtn.style.display = 'none';
                }
            }

            if (okBtn) {
                okBtn.textContent = opts.actionText || (opts.actionUrl ? 'Lihat Laporan' : okText);
                okBtn.className = 'btn px-4 flex-grow-1 rounded-pill fw-semibold shadow-sm';
                okBtn.style.color = '#FFFFFF';
                if (variant === 'danger') {
                    okBtn.classList.add('btn-danger');
                    okBtn.style.background = 'var(--cf-danger, #DC2626)';
                    okBtn.style.backgroundColor = '#DC2626';
                    okBtn.style.borderColor = '#DC2626';
                } else if (variant === 'warning') {
                    okBtn.classList.add('btn-warning', 'text-dark');
                    okBtn.style.color = '#14283C';
                    okBtn.style.background = 'var(--cf-warning, #F59E0B)';
                    okBtn.style.backgroundColor = '#F59E0B';
                    okBtn.style.borderColor = '#F59E0B';
                } else {
                    okBtn.classList.add('btn-primary');
                    okBtn.style.background = '#14283C';
                    okBtn.style.backgroundColor = '#14283C';
                    okBtn.style.borderColor = '#14283C';
                    okBtn.style.color = '#FFFFFF';
                }
            }

            if (iconWrapper && iconEl) {
                if (variant === 'danger') {
                    iconWrapper.style.background = 'var(--cf-danger-bg, rgba(220, 38, 38, 0.12))';
                    iconEl.className = 'bi bi-exclamation-triangle-fill text-danger fs-4';
                } else if (variant === 'warning') {
                    iconWrapper.style.background = 'var(--cf-warning-bg, rgba(245, 158, 11, 0.12))';
                    iconEl.className = 'bi bi-exclamation-circle-fill text-warning fs-4';
                } else if (variant === 'success') {
                    iconWrapper.style.background = 'var(--cf-success-bg, rgba(45, 125, 70, 0.12))';
                    iconEl.className = 'bi bi-check-circle-fill text-success fs-4';
                } else {
                    iconWrapper.style.background = 'var(--cf-primary-light, rgba(20, 40, 60, 0.08))';
                    iconEl.className = 'bi bi-info-circle-fill text-primary fs-4';
                }
                // Animasi ceklis / status icon pop
                iconWrapper.classList.remove('cf-confirm-icon-pop');
                void iconWrapper.offsetWidth; // force reflow
                iconWrapper.classList.add('cf-confirm-icon-pop');
            }

            const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);

            function handleOkClick() {
                if (opts.actionUrl) {
                    window.location.href = opts.actionUrl;
                    return;
                }
                if (typeof opts.onOk === 'function') {
                    opts.onOk();
                }
                bsModal.hide();
            }

            function handleCancelClick() {
                if (typeof opts.onCancel === 'function') {
                    opts.onCancel();
                }
                bsModal.hide();
            }

            function handleHidden() {
                if (okBtn) okBtn.removeEventListener('click', handleOkClick);
                if (cancelBtn) cancelBtn.removeEventListener('click', handleCancelClick);
                modalEl.removeEventListener('hidden.bs.modal', handleHidden);
                if (cancelBtn) cancelBtn.style.display = '';
                if (triggerEl && typeof triggerEl.focus === 'function') {
                    try { triggerEl.focus(); } catch (_) {}
                }
                resolve();
            }

            if (okBtn) okBtn.addEventListener('click', handleOkClick, { once: true });
            if (cancelBtn) cancelBtn.addEventListener('click', handleCancelClick, { once: true });
            modalEl.addEventListener('hidden.bs.modal', handleHidden, { once: true });

            modalEl.addEventListener('shown.bs.modal', function onShown() {
                modalEl.removeEventListener('shown.bs.modal', onShown);
                if (okBtn) okBtn.focus();
            }, { once: true });

            bsModal.show();
        });
    },

    // Shortcut helper methods untuk hasil penting
    alert: function (opts) {
        return this.result(opts);
    },
    success: function (title, message, okText) {
        return this.result({ title: title, message: message, variant: 'success', okText: okText });
    },
    error: function (title, message, okText) {
        return this.result({ title: title, message: message, variant: 'danger', okText: okText });
    },
    warning: function (title, message, okText) {
        return this.result({ title: title, message: message, variant: 'warning', okText: okText });
    },
    info: function (title, message, okText) {
        return this.result({ title: title, message: message, variant: 'info', okText: okText });
    }
};

/**
 * Global Confirmation Hook untuk Elemen Interaktif
 * Mendukung klik pada [data-cf-confirm] / [data-confirm] dan submit form
 */
function initConfirmDialogs() {
    // 1. Delegasi Klik pada Elemen Interaktif (Tombol / Link)
    document.addEventListener('click', async function (e) {
        const target = e.target.closest('[data-cf-confirm], [data-confirm]');
        if (!target) return;

        // Cegah eksekusi default
        e.preventDefault();

        const message = target.getAttribute('data-cf-confirm')
                     || target.getAttribute('data-confirm')
                     || 'Apakah Anda yakin ingin melanjutkan tindakan ini?';
        const title   = target.getAttribute('data-cf-title')
                     || target.getAttribute('data-title')
                     || 'Konfirmasi Tindakan';
        const isDanger = target.classList.contains('text-danger')
                      || target.classList.contains('btn-danger')
                      || target.getAttribute('data-cf-variant') === 'danger'
                      || target.getAttribute('data-variant') === 'danger';
        const variant = target.getAttribute('data-cf-variant')
                     || target.getAttribute('data-variant')
                     || (isDanger ? 'danger' : 'primary');
        const confirmText = target.getAttribute('data-cf-confirm-text')
                         || target.getAttribute('data-confirm-text')
                         || (variant === 'danger' ? 'Hapus' : (variant === 'warning' ? 'Setel Ulang' : 'Lanjutkan'));
        const cancelText  = target.getAttribute('data-cf-cancel-text')
                         || target.getAttribute('data-cancel-text')
                         || 'Batal';

        const confirmed = await AppDialog.confirm({
            title: title,
            message: message,
            confirmText: confirmText,
            cancelText: cancelText,
            variant: variant,
            triggerEl: target
        });

        if (confirmed) {
            if (target.tagName === 'A') {
                window.location.href = target.href;
            } else if (target.tagName === 'FORM') {
                HTMLFormElement.prototype.submit.call(target);
            } else if (target.form) {
                HTMLFormElement.prototype.submit.call(target.form);
            } else if (target.tagName === 'BUTTON' && target.type === 'submit') {
                const form = target.closest('form');
                if (form) HTMLFormElement.prototype.submit.call(form);
            } else {
                const form = target.closest('form');
                if (form) HTMLFormElement.prototype.submit.call(form);
            }
        }
    });

    // 2. Delegasi Submit pada Form dengan Atribut Konfirmasi
    document.addEventListener('submit', async function (e) {
        const form = e.target;
        if (!form || (!form.hasAttribute('data-cf-confirm') && !form.hasAttribute('data-confirm'))) {
            return;
        }

        if (form.__cf_confirmed) {
            delete form.__cf_confirmed;
            return;
        }

        e.preventDefault();
        const message = form.getAttribute('data-cf-confirm') || form.getAttribute('data-confirm');
        const title   = form.getAttribute('data-cf-title') || 'Konfirmasi Tindakan';
        const variant = form.getAttribute('data-cf-variant') || 'primary';
        const confirmText = form.getAttribute('data-cf-confirm-text') || 'Lanjutkan';
        const cancelText  = form.getAttribute('data-cf-cancel-text') || 'Batal';

        const confirmed = await AppDialog.confirm({
            title: title,
            message: message,
            confirmText: confirmText,
            cancelText: cancelText,
            variant: variant,
            triggerEl: e.submitter || form
        });

        if (confirmed) {
            form.__cf_confirmed = true;
            HTMLFormElement.prototype.submit.call(form);
        }
    });
}

/**
 * Info Ringan: Toast Tengah-Atas di Bawah Bar Menu (Prompt 32D)
 * Lebih besar, di tengah layar tepat di bawah navbar, auto-dismiss, touch target >= 44px
 */
const AppToast = {
    show: function (type, message) {
        const icons = {
            success: '<i class="bi bi-check-circle-fill text-success-emphasis"></i>',
            error:   '<i class="bi bi-exclamation-triangle-fill text-danger-emphasis"></i>',
            danger:  '<i class="bi bi-exclamation-triangle-fill text-danger-emphasis"></i>',
            warning: '<i class="bi bi-exclamation-circle-fill text-warning-emphasis"></i>',
            info:    '<i class="bi bi-info-circle-fill text-info-emphasis"></i>',
        };

        const safeType = ['success', 'error', 'danger', 'warning', 'info'].includes(type) ? type : 'info';

        let container = document.querySelector('.cf-toast-center-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'cf-toast-center-container';
            container.setAttribute('role', 'status');
            container.setAttribute('aria-live', 'polite');
            container.setAttribute('aria-atomic', 'true');
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `cf-toast-center cf-toast-center-${safeType}`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <span class="cf-toast-icon" aria-hidden="true">${icons[safeType] || icons.info}</span>
            <span class="cf-toast-message">${escapeHtml(message)}</span>
            <button class="cf-toast-close" aria-label="Tutup notifikasi">✕</button>
        `;

        container.appendChild(toast);

        function dismiss() {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-16px) scale(0.95)';
            setTimeout(function () {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }

        const autoTimer = setTimeout(dismiss, 3500);

        const closeBtn = toast.querySelector('.cf-toast-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                clearTimeout(autoTimer);
                dismiss();
            });
        }
    },

    // Shortcut methods untuk info ringan
    copied: function (itemLabel) {
        this.show('info', (itemLabel || 'Teks') + ' telah disalin ke papan klip.');
    },
    saved: function (itemLabel) {
        this.show('success', (itemLabel || 'Data') + ' berhasil disimpan otomatis.');
    },
    info: function (message) {
        this.show('info', message);
    },
    success: function (message) {
        this.show('success', message);
    },
    error: function (message) {
        this.show('error', message);
    },
    warning: function (message) {
        this.show('warning', message);
    }
};

function escapeHtml(str) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(String(str || '')));
    return div.innerHTML;
}

/**
 * Universal Secure & Insecure Context Clipboard Helper (Prompt 35A)
 * Mendukung HTTPS (navigator.clipboard) & HTTP fallback (execCommand / manual selection)
 * Password/rahasia TIDAK PERNAH dicatat ke log peramban!
 * 
 * @param {string} text Teks yang akan disalin
 * @param {HTMLElement|null} btnElement Tombol pemicu untuk feedback UI
 * @param {string} itemLabel Label deskriptif (mis. 'Kata sandi')
 * @returns {Promise<boolean>}
 */
async function copyToClipboard(text, btnElement, itemLabel) {
    if (typeof text !== 'string') {
        text = String(text || '');
    }
    itemLabel = itemLabel || 'Teks';

    let success = false;

    // 1. Coba Navigator Clipboard API (Tersedia pada Secure Context: HTTPS / localhost)
    if (window.isSecureContext && navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
        try {
            await navigator.clipboard.writeText(text);
            success = true;
        } catch (_) {
            // Gagal di clipboard API (mis. izin ditolak), lanjut ke fallback
            success = false;
        }
    }

    // 2. Fallback: Elemen textarea sementara + document.execCommand('copy') (Bekerja di HTTP non-secure context)
    if (!success && document.body) {
        try {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            ta.style.top = '-9999px';
            ta.style.opacity = '0';
            ta.setAttribute('readonly', '');
            document.body.appendChild(ta);

            ta.focus();
            ta.select();
            ta.setSelectionRange(0, text.length);

            success = document.execCommand('copy');
            document.body.removeChild(ta);
        } catch (_) {
            success = false;
        }
    }

    // 3. Fallback Terakhir: Seleksi teks otomatis dengan instruksi "Tekan Ctrl+C"
    if (!success) {
        if (window.AppDialog && typeof window.AppDialog.result === 'function') {
            window.AppDialog.result({
                title: 'Salin Manual',
                message: 'Salin otomatis tidak didukung pada koneksi ini. Silakan tekan Ctrl+C untuk menyalin ' + itemLabel + '.',
                variant: 'warning',
                okText: 'Mengerti'
            });
        } else {
            prompt('Tekan Ctrl+C lalu Enter untuk menyalin ' + itemLabel + ':', text);
        }
    } else {
        // Tampilkan umpan balik "Tersalin" yang jelas pada tombol
        if (btnElement && btnElement instanceof HTMLElement) {
            const originalHtml = btnElement.innerHTML;
            btnElement.style.minWidth = btnElement.offsetWidth + 'px';
            btnElement.innerHTML = '<i class="bi bi-check2"></i> Tersalin';
            btnElement.classList.add('btn-success');
            btnElement.classList.remove('btn-outline-secondary', 'btn-secondary');

            setTimeout(function () {
                btnElement.innerHTML = originalHtml;
                btnElement.style.minWidth = '';
                btnElement.classList.remove('btn-success');
                btnElement.classList.add('btn-outline-secondary');
            }, 2500);
        }

        if (window.AppToast && typeof window.AppToast.copied === 'function') {
            window.AppToast.copied(itemLabel);
        }
    }

    // PENTING: Password/teks rahasia TIDAK BOLEH masuk ke log console / error log!
    return success;
}

// Global Safety Guard: Mengarahkan dialog bawaan peramban yang tersisa ke AppDialog
window.alert = function (msg) {
    console.warn('[CampusFind Guard] Dialog bawaan window.alert() dialihkan ke AppDialog.result');
    AppDialog.result({ title: 'Pemberitahuan Sistem', message: String(msg), variant: 'info' });
};

/**
 * Toggle Password Visibility Helper (TASK 02C Part E)
 */
function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    if (btn) {
        btn.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
        btn.setAttribute('aria-label', isPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
        const icon = btn.querySelector('i');
        if (icon) {
            icon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
        }
    }
}

// Expose Global Helper
window.AppDialog = AppDialog;
window.AppToast  = AppToast;
window.copyToClipboard = copyToClipboard;
window.togglePasswordVisibility = togglePasswordVisibility;
window.CampusFind = {
    showFlash: function (type, message) {
        AppToast.show(type, message);
    },
    dialog: AppDialog,
    toast:  AppToast,
    copyToClipboard: copyToClipboard,
    togglePasswordVisibility: togglePasswordVisibility
};

/**
 * Global AI Match Scan Handler (TASK 11)
 * Non-blocking AJAX execution with scanning animation overlay
 */
document.addEventListener('submit', function (e) {
    const form = e.target;
    if (!form || !form.classList || !form.classList.contains('ai-match-scan-form')) {
        return;
    }

    e.preventDefault();
    const actionUrl = form.action;
    const formData = new FormData(form);
    formData.append('format', 'json');

    const scannerModalEl = document.getElementById('cf-ai-scanner-modal');
    let scannerBsModal = null;
    if (scannerModalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        scannerBsModal = bootstrap.Modal.getOrCreateInstance(scannerModalEl);
        scannerBsModal.show();
    }

    fetch(actionUrl, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
        if (scannerBsModal) scannerBsModal.hide();

        if (data.success) {
            const matchesCount = parseInt(data.matches_count || 0, 10);
            const viewAction = (data.actions && data.actions.view_candidates) ? data.actions.view_candidates : null;
            const targetUrl = viewAction ? viewAction.url : null;
            const isWorkspacePage = window.location.pathname.startsWith('/ai-match/');

            if (matchesCount > 0) {
                if (isWorkspacePage) {
                    window.location.reload();
                } else if (targetUrl && window.AppDialog && typeof window.AppDialog.result === 'function') {
                    window.AppDialog.result({
                        title: 'Kemungkinan Barang Cocok Ditemukan',
                        message: data.message || `Ditemukan ${matchesCount} kemungkinan kecocokan.`,
                        variant: 'success',
                        actionUrl: targetUrl,
                        actionText: 'Bandingkan Barang',
                        cancelText: 'Tutup'
                    });
                } else if (targetUrl) {
                    window.location.href = targetUrl;
                } else {
                    window.location.reload();
                }
            } else {
                if (window.AppDialog && typeof window.AppDialog.result === 'function') {
                    window.AppDialog.result({
                        title: data.result_status === 'completed_fallback' ? 'Kemungkinan Barang Cocok Ditemukan' : 'Hasil Pemindaian AI',
                        message: data.message || 'Analisis selesai.',
                        variant: 'info',
                        okText: 'Mengerti',
                        onOk: function () { if (isWorkspacePage) window.location.reload(); }
                    });
                } else {
                    alert(data.message || 'Analisis selesai.');
                }
            }
        } else {
            if (window.AppDialog && typeof window.AppDialog.result === 'function') {
                window.AppDialog.result({
                    title: 'Pemberitahuan Sistem',
                    message: data.message || 'Gagal memproses pemindaian.',
                    variant: 'warning',
                    okText: 'Mengerti'
                });
            } else {
                alert(data.message || 'Gagal memproses pemindaian.');
            }
        }
    })
    .catch(function (err) {
        if (scannerBsModal) scannerBsModal.hide();
        console.error('[CampusFind AI Match Error]', err);
        if (window.AppDialog && typeof window.AppDialog.result === 'function') {
            window.AppDialog.result({
                title: 'Gangguan Jaringan',
                message: 'Terjadi kendala saat menghubungkan ke server pemindaian. Silakan periksa koneksi Anda.',
                variant: 'danger',
                okText: 'Tutup'
            });
        }
    });
});

/**
 * ──────────────────────────────────────────────
 * Unified Liquid Glass Sidebar & Authenticated Shell Controller (PAGE-00B.1)
 * ──────────────────────────────────────────────
 */
function initCampusFindAppShell() {
    const shellWrapper = document.getElementById('cf-shell-wrapper');
    const sidebar = document.getElementById('cf-sidebar');
    if (!shellWrapper || !sidebar) {
        return; // Guard: Bukan halaman dengan authenticated app_shell
    }

    if (shellWrapper.dataset.shellInitialized === 'true') {
        return; // Cegah duplikasi listener
    }
    shellWrapper.dataset.shellInitialized = 'true';

    // Guard PAGE-00B.3.1: Jika modul sidebar-dock.js aktif, interaksi dock dikelola oleh sidebar-dock.js
    if (document.getElementById('cf-dock-glass') || typeof window.initCampusFindDock === 'function') {
        initUnreadBadgeSync();
        return;
    }

    const toggleBtn = document.getElementById('cf-sidebar-toggle');
    const toggleIcon = document.getElementById('cf-sidebar-toggle-icon');
    const mobileToggle = document.getElementById('cf-mobile-toggle');
    const mobileClose = document.getElementById('cf-sidebar-close');
    const backdrop = document.getElementById('cf-sidebar-backdrop');
    const sidebarNav = document.getElementById('cf-sidebar-nav');
    const slidingBubble = document.getElementById('cf-sidebar-sliding-bubble') || document.getElementById('cf-sidebar-sliding-indicator');
    const searchInput = document.getElementById('cf-sidebar-search-input');
    const specularLight = document.getElementById('cf-sidebar-specular');
    const docEl = document.documentElement;
    const STORAGE_KEY = 'cf_sidebar_collapsed';

    // Helper: Escape HTML string untuk keamanan tooltip
    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // ──────────────────────────────────────────────
    // 1. POINTER-FOLLOWING SPECULAR LIGHT REFLECTION
    // ──────────────────────────────────────────────
    if (sidebar) {
        sidebar.addEventListener('pointerenter', function(e) {
            if (e.pointerType === 'touch') return;
            sidebar.classList.add('is-pointer-inside');
        });

        sidebar.addEventListener('pointerleave', function() {
            sidebar.classList.remove('is-pointer-inside');
        });

        sidebar.addEventListener('pointermove', function(e) {
            if (e.pointerType === 'touch') return;
            const rect = sidebar.getBoundingClientRect();
            const mx = (e.clientX - rect.left).toFixed(1) + 'px';
            const my = (e.clientY - rect.top).toFixed(1) + 'px';
            sidebar.style.setProperty('--mx', mx);
            sidebar.style.setProperty('--my', my);
        });
    }

    // ──────────────────────────────────────────────
    // 2. FLOATING DOCK TOOLTIP (COLLAPSED MODE)
    // ──────────────────────────────────────────────
    let dockTooltip = document.getElementById('cf-dock-tooltip');
    if (!dockTooltip) {
        dockTooltip = document.createElement('div');
        dockTooltip.id = 'cf-dock-tooltip';
        dockTooltip.className = 'cf-dock-tooltip';
        dockTooltip.setAttribute('role', 'tooltip');
        dockTooltip.setAttribute('aria-hidden', 'true');
        document.body.appendChild(dockTooltip);
    }

    let tooltipTimer = null;

    function showDockTooltip(targetEl) {
        if (!dockTooltip) return;
        // Hanya aktif di mode collapsed desktop
        if (!shellWrapper.classList.contains('cf-sidebar-collapsed') || window.innerWidth < 992) {
            hideDockTooltip();
            return;
        }
        clearTimeout(tooltipTimer);
        tooltipTimer = setTimeout(function() {
            const label = targetEl.getAttribute('data-label') || targetEl.getAttribute('title') || '';
            if (!label) {
                hideDockTooltip();
                return;
            }
            const badgeEl = targetEl.querySelector('.cf-sidebar-badge');
            const hasBadge = badgeEl && !badgeEl.classList.contains('d-none') && badgeEl.textContent.trim();
            const badgeCount = hasBadge ? badgeEl.textContent.trim() : null;

            dockTooltip.innerHTML = badgeCount 
                ? `${escapeHtml(label)} <span class="cf-dock-tooltip-badge">${escapeHtml(badgeCount)}</span>`
                : escapeHtml(label);

            const rect = targetEl.getBoundingClientRect();
            const tipRect = dockTooltip.getBoundingClientRect();
            const top = Math.round(rect.top + (rect.height - (tipRect.height || 28)) / 2);
            const left = Math.round(rect.right + 14);

            dockTooltip.style.top = top + 'px';
            dockTooltip.style.left = left + 'px';
            dockTooltip.classList.add('show');
            dockTooltip.setAttribute('aria-hidden', 'false');
        }, 120); // 120ms debounce delay per spesifikasi
    }

    function hideDockTooltip() {
        clearTimeout(tooltipTimer);
        if (dockTooltip) {
            dockTooltip.classList.remove('show');
            dockTooltip.setAttribute('aria-hidden', 'true');
        }
    }

    // Inisialisasi event listener tooltip pada item sidebar
    const tooltipTargets = sidebar.querySelectorAll('.cf-sidebar-link, .cf-sidebar-user-link, .cf-sidebar-logout-btn');
    tooltipTargets.forEach(function(target) {
        target.addEventListener('mouseenter', function() { showDockTooltip(target); });
        target.addEventListener('mouseleave', hideDockTooltip);
        target.addEventListener('click', hideDockTooltip);
    });

    // ──────────────────────────────────────────────
    // 3. SINGLE SLIDING GLASS BUBBLE SELECTION
    // ──────────────────────────────────────────────
    function updateSlidingBubble(targetLink) {
        if (!slidingBubble || !sidebarNav) return;

        // Jika null, kembali ke menu yang saat ini active dari SSR
        if (!targetLink) {
            const activeLink = sidebarNav.querySelector('.cf-sidebar-link.active');
            if (activeLink && activeLink.offsetParent !== null) {
                updateSlidingBubble(activeLink);
            } else {
                slidingBubble.classList.remove('active');
            }
            return;
        }

        const navRect = sidebarNav.getBoundingClientRect();
        const linkRect = targetLink.getBoundingClientRect();

        const top = (linkRect.top - navRect.top) + sidebarNav.scrollTop;
        const left = (linkRect.left - navRect.left);
        const width = linkRect.width;
        const height = linkRect.height;

        slidingBubble.style.top = top + 'px';
        slidingBubble.style.left = left + 'px';
        slidingBubble.style.width = width + 'px';
        slidingBubble.style.height = height + 'px';
        slidingBubble.classList.add('active');
    }

    const updateSlidingIndicator = updateSlidingBubble; // Alias untuk kompatibilitas backward

    // Pasang listener sliding indicator pada setiap link
    const navLinks = sidebarNav ? sidebarNav.querySelectorAll('.cf-sidebar-link') : [];
    navLinks.forEach(function(link) {
        link.addEventListener('mouseenter', function() {
            updateSlidingBubble(link);
        });
        link.addEventListener('focus', function() {
            updateSlidingBubble(link);
        });
    });

    if (sidebarNav) {
        sidebarNav.addEventListener('mouseleave', function() {
            updateSlidingBubble(null);
        });
    }

    // ──────────────────────────────────────────────
    // 4. macOS VERTICAL DOCK MAGNIFICATION (GAUSSIAN + SPRING PHYSICS + SPATIAL REACTION)
    // ──────────────────────────────────────────────
    let springRaf = null;
    let isPointerInsideNav = false;
    let pointerClientY = 0;
    let lastTime = 0;

    // Kumpulan state per wrapper ikon untuk fisika spring independen
    const iconItems = [];
    if (sidebarNav) {
        const iconWrappers = sidebarNav.querySelectorAll('.cf-sidebar-link .cf-sidebar-icon-wrapper');
        iconWrappers.forEach(function(wrapper) {
            iconItems.push({
                el: wrapper,
                currentScale: 1.0,
                targetScale: 1.0,
                scaleVelocity: 0,
                currentTranslateY: 0,
                targetTranslateY: 0,
                translateYVelocity: 0
            });
        });
    }

    function calculateMagnificationTargets(clientY) {
        const sigma = 60; // Gaussian standard deviation 60px
        const maxScaleBoost = 0.6; // max scale 1.0 + 0.6 = 1.6x
        const sigmaSpatial = 85; // Spatial neighbor displacement falloff

        iconItems.forEach(function(item) {
            const rect = item.el.getBoundingClientRect();
            const centerY = rect.top + rect.height / 2;
            const dy = centerY - clientY;
            const dist = Math.abs(dy);

            // Exact Gaussian Magnification formula:
            // targetScale = 1.0 + 0.6 * exp(-d^2 / (2 * sigma^2))
            const scale = 1.0 + maxScaleBoost * Math.exp(-(dist * dist) / (2 * sigma * sigma));
            item.targetScale = Math.min(1.6, Math.max(1.0, scale));

            // Spatial Vertical Reaction (neighbor icons shift apart to prevent overlap):
            // targetTranslateY = sign(centerY - pointerY) * 14 * exp(-d^2 / (2 * sigmaSpatial^2))
            if (dist < 4) {
                item.targetTranslateY = 0;
            } else {
                const pushDirection = Math.sign(dy);
                item.targetTranslateY = pushDirection * 14 * Math.exp(-(dist * dist) / (2 * sigmaSpatial * sigmaSpatial));
            }
        });
    }

    function resetMagnificationTargets() {
        iconItems.forEach(function(item) {
            item.targetScale = 1.0;
            item.targetTranslateY = 0;
        });
    }

    function springPhysicsLoop(currentTime) {
        if (!lastTime) lastTime = currentTime;
        const dt = Math.min(0.032, (currentTime - lastTime) / 1000) || 0.016;
        lastTime = currentTime;

        const stiffness = 300; // Spring stiffness k
        const damping = 20;    // Damping coefficient c
        let isMoving = false;

        iconItems.forEach(function(item) {
            // 1. Spring physics untuk scale
            const scaleForce = stiffness * (item.targetScale - item.currentScale) - damping * item.scaleVelocity;
            item.scaleVelocity += scaleForce * dt;
            item.currentScale += item.scaleVelocity * dt;

            // 2. Spring physics untuk translateY (spatial neighbor reaction)
            const yForce = stiffness * (item.targetTranslateY - item.currentTranslateY) - damping * item.translateYVelocity;
            item.translateYVelocity += yForce * dt;
            item.currentTranslateY += item.translateYVelocity * dt;

            // Threshold cek apakah masih berosilasi
            const isScaleResting = Math.abs(item.targetScale - item.currentScale) < 0.002 && Math.abs(item.scaleVelocity) < 0.005;
            const isYResting = Math.abs(item.targetTranslateY - item.currentTranslateY) < 0.05 && Math.abs(item.translateYVelocity) < 0.08;

            if (!isScaleResting || !isYResting) {
                isMoving = true;
                item.el.style.transform = `translateY(${item.currentTranslateY.toFixed(2)}px) scale(${item.currentScale.toFixed(3)})`;
            } else if (!isPointerInsideNav && item.targetScale === 1.0 && item.targetTranslateY === 0) {
                // Bersihkan transform saat istirahat penuh pada baseline
                item.currentScale = 1.0;
                item.scaleVelocity = 0;
                item.currentTranslateY = 0;
                item.translateYVelocity = 0;
                item.el.style.transform = '';
            }
        });

        // Terus jalankan loop jika pointer masih berada di dalam atau spring belum mencapai posisi istirahat
        if (isPointerInsideNav || isMoving) {
            springRaf = requestAnimationFrame(springPhysicsLoop);
        } else {
            springRaf = null;
            lastTime = 0;
        }
    }

    function startSpringLoop() {
        if (!springRaf) {
            lastTime = 0;
            springRaf = requestAnimationFrame(springPhysicsLoop);
        }
    }

    if (sidebarNav) {
        sidebarNav.addEventListener('pointerenter', function(e) {
            if (e.pointerType === 'touch') return;
            if (!shellWrapper.classList.contains('cf-sidebar-collapsed') || window.innerWidth < 992) return;
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

            isPointerInsideNav = true;
            pointerClientY = e.clientY;
            calculateMagnificationTargets(pointerClientY);
            startSpringLoop();
        });

        sidebarNav.addEventListener('pointermove', function(e) {
            if (e.pointerType === 'touch') return;
            if (!shellWrapper.classList.contains('cf-sidebar-collapsed') || window.innerWidth < 992) return;
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

            isPointerInsideNav = true;
            pointerClientY = e.clientY;
            calculateMagnificationTargets(pointerClientY);
            startSpringLoop();
        });

        sidebarNav.addEventListener('pointerleave', function() {
            isPointerInsideNav = false;
            resetMagnificationTargets();
            startSpringLoop(); // biarkan pegas kembali ke 1.0 secara mulus
        });
    }

    function resetDockMagnification() {
        isPointerInsideNav = false;
        resetMagnificationTargets();
        iconItems.forEach(function(item) {
            item.currentScale = 1.0;
            item.scaleVelocity = 0;
            item.currentTranslateY = 0;
            item.translateYVelocity = 0;
            item.el.style.transform = '';
        });
        if (springRaf) {
            cancelAnimationFrame(springRaf);
            springRaf = null;
            lastTime = 0;
        }
    }

    // ──────────────────────────────────────────────
    // 5. QUICK SEARCH FILTER (EXPANDED PANEL)
    // ──────────────────────────────────────────────
    if (searchInput && sidebarNav) {
        searchInput.addEventListener('input', function() {
            const query = (this.value || '').trim().toLowerCase();
            const groups = sidebarNav.querySelectorAll('.cf-sidebar-group');

            groups.forEach(function(group) {
                const links = group.querySelectorAll('.cf-sidebar-link');
                let hasVisible = false;

                links.forEach(function(link) {
                    const labelEl = link.querySelector('.cf-sidebar-label');
                    const label = (labelEl ? labelEl.textContent : '').toLowerCase();

                    if (!query || label.includes(query)) {
                        link.style.display = '';
                        hasVisible = true;
                    } else {
                        link.style.display = 'none';
                    }
                });

                const heading = group.querySelector('.cf-sidebar-heading');
                if (heading) {
                    heading.style.display = hasVisible ? '' : 'none';
                }
            });

            updateSlidingBubble(null);
        });
    }

    // Shortcut Keyboard Desktop:
    // Ctrl+K: Fokus Pencarian Menu (otomatis expand jika collapsed)
    // Ctrl+B: Toggle Collapse/Expand
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
            if (window.innerWidth >= 992 && searchInput) {
                e.preventDefault();
                if (shellWrapper.classList.contains('cf-sidebar-collapsed')) {
                    applyDesktopCollapsedState(false, true);
                }
                setTimeout(function() {
                    searchInput.focus();
                    searchInput.select();
                }, 200);
            }
        }
    });

    // ──────────────────────────────────────────────
    // D. SINKRONISASI STATUS COLLAPSED DESKTOP
    // ──────────────────────────────────────────────
    function applyDesktopCollapsedState(isCollapsed, persist) {
        if (window.innerWidth < 992) {
            return;
        }

        hideDockTooltip();
        resetDockMagnification();

        if (isCollapsed) {
            shellWrapper.classList.add('cf-sidebar-collapsed');
            docEl.classList.add('cf-sidebar-collapsed');
            if (toggleBtn) {
                toggleBtn.setAttribute('aria-expanded', 'false');
                toggleBtn.setAttribute('aria-label', 'Perbesar Navigasi');
                toggleBtn.setAttribute('title', 'Perbesar Navigasi (Ctrl+B)');
            }
            if (toggleIcon) {
                toggleIcon.className = 'bi bi-chevron-right';
            }
        } else {
            shellWrapper.classList.remove('cf-sidebar-collapsed');
            docEl.classList.remove('cf-sidebar-collapsed');
            if (toggleBtn) {
                toggleBtn.setAttribute('aria-expanded', 'true');
                toggleBtn.setAttribute('aria-label', 'Perkecil Navigasi');
                toggleBtn.setAttribute('title', 'Perkecil Navigasi (Ctrl+B)');
            }
            if (toggleIcon) {
                toggleIcon.className = 'bi bi-chevron-left';
            }
        }

        if (persist) {
            try {
                localStorage.setItem(STORAGE_KEY, isCollapsed ? 'true' : 'false');
            } catch (e) {}
        }

        // Sinkronisasi posisi sliding indicator setelah transisi lebar selesai
        setTimeout(function() {
            updateSlidingIndicator(null);
        }, 380);
    }

    // Inisialisasi dari localStorage jika desktop
    try {
        const savedCollapsed = localStorage.getItem(STORAGE_KEY) === 'true';
        applyDesktopCollapsedState(savedCollapsed, false);
    } catch (e) {}

    // Inisialisasi sliding indicator pertama kali saat halaman selesai dimuat
    setTimeout(function() {
        updateSlidingIndicator(null);
    }, 120);

    // 2. Toggle Desktop Collapse
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            hideDockTooltip();
            const currentlyCollapsed = shellWrapper.classList.contains('cf-sidebar-collapsed');
            applyDesktopCollapsedState(!currentlyCollapsed, true);
        });
    }

    // Shortcut Keyboard Desktop Ctrl+B untuk toggle collapse
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && (e.key === 'b' || e.key === 'B')) {
            if (window.innerWidth >= 992 && toggleBtn) {
                e.preventDefault();
                toggleBtn.click();
            }
        }
    });

    // Handle Window Resize: selaraskan sliding indicator & dock tooltip
    window.addEventListener('resize', function() {
        hideDockTooltip();
        resetDockMagnification();
        updateSlidingIndicator(null);
    });

    // 3. Mobile Off-Canvas Drawer Open/Close
    let lastActiveElement = null;

    function openMobileDrawer() {
        lastActiveElement = document.activeElement;
        shellWrapper.classList.add('cf-mobile-open');
        document.body.style.overflow = 'hidden';
        if (mobileToggle) {
            mobileToggle.setAttribute('aria-expanded', 'true');
        }
        if (mobileClose) {
            mobileClose.focus();
        }
    }

    function closeMobileDrawer() {
        shellWrapper.classList.remove('cf-mobile-open');
        document.body.style.overflow = '';
        if (mobileToggle) {
            mobileToggle.setAttribute('aria-expanded', 'false');
        }
        if (lastActiveElement && typeof lastActiveElement.focus === 'function') {
            lastActiveElement.focus();
        }
    }

    if (mobileToggle) {
        mobileToggle.addEventListener('click', function(e) {
            e.preventDefault();
            if (shellWrapper.classList.contains('cf-mobile-open')) {
                closeMobileDrawer();
            } else {
                openMobileDrawer();
            }
        });
    }

    if (mobileClose) {
        mobileClose.addEventListener('click', function(e) {
            e.preventDefault();
            closeMobileDrawer();
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', function() {
            closeMobileDrawer();
        });
    }

    // Escape Key Handler untuk Mobile Drawer
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' || e.key === 'Esc') {
            if (shellWrapper.classList.contains('cf-mobile-open')) {
                e.preventDefault();
                closeMobileDrawer();
            }
        }
    });

    // 4. Sinkronisasi & Stabilisasi Badge Notifikasi & Pesan (PAGE-00B.2.1)
    initUnreadBadgeSync();
}

/**
 * Sinkronisasi & Stabilisasi Badge Notifikasi & Pesan (PAGE-00B.2.1)
 */
function initUnreadBadgeSync() {
    let isSyncingBadges = false;
    let notifReqSeq = 0;
    let chatReqSeq = 0;
    let latestResolvedNotifSeq = 0;
    let latestResolvedChatSeq = 0;

    function safeUpdateBadge(element, count) {
        if (!element) return;
        const formatted = count > 99 ? '99+' : String(count);
        const badgeType = element.getAttribute('data-badge');
        const dotEl = badgeType ? document.getElementById('cf-sidebar-dot-' + badgeType) : null;

        if (count > 0) {
            if (element.textContent !== formatted) {
                element.textContent = formatted;
            }
            element.classList.remove('d-none');
            if (dotEl) {
                dotEl.classList.remove('d-none');
            }
        } else {
            element.classList.add('d-none');
            element.textContent = '';
            if (dotEl) {
                dotEl.classList.add('d-none');
            }
        }
    }

    function safeUpdateIndicator(indicatorEl, count) {
        if (!indicatorEl) return;
        if (count > 0) {
            indicatorEl.classList.remove('d-none');
        } else {
            indicatorEl.classList.add('d-none');
        }
    }

    // A. Segera hydrate dari sessionStorage untuk menghilangkan flicker antar-navigasi
    try {
        const cachedNotif = sessionStorage.getItem('cf_badge_notif');
        if (cachedNotif !== null) {
            const count = parseInt(cachedNotif, 10);
            if (!isNaN(count)) {
                safeUpdateBadge(document.getElementById('cf-sidebar-badge-notif'), count);
                safeUpdateIndicator(document.getElementById('cf-topbar-notif-badge'), count);
            }
        }
        const cachedChats = sessionStorage.getItem('cf_badge_chats');
        if (cachedChats !== null) {
            const count = parseInt(cachedChats, 10);
            if (!isNaN(count)) {
                safeUpdateBadge(document.getElementById('cf-sidebar-badge-chats'), count);
            }
        }
    } catch (e) {}

    // B. Sinkronisasi asynchronous terkoordinasi (single-flight, anti-race, non-destructive)
    function syncUnreadBadges() {
        if (isSyncingBadges) return;
        isSyncingBadges = true;

        const currentNotifSeq = ++notifReqSeq;
        const currentChatSeq = ++chatReqSeq;

        const notifPromise = fetch('/notifications/unread-count', { credentials: 'same-origin' })
            .then(function(res) { return res.ok ? res.json() : null; })
            .then(function(data) {
                if (!data || typeof data.unread_count === 'undefined') return;
                if (currentNotifSeq < latestResolvedNotifSeq) return; // Drop response basi
                latestResolvedNotifSeq = currentNotifSeq;

                const count = Math.max(0, parseInt(data.unread_count, 10) || 0);
                safeUpdateBadge(document.getElementById('cf-sidebar-badge-notif'), count);
                safeUpdateIndicator(document.getElementById('cf-topbar-notif-badge'), count);
                try {
                    sessionStorage.setItem('cf_badge_notif', String(count));
                } catch (e) {}
            })
            .catch(function() {
                // Defensive: pada kegagalan jaringan atau timeout, PERTAHANKAN nilai terakhir yang valid.
            });

        const chatPromise = fetch('/chats/unread-count', { credentials: 'same-origin' })
            .then(function(res) { return res.ok ? res.json() : null; })
            .then(function(data) {
                if (!data || typeof data.unread_count === 'undefined') return;
                if (currentChatSeq < latestResolvedChatSeq) return; // Drop response basi
                latestResolvedChatSeq = currentChatSeq;

                const count = Math.max(0, parseInt(data.unread_count, 10) || 0);
                safeUpdateBadge(document.getElementById('cf-sidebar-badge-chats'), count);
                try {
                    sessionStorage.setItem('cf_badge_chats', String(count));
                } catch (e) {}
            })
            .catch(function() {
                // Defensive: pertahankan nilai terakhir yang valid
            });

        Promise.allSettled([notifPromise, chatPromise]).finally(function() {
            isSyncingBadges = false;
        });
    }

    // Polling cepat awal (50ms) dan interval latar belakang 30 detik (pencegahan duplikasi)
    setTimeout(syncUnreadBadges, 50);
    if (!window._cfBadgeInterval) {
        window._cfBadgeInterval = setInterval(syncUnreadBadges, 30000);
    }
}

/**
 * Additive Floating Submenu Flyout Controller for Civitas (PAGE-01.2C.2)
 */
function initCivitasDockFlyout() {
    const flyout = document.getElementById('cf-dock-flyout');
    const trigger = document.getElementById('cf-dock-flyout-trigger');
    const closeBtn = document.getElementById('cf-dock-flyout-close');
    if (!flyout) return;

    let closeTimer = null;

    function openFlyout() {
        if (closeTimer) clearTimeout(closeTimer);
        flyout.removeAttribute('hidden');
        requestAnimationFrame(function() {
            flyout.classList.add('is-open');
        });
        if (trigger) {
            trigger.setAttribute('aria-expanded', 'true');
        }
    }

    function closeFlyout() {
        flyout.classList.remove('is-open');
        if (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
        }
        setTimeout(function() {
            if (!flyout.classList.contains('is-open')) {
                flyout.setAttribute('hidden', '');
            }
        }, 220);
    }

    if (trigger) {
        trigger.addEventListener('click', function(e) {
            e.stopPropagation();
            if (flyout.classList.contains('is-open')) {
                closeFlyout();
            } else {
                openFlyout();
            }
        });

        trigger.addEventListener('mouseenter', function() {
            if (window.innerWidth >= 992) {
                openFlyout();
            }
        });

        trigger.addEventListener('mouseleave', function() {
            if (window.innerWidth >= 992) {
                closeTimer = setTimeout(closeFlyout, 300);
            }
        });
    }

    flyout.addEventListener('mouseenter', function() {
        if (closeTimer) clearTimeout(closeTimer);
    });

    flyout.addEventListener('mouseleave', function() {
        if (window.innerWidth >= 992) {
            closeTimer = setTimeout(closeFlyout, 250);
        }
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            closeFlyout();
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && flyout.classList.contains('is-open')) {
            closeFlyout();
        }
    });

    document.addEventListener('click', function(e) {
        if (flyout.classList.contains('is-open')) {
            if (!flyout.contains(e.target) && (!trigger || !trigger.contains(e.target))) {
                closeFlyout();
            }
        }
    });
}

/**
 * Civitas Profile Segmented Tabs Controller (PAGE-01.2C.2)
 */
function initCivitasProfileTabs() {
    const tabButtons = document.querySelectorAll('.cf-segmented-tab-btn');
    const tabPanes = document.querySelectorAll('.cf-tab-pane');
    if (!tabButtons.length || !tabPanes.length) return;

    function switchTab(targetTabId) {
        tabButtons.forEach(function(btn) {
            const isActive = btn.getAttribute('data-tab') === targetTabId;
            btn.classList.toggle('active', isActive);
            btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        tabPanes.forEach(function(pane) {
            const isActive = pane.id === targetTabId;
            pane.classList.toggle('active', isActive);
            if (isActive) {
                // Ensure all elements inside active tab are revealed immediately
                pane.querySelectorAll('.cf-motion-card, .cf-card').forEach(function(el) {
                    el.classList.add('cf-motion-revealed');
                });
                pane.querySelectorAll('.cf-motion-text').forEach(function(el) {
                    el.classList.add('cf-text-revealed');
                });
            }
        });

        try {
            history.replaceState(null, '', '#' + targetTabId);
        } catch (e) {}
    }

    tabButtons.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const tabId = this.getAttribute('data-tab');
            if (tabId) switchTab(tabId);
        });
    });

    const initialHash = window.location.hash.replace('#', '');
    if (initialHash && document.getElementById(initialHash)) {
        switchTab(initialHash);
    }
}

/**
 * Universal Aura Motion & Page Experience Engine for Civitas (PAGE-01.2D)
 */
function initCivitasUniversalMotion() {
    if (!document.body.classList.contains('cf-role-civitas')) return;

    const isReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // 1. Mark progressive enhancement ready on body
    document.body.classList.add('cf-motion-ready');

    // 2. Identify and setup Universal Text Reveal
    const textSelectors = [
        '.cf-page-header-eyebrow',
        '.cf-page-header-title',
        '.cf-page-header-desc',
        '.cf-page-header-badge',
        '.cf-page-header-actions',
        '.cf-hero-eyebrow',
        '.cf-hero-title',
        '.cf-hero-desc',
        '.cf-hero-actions',
        '.cf-analytics-title',
        '.cf-glass-calendar-title',
        '.cf-section-title',
        '.cf-card-header h2',
        '.cf-card-header h3',
        '.cf-card-header h4',
        '.cf-card-header h5',
        '.cf-card-header h6',
        '.cf-card h2',
        '.cf-card h3',
        '.cf-card h5'
    ];
    const textElements = document.querySelectorAll(textSelectors.join(', '));
    textElements.forEach(function(el) {
        if (!el.classList.contains('cf-motion-text')) {
            el.classList.add('cf-motion-text');
        }
    });

    if (isReducedMotion) {
        textElements.forEach(function(el) { el.classList.add('cf-text-revealed'); });
    } else {
        let textDelay = 40;
        textElements.forEach(function(el) {
            const rect = el.getBoundingClientRect();
            if (rect.top < window.innerHeight && rect.bottom > 0) {
                setTimeout(function() {
                    el.classList.add('cf-text-revealed');
                }, textDelay);
                textDelay += 75;
            } else {
                if ('IntersectionObserver' in window) {
                    const textObs = new IntersectionObserver(function(entries, obs) {
                        entries.forEach(function(entry) {
                            if (entry.isIntersecting) {
                                entry.target.classList.add('cf-text-revealed');
                                obs.unobserve(entry.target);
                            }
                        });
                    }, { threshold: 0.1 });
                    textObs.observe(el);
                } else {
                    el.classList.add('cf-text-revealed');
                }
            }
        });
    }

    // 3. Identify and setup Universal Card Reveal
    const cardSelectors = [
        '.cf-card',
        '.cf-kpi-card',
        '.cf-hero-banner',
        '.cf-listing-card',
        '.cf-form-card',
        '.cf-form-section',
        '.cf-profile-identity-capsule',
        '.cf-segmented-tabs',
        '.cf-chat-split-container',
        '.cf-filter-card',
        '.cf-table-wrapper',
        '.cf-empty-state-card'
    ];
    
    const cardElements = [];
    document.querySelectorAll(cardSelectors.join(', ')).forEach(function(el) {
        const parentCard = el.parentElement ? el.parentElement.closest(cardSelectors.join(', ')) : null;
        if (!parentCard || el.classList.contains('cf-form-section') || el.classList.contains('cf-kpi-card')) {
            cardElements.push(el);
            if (!el.classList.contains('cf-motion-card')) {
                el.classList.add('cf-motion-card');
            }
        }
    });

    if (isReducedMotion || !('IntersectionObserver' in window)) {
        cardElements.forEach(function(el) { el.classList.add('cf-motion-revealed'); });
    } else {
        let visibleCardCount = 0;
        const cardObserver = new IntersectionObserver(function(entries, obs) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    const target = entry.target;
                    obs.unobserve(target);
                    const delay = Math.min(visibleCardCount * 90, 450);
                    visibleCardCount++;
                    setTimeout(function() {
                        target.classList.add('cf-motion-revealed');
                    }, delay);
                }
            });
        }, { threshold: 0.08 });

        cardElements.forEach(function(el) {
            cardObserver.observe(el);
        });
    }

    // 4. Universal Genuine KPI Digit Scramble
    initUniversalDigitScramble(isReducedMotion);

    // 5. SVG Chart Motion
    initSvgChartsMotion(isReducedMotion);
}

/**
 * Genuine KPI Digit Scramble Helper (PAGE-01.2D)
 */
function initUniversalDigitScramble(isReducedMotion) {
    const scrambleElements = document.querySelectorAll('[data-count-target], .cf-kpi-scramble');
    scrambleElements.forEach(function(el) {
        if (el.dataset.scrambleInitialized === 'true') return;
        el.dataset.scrambleInitialized = 'true';

        const rawTarget = el.getAttribute('data-count-target') || el.textContent.trim().replace(/[^\d]/g, '');
        const targetValue = parseInt(rawTarget, 10);
        if (isNaN(targetValue)) return;

        const formattedActual = targetValue.toLocaleString('id-ID');
        el.setAttribute('aria-label', formattedActual);

        if (isReducedMotion) {
            el.textContent = formattedActual;
            return;
        }

        const duration = 520;
        const startTime = performance.now();
        const chars = '0123456789';
        const targetStr = String(targetValue);
        const scrambleLen = targetValue === 0 ? 1 : Math.max(targetStr.length, 2);

        let lastFrameTime = 0;
        const frameRate = 35;

        function scrambleTick(now) {
            const elapsed = now - startTime;
            const progress = Math.min(elapsed / duration, 1);

            if (now - lastFrameTime >= frameRate) {
                lastFrameTime = now;
                if (progress >= 0.85) {
                    el.textContent = formattedActual;
                } else {
                    let rand = '';
                    for (let i = 0; i < scrambleLen; i++) {
                        rand += chars[Math.floor(Math.random() * chars.length)];
                    }
                    el.textContent = rand;
                }
            }

            if (progress < 1) {
                requestAnimationFrame(scrambleTick);
            } else {
                el.textContent = formattedActual;
            }
        }

        if ('IntersectionObserver' in window) {
            const kpiObs = new IntersectionObserver(function(entries, obs) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        obs.unobserve(entry.target);
                        requestAnimationFrame(scrambleTick);
                    }
                });
            }, { threshold: 0.1 });
            kpiObs.observe(el);
        } else {
            requestAnimationFrame(scrambleTick);
        }
    });
}

/**
 * SVG Charts Motion Helper (PAGE-01.2D)
 */
function initSvgChartsMotion(isReducedMotion) {
    const animatedPaths = document.querySelectorAll('.cf-chart-path-animated, svg path.cf-trend-line');
    animatedPaths.forEach(function(path) {
        if (isReducedMotion) return;
        try {
            const length = path.getTotalLength();
            if (length > 0) {
                path.style.strokeDasharray = `${length} ${length}`;
                path.style.strokeDashoffset = `${length}`;
                setTimeout(function() {
                    path.style.transition = 'stroke-dashoffset 1.2s cubic-bezier(0.16, 1, 0.3, 1)';
                    path.style.strokeDashoffset = '0';
                }, 200);
            }
        } catch (e) {}
    });

    const axisItems = document.querySelectorAll('.cf-chart-axis-item');
    axisItems.forEach(function(item, idx) {
        if (isReducedMotion) {
            item.classList.add('is-drawn');
        } else {
            setTimeout(function() {
                item.classList.add('is-drawn');
            }, 300 + idx * 50);
        }
    });
}

/**
 * Global Lucide Icons Initializer (stroke-width: 1.65px)
 */
function initLucideIcons() {
    if (typeof window.lucide !== 'undefined' && typeof window.lucide.createIcons === 'function') {
        try {
            window.lucide.createIcons({
                attrs: {
                    'stroke-width': '1.65'
                }
            });
        } catch (e) {
            console.warn('Lucide icon initialization note:', e);
        }
    }
}
window.initLucideIcons = initLucideIcons;

// Inisialisasi saat DOM siap
function initCivitasEnhancements() {
    initCampusFindAppShell();
    initCivitasDockFlyout();
    initCivitasProfileTabs();
    initCivitasUniversalMotion();
    initLucideIcons();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCivitasEnhancements);
} else {
    initCivitasEnhancements();
}



