<?php
require_once __DIR__ . '/../../includes/auth-guard.php';
require_once __DIR__ . '/../../includes/role-guard.php';

$pageTitle  = 'Database ERD';
$activePage = 'erd';

$erdPath = __DIR__ . '/../../database/erd.md';
$erdDocument = file_get_contents($erdPath);
if ($erdDocument === false) {
    error_log('Database ERD could not be read from ' . $erdPath);
    http_response_code(500);
    exit('The database ERD is currently unavailable.');
}

if (!preg_match('/```mermaid\s*(.*?)```/s', $erdDocument, $matches)) {
    error_log('Database ERD Mermaid diagram was not found in ' . $erdPath);
    http_response_code(500);
    exit('The database ERD diagram is currently unavailable.');
}
$erdDiagram = $matches[1];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar"><span class="topbar-title">Database ERD</span></div>

  <div class="page-body">
    <div class="page-header">
      <div>
        <h5>Database Entity Relationship Diagram</h5>
        <p class="text-muted small mb-0">
          Explore the database tables and their relationships.
        </p>
      </div>
      <a class="btn btn-outline-primary"
         href="/tailorshop/database/erd.md" download="erd.md">
        <i class="bi bi-download me-1"></i> Download Mermaid source
      </a>
    </div>

    <div class="card">
      <div class="card-body">
        <div id="erd-status" class="text-muted small mb-3" role="status">
          Loading diagram…
        </div>
        <div class="overflow-auto">
          <pre id="erd-diagram" class="mermaid"><?= htmlspecialchars($erdDiagram, ENT_QUOTES, 'UTF-8') ?></pre>
        </div>
      </div>
    </div>
  </div>
</div>

<script type="module">
  const status = document.getElementById('erd-status');
  const diagram = document.getElementById('erd-diagram');

  try {
    const { default: mermaid } = await import(
      'https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.esm.min.mjs'
    );
    mermaid.initialize({
      startOnLoad: false,
      theme: document.documentElement.dataset.theme === 'light' ? 'default' : 'dark',
      securityLevel: 'strict',
    });
    await mermaid.run({ nodes: [diagram] });
    status.textContent = 'Diagram loaded from the database ERD source.';
  } catch (error) {
    console.error('Failed to render the database ERD:', error);
    status.textContent = 'Could not render the diagram. Check your connection and reload the page.';
    status.classList.remove('text-muted');
    status.classList.add('text-danger');
  }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
