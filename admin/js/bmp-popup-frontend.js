(function() {
    'use strict';

    var config = window.bmpPopupConfig;
    if (!config || !config.id) return;

    var popupEl = document.getElementById('bmp-popup-' + config.id);
    if (!popupEl) return;

    var overlayEl = document.getElementById('bmp-popup-overlay');
    var storageKey = 'bmp_popup_' + config.id;

    /* ── Frequency check ── */
    function shouldShow() {
        var freq = config.frequency;
        if (freq === 'always') return true;

        if (freq === 'session') {
            try {
                return !sessionStorage.getItem(storageKey);
            } catch(e) { return true; }
        }

        try {
            var stored = localStorage.getItem(storageKey);
            if (!stored) return true;

            if (freq === 'once') return false;

            var ts = parseInt(stored, 10);
            var now = Date.now();
            var diff = now - ts;

            if (freq === 'day') return diff > 86400000;       // 24h
            if (freq === 'week') return diff > 604800000;     // 7 days

            return true;
        } catch(e) { return true; }
    }

    function markShown() {
        var freq = config.frequency;
        try {
            if (freq === 'session') {
                sessionStorage.setItem(storageKey, '1');
            } else if (freq !== 'always') {
                localStorage.setItem(storageKey, String(Date.now()));
            }
        } catch(e) {}
    }

    /* ── Show / Hide ── */
    function showPopup() {
        popupEl.style.display = '';
        popupEl.setAttribute('aria-hidden', 'false');

        if (overlayEl && config.position === 'center') {
            overlayEl.style.display = 'block';
            // Force reflow for transition
            void overlayEl.offsetWidth;
            overlayEl.classList.add('is-visible');
        }

        // Force reflow for transition
        void popupEl.offsetWidth;
        popupEl.classList.add('is-visible');

        markShown();

        // Auto-close after X seconds (0 = disabled)
        var autoClose = config.autoClose || 0;
        if (autoClose > 0) {
            setTimeout(hidePopup, autoClose * 1000);
        }

        // Trap focus
        var closeBtn = popupEl.querySelector('.bmp-popup-close');
        if (closeBtn) closeBtn.focus();
    }

    function hidePopup() {
        popupEl.classList.remove('is-visible');
        popupEl.setAttribute('aria-hidden', 'true');

        if (overlayEl) {
            overlayEl.classList.remove('is-visible');
        }

        // Wait for animation to complete, then hide
        setTimeout(function() {
            popupEl.style.display = 'none';
            if (overlayEl) overlayEl.style.display = 'none';
        }, 400);
    }

    /* ── Close handlers ── */
    // Close button
    popupEl.addEventListener('click', function(e) {
        if (e.target.classList.contains('bmp-popup-close') || e.target.closest('.bmp-popup-close')) {
            hidePopup();
        }
    });

    // Click overlay (center only)
    if (overlayEl) {
        overlayEl.addEventListener('click', hidePopup);
    }

    // Click outside for center position
    if (config.position === 'center') {
        popupEl.addEventListener('click', function(e) {
            if (e.target === popupEl) {
                hidePopup();
            }
        });
    }

    // Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && popupEl.classList.contains('is-visible')) {
            hidePopup();
        }
    });

    /* ── Trigger logic ── */
    if (!shouldShow()) return;

    var trigger = config.trigger;

    if (trigger === 'immediate') {
        showPopup();

    } else if (trigger === 'delay') {
        var delay = Math.max(0, config.delay || 5);
        setTimeout(showPopup, delay * 1000);

    } else if (trigger === 'scroll') {
        var scrollPct = Math.max(10, Math.min(100, config.scrollPct || 50));
        var scrollFired = false;

        function checkScroll() {
            if (scrollFired) return;
            var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            var docHeight = document.documentElement.scrollHeight - window.innerHeight;
            if (docHeight <= 0) return;
            var currentPct = (scrollTop / docHeight) * 100;
            if (currentPct >= scrollPct) {
                scrollFired = true;
                window.removeEventListener('scroll', checkScroll);
                showPopup();
            }
        }
        window.addEventListener('scroll', checkScroll, { passive: true });

    } else if (trigger === 'exit_intent') {
        var exitFired = false;

        function checkExit(e) {
            if (exitFired) return;
            // Only trigger when mouse leaves through the top of the viewport
            if (e.clientY <= 0) {
                exitFired = true;
                document.removeEventListener('mouseleave', checkExit);
                showPopup();
            }
        }
        document.addEventListener('mouseleave', checkExit);
    }

    // ── Coupon copy button (Visual template) ──
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.bmp-visual__coupon-copy');
        if (!btn) return;
        var code = btn.getAttribute('data-code');
        if (!code) return;
        navigator.clipboard.writeText(code).then(function() {
            btn.textContent = 'Copied!';
            btn.classList.add('is-copied');
            setTimeout(function() {
                btn.textContent = 'Copy';
                btn.classList.remove('is-copied');
            }, 2000);
        });
    });
})();
