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

  // ── Live search filter and match highlighting for tables ───
  const escapeRegExp = value => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

  document.querySelectorAll('input[name="q"], input[id="live-search"], input[data-live-search]').forEach(searchInput => {
    const pageBody = searchInput.closest('.page-body');
    const table = pageBody?.querySelector('.table-responsive .table, table.table');
    if (!table) return;

    const rows = Array.from(table.querySelectorAll('tbody tr'))
      .filter(row => row.children.length > 1 && !row.hasAttribute('data-no-search'));
    if (!rows.length) return;

    const restoreText = element => {
      element.querySelectorAll('mark[data-live-search-match]').forEach(mark => {
        mark.replaceWith(document.createTextNode(mark.textContent));
      });
      element.normalize();
    };

    const highlightText = (element, query) => {
      restoreText(element);
      if (!query) return;

      const pattern = new RegExp(escapeRegExp(query), 'gi');
      const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT);
      const textNodes = [];
      while (walker.nextNode()) textNodes.push(walker.currentNode);

      textNodes.forEach(textNode => {
        const value = textNode.nodeValue;
        if (!value.trim() || !pattern.test(value)) return;
        pattern.lastIndex = 0;

        const fragment = document.createDocumentFragment();
        let lastIndex = 0;
        value.replace(pattern, (match, offset) => {
          if (offset > lastIndex) {
            fragment.appendChild(document.createTextNode(value.slice(lastIndex, offset)));
          }
          const mark = document.createElement('mark');
          mark.dataset.liveSearchMatch = 'true';
          mark.textContent = match;
          fragment.appendChild(mark);
          lastIndex = offset + match.length;
          return match;
        });
        if (lastIndex < value.length) {
          fragment.appendChild(document.createTextNode(value.slice(lastIndex)));
        }
        textNode.replaceWith(fragment);
      });
    };

    const search = () => {
      const query = searchInput.value.trim().toLowerCase();
      rows.forEach(row => {
        const matches = !query || row.textContent.toLowerCase().includes(query);
        row.style.display = matches ? '' : 'none';
        highlightText(row, query);
      });
    };

    searchInput.addEventListener('input', search);
    searchInput.closest('form')?.addEventListener('submit', event => event.preventDefault());
    search();
  });

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