// ============================================================
// assets/js/main.js
// Global JavaScript — runs on every page
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

  // ── Save and apply the selected color theme ─────────────────
  const themeToggle = document.getElementById('theme-toggle');
  if (themeToggle) {
    const themeStatus = document.getElementById('theme-status');
    const themeLabel = themeToggle.querySelector('span');
    const themeIcon = themeToggle.querySelector('i');

    function updateThemeControl(theme) {
      const lightMode = theme === 'light';
      themeToggle.setAttribute('aria-pressed', String(lightMode));
      themeToggle.setAttribute(
        'aria-label',
        lightMode ? 'Switch to dark mode' : 'Switch to light mode'
      );
      if (themeLabel) {
        themeLabel.textContent = lightMode ? 'Switch to dark mode' : 'Switch to light mode';
      }
      if (themeIcon) {
        themeIcon.className = `bi ${lightMode ? 'bi-moon' : 'bi-sun'} me-1`;
      }
      if (themeStatus) {
        themeStatus.textContent = `${lightMode ? 'Light' : 'Dark'} mode is currently active.`;
      }
    }

    updateThemeControl(document.documentElement.dataset.theme);
    themeToggle.addEventListener('click', () => {
      const theme = document.documentElement.dataset.theme === 'light' ? 'dark' : 'light';
      localStorage.setItem('tailorshop-theme', theme);
      document.documentElement.dataset.theme = theme;
      updateThemeControl(theme);
    });
  }

  // ── Expand or collapse sidebar navigation sections ──────────
  document.querySelectorAll('[data-nav-toggle]').forEach(toggle => {
    toggle.addEventListener('click', () => {
      const expanded = toggle.getAttribute('aria-expanded') === 'true';
      const section = document.getElementById(toggle.getAttribute('aria-controls'));
      if (!section) return;

      toggle.setAttribute('aria-expanded', String(!expanded));
      section.hidden = expanded;
    });
  });

  // ── Collapse or expand the sidebar navigation ──────────────
  const sidebarToggle = document.getElementById('sidebar-toggle');
  if (sidebarToggle) {
    const sidebarLinks = document.querySelectorAll('.sidebar-nav a');

    sidebarToggle.addEventListener('click', () => {
      const collapsed = document.body.classList.toggle('sidebar-collapsed');
      sidebarToggle.setAttribute('aria-expanded', String(!collapsed));
      sidebarToggle.setAttribute(
        'aria-label',
        collapsed ? 'Expand navigation' : 'Collapse navigation'
      );
      sidebarLinks.forEach(link => {
        if (collapsed) {
          link.title = link.textContent.trim();
        } else {
          link.removeAttribute('title');
        }
      });
    });
  }

  // ── Auto-dismiss flash alerts after 4 seconds ─────────────
  document.querySelectorAll('.alert-dismissible').forEach(alert => {
    setTimeout(() => {
      const instance = bootstrap.Alert.getOrCreateInstance(alert);
      if (instance) instance.close();
    }, 4000);
  });

  // ── Confirm before form submission on delete actions ───────
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', function (e) {
      const message = this.dataset.confirm || 'Are you sure?';
      if (!confirm(message)) {
        e.preventDefault();
      }
    });
  });

  // ── Toggle password visibility ─────────────────────────────
  document.querySelectorAll('[data-toggle-password]').forEach(btn => {
    btn.addEventListener('click', function () {
      const targetId = this.dataset.togglePassword;
      const input    = document.getElementById(targetId);
      const icon     = this.querySelector('i');
      if (!input) return;
      if (input.type === 'password') {
        input.type = 'text';
        if (icon) {
          icon.classList.replace('bi-eye', 'bi-eye-slash');
        }
      } else {
        input.type = 'password';
        if (icon) {
          icon.classList.replace('bi-eye-slash', 'bi-eye');
        }
      }
    });
  });

  // ── Live search filter for tables ─────────────────────────
  const searchInput = document.getElementById('live-search');
  if (searchInput) {
    searchInput.addEventListener('input', function () {
      const query = this.value.toLowerCase();
      const rows  = document.querySelectorAll('[data-search-row]');
      rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
      });
    });
  }

  // ── Payment method "Other" field toggle ────────────────────
  const paymentSelect = document.getElementById('payment_method');
  const otherField    = document.getElementById('payment-other-field');
  if (paymentSelect && otherField) {
    function toggleOther() {
      otherField.style.display =
        paymentSelect.value === 'Other' ? 'block' : 'none';
    }
    paymentSelect.addEventListener('change', toggleOther);
    toggleOther(); // run on load in case of form re-render
  }

  // ── Topbar: show current date ──────────────────────────────
  const dateBadge = document.getElementById('current-date');
  if (dateBadge) {
    const now = new Date();
    dateBadge.textContent = now.toLocaleDateString('en-PH', {
      weekday: 'long', year: 'numeric',
      month: 'long',   day: 'numeric',
    });
  }

});

// ── Utility: format currency as PHP peso ──────────────────────
function formatPeso(amount) {
  return '₱' + parseFloat(amount).toLocaleString('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

// ── Utility: print the rental agreement ───────────────────────
function printAgreement() {
  window.print();
}