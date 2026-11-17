<?php
/**
 * app/Views/home/_how_it_works.php
 * Phase 3: Dark Navy (#14283C) "How It Works" Interactive Two-Journey Section
 *
 * Route Compatibility:
 * - Primary anchor: #how-it-works
 * - Legacy anchor compatibility: #fitur (supports GET /cara-kerja -> /#fitur redirect)
 */

$hiwStatusLabelMap = [
    'Status: Laporan Baru' => 'Status: New Report',
];

$hiwJourneys = [
    'lost' => [
        'key'       => 'lost',
        'tabLabel'  => 'I Lost Something',
        'subtitle'  => 'Lost Item Journey',
        'stages'    => [
            [
                'code'    => '01',
                'label'   => 'Report Lost',
                'icon'    => 'bi-pencil-square',
                'title'   => 'Submit a structured lost-item report.',
                'desc'    => 'Sign in to log what went missing with its category, approximate campus location, loss date, optional photo, and confidential private details that remain hidden from other students.',
                'badge'   => $hiwStatusLabelMap['Status: Laporan Baru'],
                'actor'   => 'Reporter (Civitas)',
                'points'  => [
                    'Confidential private details visible only to staff during claim checks',
                    'Approximate campus zone helps narrow down recovery leads',
                    'Track report progress directly from your personal dashboard',
                ],
            ],
            [
                'code'    => '02',
                'label'   => 'AI Matching',
                'icon'    => 'bi-stars',
                'title'   => 'AI Matching scans incoming found reports.',
                'desc'    => 'CampusFind compares your report’s title, category, description, and campus location against verified found items and newly reported discoveries.',
                'badge'   => 'Engine: AI Candidate Matching',
                'actor'   => 'CampusFind Matching Service',
                'points'  => [
                    'Cross-checks lost descriptions against incoming found items',
                    'Ranks potential campus matches by category, details, and area',
                    'Surfaces likely candidates inside your report matching view',
                ],
            ],
            [
                'code'    => '03',
                'label'   => 'Review & Claim',
                'icon'    => 'bi-file-earmark-check',
                'title'   => 'Review likely matches and submit a claim.',
                'desc'    => 'Browse verified catalog findings or matching candidates, confirm the general item traits, and file a formal claim backed by specific ownership proof.',
                'badge'   => 'Workflow: Claim Submission',
                'actor'   => 'Claimant (Civitas)',
                'points'  => [
                    'Inspect verified public catalog details behind campus login',
                    'Attach proof descriptions or supporting files securely via protected storage',
                    'Track active claim statuses cleanly under My Claims',
                ],
            ],
            [
                'code'    => '04',
                'label'   => 'Ownership Verification',
                'icon'    => 'bi-shield-check',
                'title'   => 'Petugas verify ownership confidentially.',
                'desc'    => 'Lost & Found Center staff compare your submitted claim evidence against the physical item’s undisclosed traits and request extra clarification if anything looks unclear.',
                'badge'   => 'Review: Menunggu Verifikasi',
                'actor'   => 'Petugas Lost & Found Center',
                'points'  => [
                    'Protects students against false or premature claim attempts',
                    'Supports follow-up proof requests before final approval',
                    'Enforces role-checked image access until verification passes',
                ],
            ],
            [
                'code'    => '05',
                'label'   => 'Handover',
                'icon'    => 'bi-patch-check',
                'title'   => 'Collect your item via verified handover.',
                'desc'    => 'Once your claim is approved, pick up your item at the campus Lost & Found Center while Petugas record the official handover and synchronize report closure.',
                'badge'   => 'Status: Selesai / Dikembalikan',
                'actor'   => 'Petugas & Verified Owner',
                'points'  => [
                    'In-person release logged with verified claimant records',
                    'Marks the linked digital report and inventory record completed together',
                    'Preserves full custody audit trail inside CampusFind',
                ],
            ],
        ],
    ],
    'found' => [
        'key'       => 'found',
        'tabLabel'  => 'I Found Something',
        'subtitle'  => 'Found Item Journey',
        'stages'    => [
            [
                'code'    => '01',
                'label'   => 'Report Found',
                'icon'    => 'bi-box-seam',
                'title'   => 'Log the discovered item quickly on campus.',
                'desc'    => 'Record the item’s category, campus discovery zone, condition, and an optional reference photo—unverified uploads remain access-controlled until staff review.',
                'badge'   => $hiwStatusLabelMap['Status: Laporan Baru'],
                'actor'   => 'Finder (Civitas / Staff)',
                'points'  => [
                    'Captures location, date found, and condition accurately at discovery time',
                    'Keeps non-owner photos hidden before verification passes',
                    'Generates a traceable report reference immediately upon submission',
                ],
            ],
            [
                'code'    => '02',
                'label'   => 'Physical Verification',
                'icon'    => 'bi-clipboard2-check',
                'title'   => 'Hand the item to Petugas for inspection.',
                'desc'    => 'Bring the item to the Lost & Found Center so staff can physically inspect it, record confidential identifying marks, and mark the digital report verified.',
                'badge'   => 'Status: Terverifikasi',
                'actor'   => 'Petugas Lost & Found Center',
                'points'  => [
                    'Confirms physical condition and confidential ownership cues',
                    'Unlocks authorized catalog visibility for signed-in campus members',
                    'Transitions custody safely from finder to official campus staff',
                ],
            ],
            [
                'code'    => '03',
                'label'   => 'Inventory',
                'icon'    => 'bi-archive',
                'title'   => 'Register custody in tracked Posko inventory.',
                'desc'    => 'Staff assign a unique inventory code, place the item inside a locked cabinet or shelf at the Lost & Found Center, and synchronize live Posko stock metrics.',
                'badge'   => 'Custody: Tersimpan di L&F Center',
                'actor'   => 'Inventory Management',
                'points'  => [
                    'Logs unique ITM inventory code and physical storage slot',
                    'Keeps dashboard and C-Find Posko counts synchronized with custody status',
                    'Protects physical items in locked Center storage until claimed',
                ],
            ],
            [
                'code'    => '04',
                'label'   => 'AI Matching',
                'icon'    => 'bi-cpu',
                'title'   => 'Match verified items with student lost reports.',
                'desc'    => 'CampusFind runs AI Matching against open lost reports so owners searching for similar electronics, wallets, keys, or notebooks can spot their item promptly.',
                'badge'   => 'Engine: AI Candidate Matching',
                'actor'   => 'CampusFind Matching Service',
                'points'  => [
                    'Connects verified Posko items with matching student loss reports',
                    'Reduces waiting time between Center intake and owner discovery',
                    'Respects strict confidentiality by exposing only public attributes',
                ],
            ],
            [
                'code'    => '05',
                'label'   => 'Claim & Handover',
                'icon'    => 'bi-people',
                'title'   => 'Verify claimant proof and complete handover.',
                'desc'    => 'When a student submits a claim, Petugas validate ownership evidence, hand over the physical item, and automatically update both inventory and report statuses.',
                'badge'   => 'Status: Handed Over / Selesai',
                'actor'   => 'Petugas & Verified Claimant',
                'points'  => [
                    'Requires staff-approved proof before releasing stored property',
                    'Simultaneously marks physical inventory as handed over',
                    'Closes the lifecycle cleanly across reports, claims, and metrics',
                ],
            ],
        ],
    ],
];

$defaultJourney = $hiwJourneys['lost'];
$defaultStage = $defaultJourney['stages'][0];
?>

<section class="cf-hiw" id="how-it-works" aria-labelledby="hiw-heading">
    <!-- Legacy route compatibility anchor for /cara-kerja -> /#fitur -->
    <span id="fitur" class="cf-anchor-compat" aria-hidden="true"></span>

    <!-- Living Spatial Environment: Ambient Light Fields & Faint Orbital Perspective Geometry (Batch 1) -->
    <div class="cf-hiw-ambient-env" id="cf-hiw-ambient-env" aria-hidden="true">
        <div class="cf-hiw-ambient-glow cf-hiw-ambient-glow--cognac" id="cf-hiw-glow-cognac"></div>
        <div class="cf-hiw-ambient-glow cf-hiw-ambient-glow--sage" id="cf-hiw-glow-sage"></div>
        <div class="cf-hiw-ambient-glow cf-hiw-ambient-glow--focal" id="cf-hiw-glow-focal"></div>
        <svg class="cf-hiw-orbital-rings" viewBox="0 0 1200 600" preserveAspectRatio="none">
            <ellipse cx="600" cy="350" rx="540" ry="145" class="cf-hiw-orbit-ellipse cf-hiw-orbit-ellipse--outer" />
            <ellipse cx="600" cy="340" rx="390" ry="100" class="cf-hiw-orbit-ellipse cf-hiw-orbit-ellipse--inner" />
            <path d="M 80 350 C 320 440, 880 440, 1120 350" class="cf-hiw-orbit-arc" />
        </svg>
    </div>

    <div class="cf-hiw-sticky" id="cf-hiw-sticky">
        <div class="container cf-hiw-container">
            <div class="cf-hiw-shell" id="cf-hiw-shell" data-stage-phase="intro">

                <!-- Intro & Compact Side/Top Navigation Rail (Transforms seamlessly in Scene 04) -->
                <div class="cf-hiw-header cf-hiw-rail" id="cf-hiw-rail">
                    <div class="cf-hiw-heading-wrap" id="cf-hiw-heading-wrap">
                        <span class="cf-hiw-eyebrow">
                            <span class="cf-hiw-eyebrow-dot" aria-hidden="true"></span>
                            <span>CampusFind Workflow</span>
                        </span>
                        <h2 id="hiw-heading" class="cf-hiw-title">How It Works</h2>
                        <p class="cf-hiw-subtitle">
                            Follow how CampusFind handles lost reports, verified found custody, AI matching, and secure handover across campus.
                        </p>
                    </div>

                    <!-- Two Interactive Journeys Switcher -->
                    <div class="cf-hiw-choice-wrap" id="cf-hiw-choice-wrap">
                        <div class="cf-hiw-journey-switch" role="tablist" aria-label="Choose a CampusFind journey" id="cf-hiw-journey-tablist">
                            <span class="cf-hiw-tab-indicator" id="cf-hiw-tab-indicator" aria-hidden="true"></span>
                            <button type="button"
                                    role="tab"
                                    id="cf-hiw-journey-lost"
                                    class="cf-hiw-journey-tab is-active"
                                    aria-selected="true"
                                    aria-controls="cf-hiw-panel"
                                    data-journey="lost"
                                    tabindex="0">
                                <span class="cf-hiw-choice-top">
                                    <span class="cf-hiw-choice-kicker">Lost Item Journey</span>
                                    <span class="cf-hiw-choice-arrow" aria-hidden="true">&rarr;</span>
                                </span>
                                <span class="cf-hiw-choice-label">I Lost Something</span>
                            </button>
                            <button type="button"
                                    role="tab"
                                    id="cf-hiw-journey-found"
                                    class="cf-hiw-journey-tab"
                                    aria-selected="false"
                                    aria-controls="cf-hiw-panel"
                                    data-journey="found"
                                    tabindex="-1">
                                <span class="cf-hiw-choice-top">
                                    <span class="cf-hiw-choice-kicker">Found Item Journey</span>
                                    <span class="cf-hiw-choice-arrow" aria-hidden="true">&rarr;</span>
                                </span>
                                <span class="cf-hiw-choice-label">I Found Something</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Main Showcase Stage (Opens smoothly alongside compact rail in Scene 04/05) -->
                <div class="cf-hiw-showcase" id="cf-hiw-showcase">
                    <!-- Five Interactive Stages & Animated Progress Line -->
                    <div class="cf-hiw-stepper-wrap" id="cf-hiw-stepper-wrap">
                        <div class="cf-hiw-progress-rail" aria-hidden="true">
                            <div class="cf-hiw-progress-fill" id="cf-hiw-progress-fill" style="width: 0%;"></div>
                        </div>

                        <div class="cf-hiw-stages" id="cf-hiw-stages" role="tablist" aria-label="Journey stages">
                            <?php foreach ($defaultJourney['stages'] as $idx => $stage): ?>
                                <?php $isActiveStage = ($idx === 0); ?>
                                <button type="button"
                                        role="tab"
                                        id="cf-hiw-stage-btn-<?= $idx ?>"
                                        class="cf-hiw-stage-btn <?= $isActiveStage ? 'is-active' : '' ?>"
                                        aria-selected="<?= $isActiveStage ? 'true' : 'false' ?>"
                                        aria-controls="cf-hiw-panel"
                                        data-stage-index="<?= $idx ?>"
                                        tabindex="<?= $isActiveStage ? '0' : '-1' ?>">
                                    <span class="cf-hiw-stage-node"><?= htmlspecialchars($stage['code'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="cf-hiw-stage-label"><?= htmlspecialchars($stage['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- 3D Floating Coverflow Carousel Stage (Phase 4C Repair 4.2) -->
                    <div class="cf-hiw-panel cf-hiw-coverflow-viewport"
                         id="cf-hiw-panel"
                         role="region"
                         aria-label="CampusFind journey stages coverflow"
                         tabindex="0">

                        <!-- Screen-reader & test-harness synchronized metadata -->
                        <div class="cf-hiw-sr-stage-meta visually-hidden" aria-live="polite">
                            <span id="cf-hiw-panel-step">Stage 1 of 5 &middot; <?= htmlspecialchars($defaultJourney['subtitle'], ENT_QUOTES, 'UTF-8') ?></span>
                            <h3 id="cf-hiw-panel-title"><?= htmlspecialchars($defaultStage['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                            <p id="cf-hiw-panel-desc"><?= htmlspecialchars($defaultStage['desc'], ENT_QUOTES, 'UTF-8') ?></p>
                            <span id="cf-hiw-card-num"><?= htmlspecialchars($defaultStage['code'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span id="cf-hiw-card-badge"><?= htmlspecialchars($defaultStage['badge'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span id="cf-hiw-card-actor"><?= htmlspecialchars($defaultStage['actor'], ENT_QUOTES, 'UTF-8') ?></span>
                            <button type="button" id="cf-hiw-prev-stage" disabled aria-label="Previous stage"></button>
                            <button type="button" id="cf-hiw-next-stage" aria-label="Next stage"></button>
                        </div>

                        <!-- 3D Floating Coverflow Track containing all 5 journey stages -->
                        <div class="cf-hiw-panel-inner cf-hiw-coverflow-track" id="cf-hiw-panel-inner" role="presentation">
                            <?php foreach ($defaultJourney['stages'] as $idx => $stage): ?>
                                <?php $isActive = ($idx === 0); ?>
                                <div class="cf-hiw-card-3d <?= $isActive ? 'is-active' : '' ?>"
                                     id="cf-hiw-card-<?= $idx ?>"
                                     data-stage-index="<?= $idx ?>"
                                     role="group"
                                     aria-label="Stage <?= $idx + 1 ?> of 5: <?= htmlspecialchars($stage['title'], ENT_QUOTES, 'UTF-8') ?>"
                                     aria-current="<?= $isActive ? 'step' : 'false' ?>"
                                     tabindex="<?= $isActive ? '0' : '-1' ?>">
                                    <div class="cf-hiw-card-3d-inner">
                                        <article class="cf-hiw-card-surface">
                                            <div class="cf-hiw-card-accent" aria-hidden="true"></div>
                                            <div class="cf-hiw-card-top">
                                                <div class="cf-hiw-card-icon-badge">
                                                    <span class="cf-hiw-card-icon-wrap" aria-hidden="true">
                                                        <i class="bi <?= htmlspecialchars($stage['icon'], ENT_QUOTES, 'UTF-8') ?> cf-hiw-card-icon"></i>
                                                    </span>
                                                    <span class="cf-hiw-card-badge"><?= htmlspecialchars($stage['badge'], ENT_QUOTES, 'UTF-8') ?></span>
                                                </div>
                                                <span class="cf-hiw-card-num"><?= htmlspecialchars($stage['code'], ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>

                                            <div class="cf-hiw-card-main">
                                                <div class="cf-hiw-card-step-meta">
                                                    Stage <?= $idx + 1 ?> of 5 &middot; <?= htmlspecialchars($defaultJourney['subtitle'], ENT_QUOTES, 'UTF-8') ?>
                                                </div>
                                                <h3 class="cf-hiw-card-title"><?= htmlspecialchars($stage['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                                                <p class="cf-hiw-card-desc"><?= htmlspecialchars($stage['desc'], ENT_QUOTES, 'UTF-8') ?></p>
                                                <ul class="cf-hiw-card-points">
                                                    <?php foreach ($stage['points'] as $pt): ?>
                                                        <li class="cf-hiw-card-point">
                                                            <span class="cf-hiw-point-marker" aria-hidden="true"></span>
                                                            <span><?= htmlspecialchars($pt, ENT_QUOTES, 'UTF-8') ?></span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>

                                            <div class="cf-hiw-card-footer">
                                                <div class="cf-hiw-card-actor-wrap">
                                                    <span class="cf-hiw-card-actor-label">Workflow Participant</span>
                                                    <div class="cf-hiw-card-actor"><?= htmlspecialchars($stage['actor'], ENT_QUOTES, 'UTF-8') ?></div>
                                                </div>
                                                <div class="cf-hiw-card-nav">
                                                    <button type="button"
                                                            class="cf-hiw-step-nav-btn cf-hiw-card-prev"
                                                            data-nav-dir="-1"
                                                            <?= $idx === 0 ? 'disabled' : '' ?>
                                                            aria-label="Previous stage">
                                                        <span aria-hidden="true">&larr;</span>
                                                        <span>Prev</span>
                                                    </button>
                                                    <button type="button"
                                                            class="cf-hiw-step-nav-btn cf-hiw-card-next"
                                                            data-nav-dir="1"
                                                            <?= $idx === 4 ? 'disabled' : '' ?>
                                                            aria-label="Next stage">
                                                        <span>Next</span>
                                                        <span aria-hidden="true">&rarr;</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </article>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Structured JSON Stage Data (Zero inline HTML injection) -->
            <script type="application/json" id="cf-hiw-data"><?= json_encode($hiwJourneys, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

        </div>
    </div>
</section>

