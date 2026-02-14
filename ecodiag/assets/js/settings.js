/**
 * EcoDiag Settings JavaScript
 */
(function () {
    'use strict';

    // Range slider value display
    document.querySelectorAll('.ecodiag-range').forEach(function (range) {
        var display = range.nextElementSibling;
        if (display && display.classList.contains('ecodiag-range-val')) {
            range.addEventListener('input', function () {
                display.textContent = this.value + '%';
            });
        }
    });
})();
