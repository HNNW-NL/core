import { config } from '@fortawesome/fontawesome-svg-core';
import '@fortawesome/fontawesome-svg-core/styles.css';
config.autoAddCss = false;

document.addEventListener('DOMContentLoaded', function() {

    // ==========================================
    // 1. WERKERVARING TOOGLE (HUIDIGE BAAN)
    // ==========================================
    const currentCheckbox = document.getElementById('exp-is-current');
    const endDateInput = document.getElementById('exp-end-date');

    if (currentCheckbox && endDateInput) {
        currentCheckbox.addEventListener('change', function() {
            endDateInput.disabled = this.checked;
            if (this.checked) {
                endDateInput.value = '';
            }
        });

        if (currentCheckbox.checked) {
            endDateInput.disabled = true;
        }
    }

    // ==========================================
    // 2. TIDYCAL GEBLOKKEERDE DATUMS BEHEREN
    // ==========================================
    const picker = document.getElementById('picker_blocked_date');
    const addBtn = document.getElementById('btn_add_blocked');
    const dummyZone = document.getElementById('dummy_add_zone');
    const badgeContainer = document.getElementById('blocked_badges');
    const csvHidden = document.getElementById('blocked_dates_csv');

    let blockedDates = [];

    function addDate(val) {
        if (!val || blockedDates.includes(val)) return;
        blockedDates.push(val);
        renderBadges();
    }

    if (addBtn && picker) {
        addBtn.addEventListener('click', function(e) {
            e.preventDefault(); // Voorkomt eventuele formulier submits
            addDate(picker.value);
            picker.value = '';
        });
    }

    if (dummyZone && picker) {
        dummyZone.addEventListener('click', function() {
            if (picker.showPicker) {
                picker.showPicker(); // Opent de kalender-popup direct in moderne browsers
            } else {
                picker.focus();
            }
        });
    }

    function renderBadges() {
        if (!badgeContainer || !csvHidden) return;

        badgeContainer.innerHTML = '';
        blockedDates.forEach((date, i) => {
            const badge = document.createElement('span');
            badge.className = 'blocked-badge'; // Gebruikt nu puur de styling uit style.css
            badge.innerHTML = `${date} <span data-idx="${i}">&times;</span>`;

            badge.querySelector('span').addEventListener('click', function() {
                blockedDates.splice(i, 1);
                renderBadges();
            });
            badgeContainer.appendChild(badge);
        });

        // Update het verborgen CSV inputveld voor Symfony
        csvHidden.value = blockedDates.join(',');
    }
});
