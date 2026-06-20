jQuery(document).ready(function($) {
    var delay = typeof window.bmpPopupDelay !== 'undefined' ? window.bmpPopupDelay : 2;
    setTimeout(function() {
        $('#bmp-popup-banner').attr('aria-hidden', 'false').fadeIn();
    }, delay * 1000);

    $(document).on('click', '.bmp-popup-close', function() {
        $('#bmp-popup-banner').attr('aria-hidden', 'true').fadeOut();
    });
    // Fermer en cliquant en dehors du popup
    $(document).on('click', '#bmp-popup-banner', function(e) {
        if ($(e.target).is('#bmp-popup-banner')) {
            $('#bmp-popup-banner').attr('aria-hidden', 'true').fadeOut();
        }
    });
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('#bmp-popup-banner').attr('aria-hidden', 'true').fadeOut();
        }
    });
});
