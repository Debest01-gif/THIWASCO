    </main>
  </div><!-- /.main-content -->
</div><!-- /.app-wrapper -->

<!-- ===== GLOBAL JAVASCRIPT ===== -->
<script>
// Sidebar toggle
function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  if (window.innerWidth <= 768) {
    sidebar.classList.toggle('mobile-open');
  } else {
    sidebar.classList.toggle('collapsed');
    document.querySelector('.main-content').classList.toggle('sidebar-collapsed');
  }
}

// Submenu toggle
function toggleNav(el) {
  const item = el.closest('.nav-item');
  const submenu = item.querySelector('.nav-submenu');
  const isOpen = item.classList.contains('open');
  // Close all
  document.querySelectorAll('.nav-item.open').forEach(i => {
    i.classList.remove('open');
    i.querySelector('.nav-submenu')?.classList.remove('open');
  });
  if (!isOpen) {
    item.classList.add('open');
    submenu?.classList.add('open');
  }
}

// Auto-dismiss flash
setTimeout(() => {
  const flash = document.getElementById('flashAlert');
  if (flash) {
    flash.style.opacity = '0';
    flash.style.transition = 'opacity 0.4s';
    setTimeout(() => flash.remove(), 400);
  }
}, 5000);

// Modal helpers
function openModal(id) {
  document.getElementById(id).classList.add('active');
  document.body.style.overflow = 'hidden';
}
function closeModal(id) {
  document.getElementById(id).classList.remove('active');
  document.body.style.overflow = '';
}
document.querySelectorAll('.modal-overlay').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) closeModal(m.id); });
});

// Tab switching
function switchTab(tabId, groupId) {
  const group = document.getElementById(groupId) || document;
  group.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  group.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.getElementById(tabId + '-btn')?.classList.add('active');
  document.getElementById(tabId)?.classList.add('active');
}

// Confirm delete helper
function confirmAction(message, callback) {
  if (confirm(message || 'Are you sure?')) callback();
}

// Format numbers
function formatNum(n) {
  return new Intl.NumberFormat('en-KE').format(n);
}
function formatCurrency(n) {
  return 'KES ' + new Intl.NumberFormat('en-KE', {minimumFractionDigits:2, maximumFractionDigits:2}).format(n);
}

// AJAX helper
async function apiPost(url, data) {
  const res = await fetch(url, {
    method: 'POST',
    headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
    body: JSON.stringify(data)
  });
  return res.json();
}

// Print page
function printPage() { window.print(); }

// Search filter for tables
function filterTable(inputId, tableId) {
  const filter = document.getElementById(inputId).value.toUpperCase();
  const rows = document.querySelectorAll('#' + tableId + ' tbody tr');
  rows.forEach(row => {
    const text = row.textContent.toUpperCase();
    row.style.display = text.includes(filter) ? '' : 'none';
  });
}

// Collapsible sidebar on desktop
const savedState = localStorage.getItem('sidebarState');
if (savedState === 'collapsed' && window.innerWidth > 768) {
  document.getElementById('sidebar').classList.add('collapsed');
  document.querySelector('.main-content')?.classList.add('sidebar-collapsed');
}
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
  const collapsed = document.getElementById('sidebar').classList.contains('collapsed');
  localStorage.setItem('sidebarState', collapsed ? 'open' : 'collapsed');
});
</script>

<!-- Page-specific scripts loaded here by individual pages -->
<?php if (isset($extraScripts)): ?>
  <?php foreach ($extraScripts as $script): ?>
    <script src="<?= $script ?>"></script>
  <?php endforeach; ?>
<?php endif; ?>

</body>
</html>
