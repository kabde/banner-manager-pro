jQuery(function($) {
    var selectedImageUrl = $('#bmp_image_preview img').first().attr('src') || '';

    function escapeAttr(value) {
        return String(value || '').replace(/[&<>"']/g, function(character) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[character];
        });
    }

    function currentType() {
        return $('input[name="bmp_type"]:checked').val() || 'image';
    }

    function safePreviewUrl(value) {
        if (!value) {
            return '';
        }

        try {
            var url = new URL(value, window.location.origin);
            return (url.protocol === 'http:' || url.protocol === 'https:') ? url.href : '';
        } catch (error) {
            return '';
        }
    }

    function updateEmptyStates() {
        $('#bmp-image-empty').toggle(!$('#bmp_image_id').val());
        $('#bmp-link-empty').toggle(!$('#bmp_image_link').val());
        $('#bmp-positions-empty').toggle($('input[name="bmp_positions[]"]:checked').length === 0);
    }

    function updateTypeFields() {
        if (currentType() === 'html') {
            $('#bmp-image-fields').hide();
            $('#bmp-html-fields').show();
            $('#bmp-dimensions-section').hide();
        } else {
            $('#bmp-image-fields').show();
            $('#bmp-html-fields').hide();
            $('#bmp-dimensions-section').show();
        }

        updatePreview();
    }

    function updateCustomDimensions() {
        $('#bmp-custom-dimensions').toggle($('#bmp_dimensions_select').val() === 'custom');
    }

    function imagePreviewHtml() {
        var imageId = $('#bmp_image_id').val();
        var imageUrl = selectedImageUrl || $('#bmp_image_preview img').first().attr('src') || '';
        var link = safePreviewUrl($('#bmp_image_link').val());

        if (!imageId || !imageUrl) {
            return '<div class="bmp-preview-empty">Aucune image sélectionnée.</div>';
        }

        var image = '<img class="bmp-preview-image" src="' + escapeAttr(imageUrl) + '" alt="">';
        if (link) {
            return '<a href="' + escapeAttr(link) + '" target="_blank" rel="noopener noreferrer">' + image + '</a>';
        }

        return image;
    }

    function htmlPreviewHtml() {
        var html = $('#bmp_html').val();

        if (!$.trim(html)) {
            return '<div class="bmp-preview-empty">Aucun code HTML à prévisualiser.</div>';
        }

        return '<iframe class="bmp-preview-iframe" sandbox="allow-scripts allow-popups allow-forms" srcdoc="' + escapeAttr(html) + '"></iframe>';
    }

    function updatePreview() {
        $('#bmp-live-preview').html(currentType() === 'html' ? htmlPreviewHtml() : imagePreviewHtml());
        updateEmptyStates();
    }

    function removeImage() {
        selectedImageUrl = '';
        $('#bmp_image_id').val('');
        $('#bmp_image_preview').empty();
        updatePreview();
    }

    function renderSelectedImage(attachment) {
        var imageUrl = attachment.url;
        if (attachment.sizes && attachment.sizes.medium) {
            imageUrl = attachment.sizes.medium.url;
        } else if (attachment.sizes && attachment.sizes.thumbnail) {
            imageUrl = attachment.sizes.thumbnail.url;
        }

        selectedImageUrl = imageUrl;
        $('#bmp_image_id').val(attachment.id);
        $('#bmp_image_preview').html(
            '<img src="' + escapeAttr(imageUrl) + '" style="max-width:150px; height:auto; border:1px solid #ddd; margin:5px 0;" alt="">' +
            '<br><button type="button" class="button button-small" id="bmp_remove_image">Supprimer</button>'
        );
        updatePreview();
    }

    $('input[name="bmp_type"]').on('change', updateTypeFields);
    $('#bmp_dimensions_select').on('change', updateCustomDimensions);
    $('#bmp_image_link, #bmp_html').on('input', updatePreview);
    $(document).on('change', 'input[name="bmp_positions[]"]', updateEmptyStates);
    $(document).on('click', '#bmp_remove_image', removeImage);

    $('.bmp-preview-mode').on('click', function() {
        var mode = $(this).data('mode') === 'mobile' ? 'mobile' : 'desktop';

        $('.bmp-preview-mode').removeClass('is-active');
        $(this).addClass('is-active');
        $('#bmp-live-preview')
            .toggleClass('bmp-preview-mobile', mode === 'mobile')
            .toggleClass('bmp-preview-desktop', mode !== 'mobile');
    });

    $('#bmp_pick_image').on('click', function(e) {
        e.preventDefault();

        if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
            window.alert('Erreur: La bibliothèque média WordPress n\'est pas disponible');
            return;
        }

        var frame = wp.media({
            title: 'Choisir une image',
            button: {
                text: 'Utiliser cette image'
            },
            multiple: false
        });

        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            renderSelectedImage(attachment);
        });

        frame.open();
    });

    if ($('#bmp_image_id').val() && !$('#bmp_remove_image').length) {
        $('#bmp_image_preview').append('<br><button type="button" class="button button-small" id="bmp_remove_image">Supprimer</button>');
    }

    updateTypeFields();
    updateCustomDimensions();
    updateEmptyStates();
});
