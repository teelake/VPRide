<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/Auth.php';
require_once $backendRoot . '/src/LegalPageRepository.php';

use VprideBackend\Auth;
use VprideBackend\Database;
use VprideBackend\LegalPageRepository;

Config::load($backendRoot . '/.env');
Auth::startSession();
Auth::requireLogin();
Auth::requirePermission('settings.manage');

$admin = Auth::currentAdmin();
$pdo = Database::pdo();
$repo = new LegalPageRepository($pdo);
$csrf = Auth::csrfToken();
$message = '';
$error = '';

$rows = $repo->listAll();
$bySlug = [];
foreach ($rows as $r) {
    $bySlug[$r['slug']] = $r;
}
$terms = $bySlug[LegalPageRepository::SLUG_TERMS] ?? [
    'slug' => LegalPageRepository::SLUG_TERMS,
    'title' => 'Terms of Use',
    'body_html' => '<p></p>',
];
$privacy = $bySlug[LegalPageRepository::SLUG_PRIVACY] ?? [
    'slug' => LegalPageRepository::SLUG_PRIVACY,
    'title' => 'Privacy Policy',
    'body_html' => '<p></p>',
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (! Auth::validateCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid session.';
    } else {
        try {
            $repo->upsert(
                LegalPageRepository::SLUG_TERMS,
                (string) ($_POST['terms_title'] ?? $terms['title']),
                (string) ($_POST['terms_html'] ?? ''),
                (int) $admin[0],
            );
            $repo->upsert(
                LegalPageRepository::SLUG_PRIVACY,
                (string) ($_POST['privacy_title'] ?? $privacy['title']),
                (string) ($_POST['privacy_html'] ?? ''),
                (int) $admin[0],
            );
            $message = 'Legal pages saved. Riders will see updates after a short cache window.';
            $rows = $repo->listAll();
            $bySlug = [];
            foreach ($rows as $r) {
                $bySlug[$r['slug']] = $r;
            }
            $terms = $bySlug[LegalPageRepository::SLUG_TERMS] ?? $terms;
            $privacy = $bySlug[LegalPageRepository::SLUG_PRIVACY] ?? $privacy;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$termsHtmlJson = json_encode($terms['body_html'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$privacyHtmlJson = json_encode($privacy['body_html'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

header('Content-Type: text/html; charset=utf-8');
$pageTitle = 'Legal pages · VP Ride Console';
$bodyClass = 'vp-body vp-body--app';
$vpNavActive = 'legal_pages';
$vpTopbarTitle = 'Legal pages';
require __DIR__ . '/includes/head.php';
?>
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<?php
require __DIR__ . '/includes/app_shell_start.php';
?>

<header class="vp-page-hero">
  <h1 class="vp-page-title">Rider legal pages</h1>
  <p class="vp-page-desc">Edit <strong>Terms of Use</strong> and <strong>Privacy Policy</strong> shown in the mobile app (sign-up notice and full-screen readers). Content is rich text via Quill; the app loads HTML from <code class="vp-inline-code">GET /api/v1/legal-pages/{slug}</code>.</p>
</header>

<?php if ($message !== '') { ?>
  <div class="vp-alert vp-alert--success" role="status"><?= vp_h($message) ?></div>
<?php } ?>
<?php if ($error !== '') { ?>
  <div class="vp-alert vp-alert--error" role="alert"><?= vp_h($error) ?></div>
<?php } ?>

<form method="post" action="<?= vp_h(vp_url('/legal-pages')) ?>" id="vp-legal-form" class="vp-hub-grid vp-hub-grid--stack">
  <input type="hidden" name="_csrf" value="<?= vp_h($csrf) ?>">
  <input type="hidden" name="terms_html" id="vp_terms_html" value="">
  <input type="hidden" name="privacy_html" id="vp_privacy_html" value="">

  <section class="vp-card" aria-labelledby="legal-terms-h">
    <div class="vp-card__pad">
      <h2 id="legal-terms-h" class="vp-section-title">Terms of Use</h2>
      <p class="vp-page-desc" style="margin-top:-0.25rem;">Slug: <code class="vp-inline-code">terms_of_use</code></p>
      <label class="vp-label" for="terms_title">Title (app bar)</label>
      <input class="vp-input vp-input--block" type="text" id="terms_title" name="terms_title" value="<?= vp_h($terms['title']) ?>" maxlength="255" required>
      <label class="vp-label" for="vp_quill_terms" style="margin-top:1rem;">Body</label>
      <div id="vp_quill_terms" class="vp-quill-wrap"></div>
    </div>
  </section>

  <section class="vp-card" aria-labelledby="legal-privacy-h">
    <div class="vp-card__pad">
      <h2 id="legal-privacy-h" class="vp-section-title">Privacy Policy</h2>
      <p class="vp-page-desc" style="margin-top:-0.25rem;">Slug: <code class="vp-inline-code">privacy_policy</code></p>
      <label class="vp-label" for="privacy_title">Title (app bar)</label>
      <input class="vp-input vp-input--block" type="text" id="privacy_title" name="privacy_title" value="<?= vp_h($privacy['title']) ?>" maxlength="255" required>
      <label class="vp-label" for="vp_quill_privacy" style="margin-top:1rem;">Body</label>
      <div id="vp_quill_privacy" class="vp-quill-wrap"></div>
    </div>
  </section>

  <div class="vp-form-actions">
    <button type="submit" class="vp-btn vp-btn--primary">Save both pages</button>
  </div>
</form>

<style>
  .vp-quill-wrap .ql-container { min-height: 220px; font-size: 1rem; }
  .vp-quill-wrap { background: #fff; border-radius: 12px; border: 1px solid rgba(0,0,0,.08); }
  .vp-quill-wrap .ql-toolbar { border-radius: 12px 12px 0 0; border-left: none; border-right: none; border-top: none; }
  .vp-quill-wrap .ql-container { border: none; border-radius: 0 0 12px 12px; }
</style>
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
(function () {
  var termsSeed = <?= $termsHtmlJson ?>;
  var privacySeed = <?= $privacyHtmlJson ?>;
  var quillTerms = new Quill('#vp_quill_terms', {
    theme: 'snow',
    modules: {
      toolbar: [
        [{ 'header': [1, 2, 3, false] }],
        ['bold', 'italic', 'underline', 'strike'],
        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
        ['blockquote', 'code-block'],
        ['link'],
        ['clean']
      ]
    }
  });
  var quillPrivacy = new Quill('#vp_quill_privacy', {
    theme: 'snow',
    modules: {
      toolbar: [
        [{ 'header': [1, 2, 3, false] }],
        ['bold', 'italic', 'underline', 'strike'],
        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
        ['blockquote', 'code-block'],
        ['link'],
        ['clean']
      ]
    }
  });
  if (termsSeed && typeof termsSeed === 'string') {
    quillTerms.clipboard.dangerouslyPasteHTML(termsSeed);
  }
  if (privacySeed && typeof privacySeed === 'string') {
    quillPrivacy.clipboard.dangerouslyPasteHTML(privacySeed);
  }
  document.getElementById('vp-legal-form').addEventListener('submit', function () {
    document.getElementById('vp_terms_html').value = quillTerms.root.innerHTML;
    document.getElementById('vp_privacy_html').value = quillPrivacy.root.innerHTML;
  });
})();
</script>

<?php require __DIR__ . '/includes/app_shell_end.php'; ?>
