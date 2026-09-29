// ============================================================
// assets/js/materials-selector.js
// Dynamic add/remove rows for the materials used form
// Used by modules/tailoring/materials.php
// ============================================================

document.addEventListener('DOMContentLoaded', () => {
    const container   = document.getElementById('materials-container');
    const addRowBtn   = document.getElementById('add-row');

    if (!container || !addRowBtn) return;

    // ── Add a new row ────────────────────────────────────────
    addRowBtn.addEventListener('click', () => {
        const firstRow  = container.querySelector('.material-row');
        const newRow    = firstRow.cloneNode(true);

        // Reset values in cloned row
        newRow.querySelector('.item-select').value = '';
        newRow.querySelector('.qty-input').value   = '';

        // Enable remove button on all rows
        newRow.querySelector('.remove-row').disabled = false;
        container.appendChild(newRow);

        // Bind remove button on new row
        bindRemoveButtons();
        updateRemoveButtons();
    });

    // ── Remove a row ─────────────────────────────────────────
    function bindRemoveButtons() {
        container.querySelectorAll('.remove-row').forEach(btn => {
            btn.replaceWith(btn.cloneNode(true)); // Remove old listeners
        });
        container.querySelectorAll('.remove-row').forEach(btn => {
            btn.addEventListener('click', () => {
                const row = btn.closest('.material-row');
                if (container.querySelectorAll('.material-row').length > 1) {
                    row.remove();
                    updateRemoveButtons();
                }
            });
        });
    }

    // Keep first row's remove button disabled when it's the only row
    function updateRemoveButtons() {
        const rows = container.querySelectorAll('.material-row');
        rows.forEach((row, idx) => {
            const btn = row.querySelector('.remove-row');
            if (btn) btn.disabled = rows.length === 1;
        });
    }

    bindRemoveButtons();
    updateRemoveButtons();

    // ── Show stock info on item select ───────────────────────
    container.addEventListener('change', (e) => {
        if (!e.target.classList.contains('item-select')) return;

        const select  = e.target;
        const opt     = select.options[select.selectedIndex];
        const qtyInput = select.closest('.material-row').querySelector('.qty-input');

        if (opt && opt.dataset.qty) {
            qtyInput.max         = opt.dataset.qty;
            qtyInput.placeholder = '0 – ' + opt.dataset.qty;
        } else {
            qtyInput.max         = '';
            qtyInput.placeholder = '0';
        }
    });

    // ── Validate quantity does not exceed stock ───────────────
    container.addEventListener('input', (e) => {
        if (!e.target.classList.contains('qty-input')) return;

        const qtyInput = e.target;
        const row      = qtyInput.closest('.material-row');
        const select   = row.querySelector('.item-select');
        const opt      = select.options[select.selectedIndex];
        const maxQty   = opt && opt.dataset.qty ? parseFloat(opt.dataset.qty) : null;
        const entered  = parseFloat(qtyInput.value);

        if (maxQty !== null && entered > maxQty) {
            qtyInput.setCustomValidity(
                'Cannot exceed available stock (' + maxQty + ').'
            );
            qtyInput.classList.add('is-invalid');
        } else {
            qtyInput.setCustomValidity('');
            qtyInput.classList.remove('is-invalid');
        }
    });
});