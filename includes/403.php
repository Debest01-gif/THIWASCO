<?php
$pageTitle = 'Access Denied - 403';
include_once __DIR__ . '/header.php';
?>
<div style="min-height: 60vh; display: flex; align-items: center; justify-content: center; text-align: center; padding: 2rem;">
  <div class="card" style="max-width: 540px; padding: 2.5rem; border-top: 4px solid var(--crimson, #e63946);">
    <div style="font-size: 4rem; color: #e63946; margin-bottom: 1rem;">
      <i class="fa-solid fa-shield-halved"></i>
    </div>
    <h2 style="font-size: 1.75rem; font-weight: 700; color: var(--navy, #0f2744); margin-bottom: 0.75rem;">Access Restricted</h2>
    <p style="color: var(--text-muted, #64748b); font-size: 0.95rem; margin-bottom: 1.5rem; line-height: 1.6;">
      You do not have the required role or permission privileges to access this module. If you require access, please contact the System Administrator.
    </p>
    <div style="display: flex; gap: 1rem; justify-content: center;">
      <a href="<?= APP_URL ?>/index.php" class="btn btn-primary">
        <i class="fa-solid fa-house"></i> Return to Dashboard
      </a>
      <button onclick="history.back()" class="btn btn-secondary">
        <i class="fa-solid fa-arrow-left"></i> Go Back
      </button>
    </div>
  </div>
</div>
<?php include_once __DIR__ . '/footer.php'; ?>
