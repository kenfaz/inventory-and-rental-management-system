// ============================================================
// assets/js/conflict-checker.js
// Calls check-conflict.php API when garment or dates change
// Shows inline alert if garment is unavailable for selected dates
// ============================================================

document.addEventListener('DOMContentLoaded', () => {
    const garmentSelect  = document.getElementById('garment-select');
    const pickupInput    = document.getElementById('pickup-date');
    const returnInput    = document.getElementById('return-date');
    const conflictAlert  = document.getElementById('conflict-alert');
    const conflictMsg    = document.getElementById('conflict-msg');
    const submitBtn      = document.querySelector('#reservation-form [type="submit"]');

    if (!garmentSelect || !pickupInput || !returnInput) return;

    let checkTimeout = null;

    async function checkConflict() {
        const garmentId  = garmentSelect.value;
        const pickupDate = pickupInput.value;
        const returnDate = returnInput.value;

        if (!garmentId || !pickupDate || !returnDate) {
            hideAlert();
            return;
        }

        if (returnDate <= pickupDate) {
            hideAlert();
            return;
        }

        try {
            const url = `/tailorshop/api/check-conflict.php?garment_id=${garmentId}`
                      + `&pickup_date=${pickupDate}&return_date=${returnDate}`;
            const res  = await fetch(url);
            const data = await res.json();

            if (data.conflict) {
                showAlert(data.message || 'This garment is not available for the selected dates.');
                if (submitBtn) submitBtn.disabled = true;
            } else {
                hideAlert();
                if (submitBtn) submitBtn.disabled = false;
            }
        } catch (e) {
            // Silently fail — server validation will catch it
            hideAlert();
        }
    }

    function showAlert(message) {
        if (conflictAlert && conflictMsg) {
            conflictMsg.textContent = message;
            conflictAlert.classList.remove('d-none');
        }
    }

    function hideAlert() {
        if (conflictAlert) {
            conflictAlert.classList.add('d-none');
        }
    }

    function scheduleCheck() {
        clearTimeout(checkTimeout);
        checkTimeout = setTimeout(checkConflict, 400);
    }

    garmentSelect.addEventListener('change', scheduleCheck);
    pickupInput.addEventListener('change', scheduleCheck);
    returnInput.addEventListener('change', scheduleCheck);

    // Also update return date minimum when pickup changes
    pickupInput.addEventListener('change', () => {
        if (pickupInput.value) {
            const next = new Date(pickupInput.value);
            next.setDate(next.getDate() + 1);
            returnInput.min = next.toISOString().split('T')[0];
            if (returnInput.value && returnInput.value <= pickupInput.value) {
                returnInput.value = '';
            }
        }
    });
});