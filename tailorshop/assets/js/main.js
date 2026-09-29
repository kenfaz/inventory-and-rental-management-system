// ============================================================
// assets/js/main.js
// Global JavaScript — runs on every page
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

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