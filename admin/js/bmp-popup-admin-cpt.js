jQuery(function($) {
    var __ = wp.i18n.__;
    var selectedImageUrl = $('#bmp_popup_image_preview img').first().attr('src') || '';
    var selectedVisualImageUrl = $('#bmp_popup_visual_image_preview img').first().attr('src') || '';

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
        $('#bmp-popup-visual-fields').toggle(type === 'visual');
        // CTA button color for post and visual
        $('#bmp-popup-btn-color-row').toggle(type === 'post' || type === 'visual');
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

    // ── Preview functions ──

    function imagePreviewHtml() {
        var imageId = $('#bmp_popup_image_id').val();
        var imageUrl = selectedImageUrl || $('#bmp_popup_image_preview img').first().attr('src') || '';
        var link = safePreviewUrl($('#bmp_popup_image_link').val());
        if (!imageId || !imageUrl) return '<div class="bmp-preview-empty">' + __( 'No image selected.', 'banner-manager-pro' ) + '</div>';
        var image = '<img class="bmp-preview-image" src="' + escapeAttr(imageUrl) + '" alt="">';
        if (link) return '<a href="' + escapeAttr(link) + '" target="_blank" rel="noopener noreferrer">' + image + '</a>';
        return image;
    }

    function htmlPreviewHtml() {
        var html = $('#bmp_popup_html').val();
        if (!(html || '').trim()) return '<div class="bmp-preview-empty">' + __( 'No HTML code.', 'banner-manager-pro' ) + '</div>';
        return '<iframe class="bmp-preview-iframe" sandbox="allow-scripts allow-popups allow-forms" srcdoc="' + escapeAttr(html) + '"></iframe>';
    }

    function visualPreviewHtml() {
        var tpl = $('#bmp_popup_visual_tpl').val();
        if (!tpl) return '<div class="bmp-preview-empty">' + __( 'Choose a template.', 'banner-manager-pro' ) + '</div>';

        var heading = escapeAttr($('#bmp_popup_visual_heading').val()) || 'Heading';
        var body = escapeAttr($('#bmp_popup_visual_body').val()) || 'Body text...';
        var ctaText = escapeAttr($('#bmp_popup_visual_cta_text').val()) || 'Learn More';
        var imgUrl = selectedVisualImageUrl || '';
        var tplClass = 'bmp-visual--' + tpl.replace(/_/g, '-');

        var html = '<div class="bmp-visual ' + tplClass + '" style="font-size:11px;overflow:hidden;">';

        // Image block (for templates that use it)
        var imgBlock = '';
        if (imgUrl && ['flash_sale', 'lead_magnet', 'product_showcase', 'testimonial'].indexOf(tpl) !== -1) {
            imgBlock = '<div style="max-height:80px;overflow:hidden;"><img src="' + escapeAttr(imgUrl) + '" style="width:100%;height:80px;object-fit:cover;display:block;" alt=""></div>';
        }

        // Content block
        var contentBlock = '<div style="padding:10px 12px;">' +
            '<div style="font-size:12px;font-weight:800;margin-bottom:4px;">' + heading + '</div>' +
            '<div style="font-size:10px;opacity:0.7;margin-bottom:8px;line-height:1.4;">' + body.substring(0, 80) + '</div>' +
            '<span style="display:inline-block;background:#111827;color:#fff;font-size:9px;font-weight:600;padding:4px 10px;border-radius:4px;">' + ctaText + '</span>' +
            '</div>';

        // Template-specific previews
        if (tpl === 'flash_sale' || tpl === 'product_showcase') {
            html += imgBlock + contentBlock;
        } else if (tpl === 'lead_magnet') {
            html += '<div style="display:flex;">' +
                '<div style="flex:0 0 35%;max-height:120px;overflow:hidden;">' + (imgUrl ? '<img src="' + escapeAttr(imgUrl) + '" style="width:100%;height:120px;object-fit:cover;" alt="">' : '<div style="background:#d1d5db;height:120px;"></div>') + '</div>' +
                '<div style="flex:1;">' + contentBlock + '</div>' +
                '</div>';
        } else if (tpl === 'newsletter') {
            html += '<div style="text-align:center;padding:12px;">' +
                '<div style="font-size:20px;margin-bottom:6px;">&#9993;</div>' +
                '<div style="font-size:12px;font-weight:800;margin-bottom:4px;">' + heading + '</div>' +
                '<div style="font-size:10px;opacity:0.7;margin-bottom:8px;">' + body.substring(0, 60) + '</div>' +
                '<span style="display:inline-block;background:#111827;color:#fff;font-size:9px;font-weight:600;padding:4px 10px;border-radius:4px;">' + ctaText + '</span>' +
                '</div>';
        } else if (tpl === 'video_spotlight') {
            var videoUrl = $('#bmp_popup_visual_video_url').val() || '';
            html += '<div style="background:#000;height:70px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;">&#9654;</div>' + contentBlock;
        } else if (tpl === 'coupon_code') {
            var code = escapeAttr($('#bmp_popup_visual_coupon').val()) || 'CODE';
            html += '<div style="text-align:center;padding:12px;">' +
                '<div style="font-size:12px;font-weight:800;margin-bottom:6px;">' + heading + '</div>' +
                '<div style="display:inline-flex;border:2px dashed #d1d5db;border-radius:6px;overflow:hidden;margin-bottom:8px;">' +
                '<code style="font-size:13px;font-weight:800;padding:6px 10px;letter-spacing:0.1em;">' + code + '</code>' +
                '<span style="background:#111827;color:#fff;padding:6px 8px;font-size:9px;">Copy</span></div>' +
                '<br><span style="display:inline-block;background:#111827;color:#fff;font-size:9px;font-weight:600;padding:4px 10px;border-radius:4px;">' + ctaText + '</span>' +
                '</div>';
        } else if (tpl === 'announcement') {
            html += '<div style="text-align:center;padding:16px 12px;">' +
                '<div style="font-size:14px;font-weight:800;margin-bottom:6px;">' + heading + '</div>' +
                '<div style="font-size:10px;opacity:0.7;margin-bottom:10px;line-height:1.4;">' + body.substring(0, 100) + '</div>' +
                '<span style="display:inline-block;background:#111827;color:#fff;font-size:9px;font-weight:600;padding:4px 10px;border-radius:4px;">' + ctaText + '</span>' +
                '</div>';
        } else if (tpl === 'testimonial') {
            var author = escapeAttr($('#bmp_popup_visual_author').val()) || 'Author';
            var role = escapeAttr($('#bmp_popup_visual_role').val()) || '';
            html += '<div style="text-align:center;padding:14px 12px;">' +
                '<div style="font-size:18px;opacity:0.15;margin-bottom:4px;">&ldquo;</div>' +
                '<div style="font-size:11px;font-style:italic;opacity:0.8;margin-bottom:10px;line-height:1.5;">' + body.substring(0, 80) + '</div>' +
                '<div style="display:flex;align-items:center;justify-content:center;gap:6px;margin-bottom:8px;">' +
                (imgUrl ? '<img src="' + escapeAttr(imgUrl) + '" style="width:24px;height:24px;border-radius:50%;object-fit:cover;" alt="">' : '') +
                '<div style="text-align:left;font-size:10px;"><strong>' + author + '</strong>' + (role ? '<br><span style="opacity:0.6;">' + role + '</span>' : '') + '</div>' +
                '</div>' +
                '<span style="display:inline-block;background:#111827;color:#fff;font-size:9px;font-weight:600;padding:4px 10px;border-radius:4px;">' + ctaText + '</span>' +
                '</div>';
        } else {
            html += contentBlock;
        }

        html += '</div>';
        return html;
    }

    function updatePreview() {
        var type = currentType();
        var html;
        if (type === 'html') html = htmlPreviewHtml();
        else if (type === 'visual') html = visualPreviewHtml();
        else html = imagePreviewHtml();
        $('#bmp-popup-live-preview').html(html);
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
            '<br><button type="button" class="button button-small" id="bmp_popup_remove_image">' + __( 'Remove', 'banner-manager-pro' ) + '</button>'
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

    // Media picker (popup image type)
    $('#bmp_popup_pick_image').on('click', function(e) {
        e.preventDefault();
        if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
            alert( __( 'WordPress media library is not available.', 'banner-manager-pro' ) );
            return;
        }
        var frame = wp.media({
            title: __( 'Choose an image', 'banner-manager-pro' ),
            button: { text: __( 'Use this image', 'banner-manager-pro' ) },
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
        $('#bmp_popup_image_preview').append('<br><button type="button" class="button button-small" id="bmp_popup_remove_image">' + __( 'Remove', 'banner-manager-pro' ) + '</button>');
    }

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
        $select.find('optgroup').each(function() {
            var hasVisible = $(this).find('option:visible').length > 0;
            $(this).toggle(hasVisible);
        });
    });

    // Border radius range label
    $('input[name="bmp_popup_radius"]').on('input', function() {
        $(this).next('span').text($(this).val() + 'px');
    });

    // Close button style — show/hide custom color pickers
    $('input[name="bmp_popup_close_color"]').on('change', function() {
        $('#bmp-close-custom-color').toggle($(this).val() === 'custom');
    });

    // ═══════════════════════════════════════
    // VISUAL TEMPLATE SYSTEM
    // ═══════════════════════════════════════

    // Template selector — card click
    $('.bmp-visual-card input[type="radio"]').on('change', function() {
        var tpl = $(this).val();
        $('#bmp_popup_visual_tpl').val(tpl);
        $('#bmp-visual-customize').show();

        // Show/hide template-specific fields (skip shared fields without data-templates)
        $('.bmp-visual-field[data-templates]').each(function() {
            var templates = $(this).data('templates').split(',');
            $(this).toggle(templates.indexOf(tpl) !== -1);
        });

        updatePreview();
    });

    // Visual fields — live preview on input
    $('#bmp_popup_visual_heading, #bmp_popup_visual_body, #bmp_popup_visual_cta_text, #bmp_popup_visual_cta_link, #bmp_popup_visual_video_url, #bmp_popup_visual_coupon, #bmp_popup_visual_badge, #bmp_popup_visual_price, #bmp_popup_visual_author, #bmp_popup_visual_role').on('input', updatePreview);

    // Visual image picker
    $(document).on('click', '#bmp_popup_visual_pick_image', function(e) {
        e.preventDefault();
        if (typeof wp === 'undefined' || typeof wp.media === 'undefined') return;
        var frame = wp.media({
            title: __( 'Choose Image', 'banner-manager-pro' ),
            button: { text: __( 'Use this image', 'banner-manager-pro' ) },
            multiple: false
        });
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            var imgUrl = attachment.url;
            if (attachment.sizes && attachment.sizes.medium) imgUrl = attachment.sizes.medium.url;
            else if (attachment.sizes && attachment.sizes.thumbnail) imgUrl = attachment.sizes.thumbnail.url;
            selectedVisualImageUrl = imgUrl;
            $('#bmp_popup_visual_image_id').val(attachment.id);
            $('#bmp_popup_visual_image_preview').html(
                '<img src="' + escapeAttr(imgUrl) + '" style="max-width:150px;height:auto;border:1px solid #ddd;margin:5px 0;" alt="">'
            );
            $('#bmp_popup_visual_remove_image').show();
            updatePreview();
        });
        frame.open();
    });

    // Visual image remove
    $(document).on('click', '#bmp_popup_visual_remove_image', function(e) {
        e.preventDefault();
        selectedVisualImageUrl = '';
        $('#bmp_popup_visual_image_id').val('');
        $('#bmp_popup_visual_image_preview').html('');
        $(this).hide();
        updatePreview();
    });

    // Init visual fields visibility
    function initVisualFields() {
        var tpl = $('#bmp_popup_visual_tpl').val();
        if (tpl) {
            $('.bmp-visual-field[data-templates]').each(function() {
                var templates = $(this).data('templates').split(',');
                $(this).toggle(templates.indexOf(tpl) !== -1);
            });
        }
    }

    // Init all
    updateTypeFields();
    updatePositionFields();
    updateTriggerFields();
    updateDisplayFields();
    initVisualFields();
});
