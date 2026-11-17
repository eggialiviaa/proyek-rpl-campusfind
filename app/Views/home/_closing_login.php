<?php
/**
 * app/Views/home/_closing_login.php
 * Phase 4C: Scene 07 (Emotional Closing + Integrated Footer) & Scene 08 (Full-Screen Split Liquid Glass Login)
 *
 * Uses sanitized variables prepared by HomeController::index() and AuthMiddleware:
 * - $isLoggedInUser (bool)
 * - $availableHeroObjects (array)
 * - $csrfToken (string)
 * - $lockInfo (array)
 */

$closingPart = $closingPart ?? 'all';
$isLoggedIn = !empty($isLoggedInUser);
$closingHeroObjects = $availableHeroObjects ?? [];
$authUserData = $isLoggedIn ? \App\Middleware\AuthMiddleware::user() : null;
$authRoleStr = $isLoggedIn ? strtolower(trim((string) (\App\Middleware\AuthMiddleware::role() ?? 'civitas'))) : 'tamu';
$dashboardHref = match ($authRoleStr) {
    'admin' => '/admin/dashboard',
    'petugas' => '/petugas/dashboard',
    default => '/dashboard',
};
$authDisplayName = trim((string) ($authUserData['name'] ?? ($authUserData['nama'] ?? 'Campus Member')));
$authRoleLabel = match ($authRoleStr) {
    'admin' => 'Administrator',
    'petugas' => 'Lost & Found Officer',
    default => 'Civitas Akademika',
};
?>

<?php if ($closingPart === 'all' || $closingPart === 'closing'): ?>
<!-- Scene 07: Emotional Closing + Integrated Footer -->
<section class="cf-closing" id="cf-closing" aria-labelledby="cf-closing-heading">
    <div class="cf-closing-upper">
        <div class="container cf-closing-container">
            <div class="cf-closing-grid">
                <div class="cf-closing-copy" data-closing-actor="title">
                    <span class="cf-closing-eyebrow">
                        <span class="cf-closing-dot" aria-hidden="true"></span>
                        <span>CampusFind Promise</span>
                    </span>

                    <h2 id="cf-closing-heading" class="cf-closing-title">
                        Every lost item deserves a way home.
                    </h2>

                    <p class="cf-closing-desc">
                        Whether a misplaced wallet, lecture notebook, or campus keys, verified stewardship brings everyday essentials safely back to their owners.
                    </p>

                    <div class="cf-closing-actions" data-closing-actor="cta">
                        <?php if (!$isLoggedIn): ?>
                            <a href="/login"
                               class="cf-hero-btn cf-hero-btn--primary cf-closing-login-cue"
                               id="cf-closing-signin-btn">
                                <span>Sign In to CampusFind</span>
                                <span class="cf-hero-btn-arrow" aria-hidden="true">&rarr;</span>
                            </a>
                        <?php else: ?>
                            <a href="<?= htmlspecialchars($dashboardHref, ENT_QUOTES, 'UTF-8') ?>"
                               class="cf-hero-btn cf-hero-btn--primary cf-closing-login-cue"
                               id="cf-closing-signin-btn">
                                <span>Open Dashboard</span>
                                <span class="cf-hero-btn-arrow" aria-hidden="true">&rarr;</span>
                            </a>
                        <?php endif; ?>

                        <a href="#hero"
                           class="cf-hero-btn cf-hero-btn--secondary"
                           id="cf-closing-top-btn"
                           data-narrative-jump="hero">
                            <span>Back to Top</span>
                        </a>
                    </div>
                </div>

                <?php if (!empty($closingHeroObjects)): ?>
                    <div class="cf-closing-visuals" data-closing-actor="visuals" aria-hidden="true">
                        <?php foreach ($closingHeroObjects as $idx => $obj): ?>
                            <div class="cf-closing-obj cf-closing-obj--<?= htmlspecialchars($obj['key'], ENT_QUOTES, 'UTF-8') ?>"
                                 data-closing-obj-index="<?= $idx ?>">
                                <img src="<?= htmlspecialchars($obj['src'], ENT_QUOTES, 'UTF-8') ?>"
                                     alt=""
                                     class="cf-closing-obj-img"
                                     loading="lazy"
                                     decoding="async">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="cf-closing-footer-wrap" data-closing-actor="footer">
        <?php require BASE_PATH . '/app/Views/partials/footer.php'; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($closingPart === 'all' || $closingPart === 'login'): ?>
<!-- Scene 08 & Direct Navbar Shortcut Shared Optical Liquid Glass Login Portal -->
<section class="cf-scene-login-portal"
         id="cf-scene-login-portal"
         aria-labelledby="cf-scene-login-brand-heading">
    <span id="login-scene" class="cf-anchor-compat" aria-hidden="true"></span>

    <!-- Scoped SVG Optical Displacement Filter for Login Glass Card -->
    <svg class="cf-lg-svg-defs" width="0" height="0" aria-hidden="true" focusable="false">
        <defs>
            <filter id="cf-lg-login-filter"
                    x="-12%" y="-12%" width="124%" height="124%"
                    color-interpolation-filters="sRGB"
                    primitiveUnits="objectBoundingBox">
                <feImage id="cf-lg-login-map"
                         x="0" y="0" width="1" height="1"
                         preserveAspectRatio="none"
                         result="dispMap"
                         href="" />
                <feDisplacementMap in="SourceGraphic"
                                   in2="dispMap"
                                   scale="0.032"
                                   xChannelSelector="R"
                                   yChannelSelector="G"
                                   result="refracted" />
                <feGaussianBlur in="refracted" stdDeviation="0.002" result="softGlass" />
            </filter>
        </defs>
    </svg>

    <div class="cf-scene-login-backdrop" id="cf-scene-login-backdrop" aria-hidden="true"></div>

    <div class="container cf-scene-login-container">
        <div class="cf-scene-login-grid">

            <!-- Left Column: Minimalist Brand Statement -->
            <div class="cf-scene-login-brand" id="cf-scene-login-brand">
                <span class="cf-scene-login-eyebrow">
                    <span class="cf-scene-login-eyebrow-dot" aria-hidden="true"></span>
                    <span>Verified Campus Access</span>
                </span>

                <h2 id="cf-scene-login-brand-heading" class="cf-scene-login-wordmark">
                    CAMPUSFIND<span class="cf-scene-login-wordmark-dot">.</span>
                </h2>

                <p class="cf-scene-login-tagline">
                    Lost things. Found connections.
                </p>

                <p class="cf-scene-login-copy">
                    Sign in once to file reports, verify ownership securely with our staff, and track AI-assisted matches across campus.
                </p>

                <button type="button"
                        class="cf-scene-login-back-btn"
                        id="cf-login-scene-back"
                        onclick="if(window.CampusFindLanding && window.CampusFindLanding.narrative && typeof window.CampusFindLanding.narrative.isActive === 'function' && window.CampusFindLanding.narrative.isActive()){return;} window.location.href='/';">
                    <span aria-hidden="true">&larr;</span>
                    <span id="cf-login-scene-back-label">Return to Experience</span>
                </button>
            </div>

            <!-- Right Column: Liquid Glass Login Card -->
            <div class="cf-scene-login-card-wrap" id="cf-scene-login-card-wrap">
                <div class="cf-lg-login-panel" id="cf-lg-login-panel">
                    <div class="cf-lg-login-refract" aria-hidden="true"></div>
                    <div class="cf-lg-login-specular" aria-hidden="true"></div>

                    <div class="cf-lg-login-content">
                        <?php if (!$isLoggedIn): ?>
                            <div class="cf-lg-login-header">
                                <h3 class="cf-lg-login-title">Sign In</h3>
                                <p class="cf-lg-login-subtitle">Enter your CampusFind credentials to continue.</p>
                            </div>

                            <?php
                            $loginFormVariant = 'glass';
                            $loginRedirectTarget = '/';
                            require BASE_PATH . '/app/Views/auth/_login_form.php';
                            ?>
                        <?php else: ?>
                            <div class="cf-lg-login-header">
                                <h3 class="cf-lg-login-title">Signed In</h3>
                                <p class="cf-lg-login-subtitle">
                                    Active session as <?= htmlspecialchars($authDisplayName, ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($authRoleLabel, ENT_QUOTES, 'UTF-8') ?>).
                                </p>
                            </div>

                            <div class="cf-lg-login-authed-actions">
                                <a href="<?= htmlspecialchars($dashboardHref, ENT_QUOTES, 'UTF-8') ?>"
                                   class="cf-hero-btn cf-hero-btn--primary w-100 justify-content-center">
                                    <span>Go to Dashboard</span>
                                    <span class="cf-hero-btn-arrow" aria-hidden="true">&rarr;</span>
                                </a>

                                <a href="/search"
                                   class="cf-hero-btn cf-hero-btn--secondary w-100 justify-content-center">
                                    <span>Search Reports</span>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>
<?php endif; ?>
