jQuery(function($) {
    var selectedImageUrl = $('#bmp_popup_image_preview img').first().attr('src') || '';

    function escapeAttr(value) {
        return String(value || '').replace(/[&<>"']/g, function(c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];
        });
    }

    function currentType() {
        return $('input[name="bmp_popup_type"]:checked').val() || 'image';
    }

    function safePreviewUrl(value) {
        if (!value) return '';
        try {
            var url = new URL(value, window.location.origin);
            return (url.protocol === 'http:' || url.protocol === 'https:') ? url.href : '';
        } catch(e) { return ''; }
    }

    // Type toggle
    function updateTypeFields() {
        var type = currentType();
        $('#bmp-popup-image-fields').toggle(type === 'image');
        $('#bmp-popup-html-fields').toggle(type === 'html');
        $('#bmp-popup-post-fields').toggle(type === 'post');
        // CTA button color only relevant for post type (card mode has a button)
        $('#bmp-popup-btn-color-row').toggle(type === 'post');
        updatePreview();
    }

    // Position → show/hide size section
    function updatePositionFields() {
        var pos = $('input[name="bmp_popup_position"]:checked').val() || 'center';
        if (pos === 'center') {
            $('#bmp-popup-size-section').show();
        } else {
            $('#bmp-popup-size-section').hide();
        }
    }

    // Trigger → show/hide delay/scroll rows
    function updateTriggerFields() {
        var trigger = $('#bmp_popup_trigger').val();
        $('#bmp-popup-delay-row').toggle(trigger === 'delay');
        $('#bmp-popup-scroll-row').toggle(trigger === 'scroll');
    }

    // Display on → show/hide specific fields
    function updateDisplayFields() {
        var display = $('#bmp_popup_display_on').val();
        $('#bmp-popup-specific-post-row').toggle(display === 'specific_post');
        $('#bmp-popup-specific-page-row').toggle(display === 'specific_page');
        $('#bmp-popup-category-row').toggle(display === 'category');
    }

    // Preview
    function imagePreviewHtml() {
        var imageId = $('#bmp_popup_image_id').val();
        var imageUrl = selectedImageUrl || $('#bmp_popup_image_preview img').first().attr('src') || '';
        var link = safePreviewUrl($('#bmp_popup_image_link').val());
        if (!imageId || !imageUrl) return '<div class="bmp-preview-empty">Aucune image sélectionnée.</div>';
        var image = '<img class="bmp-preview-image" src="' + escapeAttr(imageUrl) + '" alt="">';
        if (link) return '<a href="' + escapeAttr(link) + '" target="_blank" rel="noopener noreferrer">' + image + '</a>';
        return image;
    }

    function htmlPreviewHtml() {
        var html = $('#bmp_popup_html').val();
        if (!(html || '').trim()) return '<div class="bmp-preview-empty">Aucun code HTML.</div>';
        return '<iframe class="bmp-preview-iframe" sandbox="allow-scripts allow-popups allow-forms" srcdoc="' + escapeAttr(html) + '"></iframe>';
    }

    function updatePreview() {
        $('#bmp-popup-live-preview').html(currentType() === 'html' ? htmlPreviewHtml() : imagePreviewHtml());
    }

    function removeImage() {
        selectedImageUrl = '';
        $('#bmp_popup_image_id').val('');
        $('#bmp_popup_image_preview').empty();
        updatePreview();
    }

    function renderSelectedImage(attachment) {
        var imageUrl = attachment.url;
        if (attachment.sizes && attachment.sizes.medium) imageUrl = attachment.sizes.medium.url;
        else if (attachment.sizes && attachment.sizes.thumbnail) imageUrl = attachment.sizes.thumbnail.url;
        selectedImageUrl = imageUrl;
        $('#bmp_popup_image_id').val(attachment.id);
        $('#bmp_popup_image_preview').html(
            '<img src="' + escapeAttr(imageUrl) + '" style="max-width:150px; height:auto; border:1px solid #ddd; margin:5px 0;" alt="">' +
            '<br><button type="button" class="button button-small" id="bmp_popup_remove_image">Supprimer</button>'
        );
        updatePreview();
    }

    // Events
    $('input[name="bmp_popup_type"]').on('change', updateTypeFields);
    $('input[name="bmp_popup_position"]').on('change', updatePositionFields);
    $('#bmp_popup_trigger').on('change', updateTriggerFields);
    $('#bmp_popup_display_on').on('change', updateDisplayFields);
    $('#bmp_popup_image_link, #bmp_popup_html').on('input', updatePreview);
    $(document).on('click', '#bmp_popup_remove_image', removeImage);

    // Preview mode toggle
    $('.bmp-popup-preview-mode').on('click', function() {
        var mode = $(this).data('mode') === 'mobile' ? 'mobile' : 'desktop';
        $('.bmp-popup-preview-mode').removeClass('is-active');
        $(this).addClass('is-active');
        $('#bmp-popup-live-preview')
            .toggleClass('bmp-preview-mobile', mode === 'mobile')
            .toggleClass('bmp-preview-desktop', mode !== 'mobile');
    });

    // Media picker
    $('#bmp_popup_pick_image').on('click', function(e) {
        e.preventDefault();
        if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
            alert('La bibliothèque média WordPress n\'est pas disponible');
            return;
        }
        var frame = wp.media({
            title: 'Choisir une image',
            button: { text: 'Utiliser cette image' },
            multiple: false
        });
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            renderSelectedImage(attachment);
        });
        frame.open();
    });

    // Add remove button if image exists but no remove button
    if ($('#bmp_popup_image_id').val() && !$('#bmp_popup_remove_image').length) {
        $('#bmp_popup_image_preview').append('<br><button type="button" class="button button-small" id="bmp_popup_remove_image">Supprimer</button>');
    }

    // Init
    // Post display mode toggle
    $('input[name="bmp_popup_post_display"]').on('change', function() {
        $('#bmp-popup-btn-row').toggle($(this).val() === 'card');
    });

    // Post search filter
    $('#bmp-post-search').on('input', function() {
        var search = $(this).val().toLowerCase();
        var $select = $('#bmp_popup_post_id');
        $select.find('option').each(function() {
            var text = $(this).text().toLowerCase();
            $(this).toggle(!search || text.indexOf(search) !== -1);
        });
        // Show optgroups that have visible options
        $select.find('optgroup').each(function() {
            var hasVisible = $(this).find('option:visible').length > 0;
            $(this).toggle(hasVisible);
        });
    });

    // Border radius range label
    $('input[name="bmp_popup_radius"]').on('input', function() {
        $(this).next('span').text($(this).val() + 'px');
    });

    updateTypeFields();
    updatePositionFields();
    updateTriggerFields();
    updateDisplayFields();
});
