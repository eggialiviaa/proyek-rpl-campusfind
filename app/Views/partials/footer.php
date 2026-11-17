<?php
/**
 * app/Views/partials/footer.php
 * Footer Campus Find
 *
 * Landing page ($isLandingPage = true) uses the redesigned Midnight Navy editorial footer.
 * Internal dashboards, search, reports, and login preserve the existing internal footer unchanged.
 */
$isLandingFooter = !empty($isLandingPage);
if ($isLandingFooter && !empty($GLOBALS['cfLandingFooterRendered'])) {
    return;
}
if ($isLandingFooter) {
    $GLOBALS['cfLandingFooterRendered'] = true;
}
$footerAuthUser = \App\Middleware\AuthMiddleware::user();
$footerUserRole = $footerAuthUser ? strtolower(trim((string)($footerAuthUser['role_name'] ?? $footerAuthUser['role'] ?? ''))) : null;
$footerIsLoggedIn = \App\Middleware\AuthMiddleware::isLoggedIn();

// Role-aware home destination mapping (FLOW-01.1 Phase A)
$footerHomeHref = match ($footerUserRole) {
    'admin'              => '/admin',
    'petugas', 'civitas' => '/dashboard',
    default              => '/',
};
$isFooterLoggedIn = $footerIsLoggedIn;
?>
<?php if ($isLandingFooter): ?>
<footer class="cf-landing-footer" id="cf-landing-footer" role="contentinfo">
    <div class="container cf-landing-footer-container">
        <div class="cf-landing-footer-top">
            <div class="cf-landing-footer-brand-block">
                <a href="/" class="cf-landing-footer-wordmark">CAMPUSFIND.</a>
                <p class="cf-landing-footer-tagline">Lost things. Found connections.</p>
            </div>

            <nav class="cf-landing-footer-nav" aria-label="Footer navigation">
                <a href="/#recently-reported" class="cf-landing-footer-link">Recently Reported</a>
                <a href="/#how-it-works" class="cf-landing-footer-link">How It Works</a>
                <a href="<?= $isFooterLoggedIn ? '/search' : '/login' ?>" class="cf-landing-footer-link">
                    <?= $isFooterLoggedIn ? 'Browse Catalog' : 'Sign In' ?>
                </a>
            </nav>
        </div>

        <div class="cf-landing-footer-bottom">
            <p class="cf-landing-footer-copy">
                &copy; <?= date('Y') ?> CampusFind. Digital Campus Lost &amp; Found System.
            </p>
        </div>
    </div>
</footer>
<?php else: ?>
<footer class="cf-footer" role="contentinfo">
    <div class="container">
        <div class="row align-items-center gy-3">
            <div class="col-md-4">
                <div class="cf-footer-brand mb-1">🔍 CampusFind</div>
                <p class="small mb-0 opacity-75">
                    Sistem Lost &amp; Found Digital Kampus
                </p>
            </div>
            <div class="col-md-4 text-md-center">
                <nav aria-label="Footer navigation">
                    <?php if ($footerIsLoggedIn): ?>
                        <a href="<?= htmlspecialchars($footerHomeHref, ENT_QUOTES, 'UTF-8') ?>" class="small">Beranda</a>
                    <?php else: ?>
                        <a href="/" class="small me-3">Beranda</a>
                        <a href="/login" class="small">Masuk</a>
                    <?php endif; ?>
                </nav>
            </div>
            <div class="col-md-4 text-md-end">
                <p class="small mb-0 opacity-60">
                    &copy; <?= date('Y') ?> Campus Find.
                    Dikembangkan untuk lingkungan akademik.
                </p>
            </div>
        </div>
    </div>
</footer>
<?php endif; ?>
