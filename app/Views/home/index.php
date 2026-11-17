<?php
/**
 * app/Views/home/index.php
 * Landing page — Campus Find
 * Phase 4C: Cinematic Scroll Narrative System (Scene 01 → Scene 08)
 */

$canBrowse = !empty($canBrowseCatalog);
$exploreHref = $canBrowse ? '#recently-reported' : ($exploreFoundUrl ?? '/login');

// Slot konfigurasi objek 3D Hero (Batch 2: 5 objek dengan directional bias & spatial depth)
$heroAssetSlots = [
    [
        'key'   => 'notebook',
        'src'   => '/images/landing/notebook.webp',
        'alt'   => 'Hardcover campus notebook',
        'depth' => '20',
        'dx'    => '1.1',
        'dy'    => '-0.8',
    ],
    [
        'key'   => 'wallet',
        'src'   => '/images/landing/wallet.webp',
        'alt'   => 'Minimalist leather wallet',
        'depth' => '32',
        'dx'    => '-0.9',
        'dy'    => '1.1',
    ],
    [
        'key'   => 'keys',
        'src'   => '/images/landing/keys.webp',
        'alt'   => 'Campus keys with leather fob',
        'depth' => '42',
        'dx'    => '1.2',
        'dy'    => '1.0',
    ],
    [
        'key'   => 'earbuds',
        'src'   => '/images/landing/earbuds.webp',
        'alt'   => 'Wireless earbuds charging case',
        'depth' => '36',
        'dx'    => '-1.1',
        'dy'    => '-1.0',
    ],
    [
        'key'   => 'tumbler',
        'src'   => '/images/landing/tumbler.webp',
        'alt'   => 'Insulated stainless tumbler',
        'depth' => '26',
        'dx'    => '0.9',
        'dy'    => '-0.7',
    ],
];

$availableHeroObjects = array_values(array_filter(
    $heroAssetSlots,
    static fn(array $slot): bool => is_file(BASE_PATH . '/public' . $slot['src'])
));
$isCompleteHeroSet = count($availableHeroObjects) >= 5;
?>

<div class="cf-narrative-root" id="cf-narrative-root" data-active-beat="hero">

    <div class="cf-stage-viewport" id="cf-stage-viewport">

        <!-- Scene Layer 1: Hero -->
        <div class="cf-scene-layer cf-scene-layer--hero is-scene-active" id="cf-scene-hero" data-scene-group="hero">
            <!-- Phase 1: Light Editorial Hero Section -->
            <section class="cf-hero" id="hero" aria-labelledby="hero-heading">
                <div class="container cf-hero-container">
                    <div class="row align-items-center cf-hero-row">

                        <!-- Left Column: Editorial Typography & CTAs -->
                        <div class="col-lg-6 cf-hero-copy">
                            <div class="cf-hero-scrub-wrap cf-hero-scrub--eyebrow" id="cf-hero-scrub-eyebrow">
                                <span class="cf-hero-eyebrow">
                                    <span class="cf-hero-eyebrow-dot" aria-hidden="true"></span>
                                    Campus Lost &amp; Found
                                </span>
                            </div>

                            <div class="cf-hero-scrub-wrap cf-hero-scrub--title" id="cf-hero-scrub-title">
                                <h1 id="hero-heading" class="cf-hero-title">
                                    <span class="cf-hero-line-clip" id="cf-hero-scrub-line-1">
                                        <span class="cf-hero-line cf-hero-line--1">Looking for</span>
                                    </span>
                                    <span class="cf-hero-line-clip" id="cf-hero-scrub-line-2">
                                        <span class="cf-hero-line cf-hero-line--2">Something?</span>
                                    </span>
                                </h1>
                            </div>

                            <div class="cf-hero-scrub-wrap cf-hero-scrub--sub" id="cf-hero-scrub-sub">
                                <p class="cf-hero-subtitle">
                                    Lost something on campus? Let&rsquo;s help you find it.
                                </p>
                            </div>

                            <div class="cf-hero-scrub-wrap cf-hero-scrub--actions" id="cf-hero-scrub-actions">
                                <div class="cf-hero-actions">
                                    <a href="<?= htmlspecialchars($exploreHref, ENT_QUOTES, 'UTF-8') ?>"
                                       class="cf-hero-btn cf-hero-btn--primary"
                                       id="hero-cta-explore"
                                       <?= $canBrowse ? 'data-landing-filter="found"' : '' ?>>
                                        <span>Explore Found Items</span>
                                        <span class="cf-hero-btn-arrow" aria-hidden="true">&rarr;</span>
                                    </a>

                                    <a href="#how-it-works"
                                       class="cf-hero-btn cf-hero-btn--secondary"
                                       id="hero-cta-how-it-works"
                                       data-smooth-scroll="1">
                                        <span>How It Works</span>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Cinematic Floating 3D Product Composition -->
                        <div class="col-lg-6 cf-hero-visual">
                            <div class="cf-hero-stage <?= $isCompleteHeroSet ? 'cf-hero-stage--complete' : 'cf-hero-stage--interim' ?>"
                                 id="cf-hero-stage"
                                 aria-hidden="true">
                                <!-- Living Ambient Environment: Subtly Breathing Diffused Light Fields -->
                                <div class="cf-hero-ambient-env" id="cf-hero-ambient-env" aria-hidden="true">
                                    <div class="cf-hero-ambient-glow cf-hero-ambient-glow--cognac" id="cf-hero-glow-cognac"></div>
                                    <div class="cf-hero-ambient-glow cf-hero-ambient-glow--sage" id="cf-hero-glow-sage"></div>
                                    <div class="cf-hero-ambient-glow cf-hero-ambient-glow--focal" id="cf-hero-glow-focal"></div>
                                </div>

                                <?php foreach ($availableHeroObjects as $objIdx => $obj): ?>
                                    <div class="cf-hero-slot cf-hero-slot--<?= htmlspecialchars($obj['key'], ENT_QUOTES, 'UTF-8') ?>"
                                         data-parallax-depth="<?= htmlspecialchars($obj['depth'], ENT_QUOTES, 'UTF-8') ?>"
                                         data-parallax-dx="<?= htmlspecialchars($obj['dx'] ?? '1', ENT_QUOTES, 'UTF-8') ?>"
                                         data-parallax-dy="<?= htmlspecialchars($obj['dy'] ?? '1', ENT_QUOTES, 'UTF-8') ?>">
                                        <div class="cf-hero-scrub-obj" data-hero-scrub-obj="<?= $objIdx ?>">
                                            <div class="cf-hero-entrance">
                                                <div class="cf-hero-float">
                                                    <img src="<?= htmlspecialchars($obj['src'], ENT_QUOTES, 'UTF-8') ?>"
                                                         alt="<?= htmlspecialchars($obj['alt'], ENT_QUOTES, 'UTF-8') ?>"
                                                         class="cf-hero-object-img"
                                                         decoding="async"
                                                         fetchpriority="high">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                    </div>
                </div>
            </section>
        </div>

        <!-- Scene Layer 2: Recently Reported -->
        <div class="cf-scene-layer cf-scene-layer--recent" id="cf-scene-recent" data-scene-group="recent">
            <?php require BASE_PATH . '/app/Views/home/_recently_reported.php'; ?>
        </div>

        <!-- Scene Layer 3: How It Works (Intro, Journey Choice, 5 Lost Stages, 5 Found Stages) -->
        <div class="cf-scene-layer cf-scene-layer--hiw" id="cf-scene-hiw" data-scene-group="hiw">
            <?php require BASE_PATH . '/app/Views/home/_how_it_works.php'; ?>
        </div>

        <!-- Scene Layer 4: Journey-Specific Completion CTA -->
        <div class="cf-scene-layer cf-scene-layer--cta" id="cf-scene-cta" data-scene-group="cta">
            <?php require BASE_PATH . '/app/Views/home/_final_cta.php'; ?>
        </div>

        <!-- Scene Layer 5: Emotional Closing + Footer -->
        <div class="cf-scene-layer cf-scene-layer--closing" id="cf-scene-closing" data-scene-group="closing">
            <?php $closingPart = 'closing'; require BASE_PATH . '/app/Views/home/_closing_login.php'; ?>
        </div>

    </div>

    <!-- Shared Portal Overlay Layer: Full-Screen Login Scene (outside scene viewport & transformed ancestors) -->
    <?php $closingPart = 'login'; require BASE_PATH . '/app/Views/home/_closing_login.php'; ?>

</div>
