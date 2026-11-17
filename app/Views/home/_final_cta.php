<?php
/**
 * app/Views/home/_final_cta.php
 * Phase 4C: Scene 06 — Journey Completion CTA (Soft White #F7F8FA)
 *
 * Uses sanitized view variables from HomeController::index():
 * - $isLoggedInUser (bool)
 */

$isLoggedIn = !empty($isLoggedInUser);
$lostHref = $isLoggedIn ? '/reports/lost/create' : '/login';
$foundHref = $isLoggedIn ? '/reports/found/create' : '/login';
?>

<section class="cf-final-cta"
         id="final-cta"
         aria-labelledby="final-cta-heading"
         data-active-journey="lost">
    <div class="container cf-final-cta-container">
        <div class="cf-final-cta-inner" id="cf-final-cta-inner">
            <span class="cf-final-cta-eyebrow" id="cf-final-cta-eyebrow">
                <span class="cf-final-cta-dot" aria-hidden="true"></span>
                <span id="cf-final-cta-eyebrow-text"
                      data-lost-eyebrow="Lost Journey Complete"
                      data-found-eyebrow="Found Journey Complete">Lost Journey Complete</span>
            </span>

            <h2 id="final-cta-heading"
                class="cf-final-cta-title"
                data-lost-title="Ready to report your lost item?"
                data-found-title="Ready to report a found item?">
                Ready to report your lost item?
            </h2>

            <p class="cf-final-cta-desc"
               id="cf-final-cta-desc"
               data-lost-desc="Log your lost belonging inside CampusFind&rsquo;s verified matching and handover workflow."
               data-found-desc="Register a found campus item for secure custody, verification, and rightful return.">
                Log your lost belonging inside CampusFind&rsquo;s verified matching and handover workflow.
            </p>

            <div class="cf-final-cta-actions">
                <a href="<?= htmlspecialchars($lostHref, ENT_QUOTES, 'UTF-8') ?>"
                   class="cf-hero-btn cf-hero-btn--primary cf-final-cta-btn"
                   id="cf-final-cta-primary"
                   data-lost-href="<?= htmlspecialchars($lostHref, ENT_QUOTES, 'UTF-8') ?>"
                   data-found-href="<?= htmlspecialchars($foundHref, ENT_QUOTES, 'UTF-8') ?>"
                   data-lost-label="Report Lost Item"
                   data-found-label="Report Found Item">
                    <span id="cf-final-cta-primary-label">Report Lost Item</span>
                    <span class="cf-hero-btn-arrow" aria-hidden="true">&rarr;</span>
                </a>

                <button type="button"
                        class="cf-hero-btn cf-hero-btn--secondary cf-final-cta-secondary"
                        id="cf-final-cta-secondary"
                        data-target-journey="found"
                        data-lost-label="Switch to Found Journey"
                        data-found-label="Switch to Lost Journey">
                    <span id="cf-final-cta-secondary-label">Switch to Found Journey</span>
                </button>
            </div>
        </div>
    </div>
</section>


