jQuery(document).ready(function($) {
    var frame;
    $('#bmp_popup_pick_image').on('click', function(e) {
        e.preventDefault();
        if (frame) {
            frame.open();
            return;
        }
        frame = wp.media({
            title: 'Choisir une image',
            button: { text: 'Utiliser cette image' },
            multiple: false
        });
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            $('#bmp_popup_image_id').val(attachment.id);
            var imageUrl = attachment.url;
            if (attachment.sizes && attachment.sizes.thumbnail) {
                imageUrl = attachment.sizes.thumbnail.url;
            }
            $('#bmp_popup_image_preview').html('<img src="' + imageUrl + '" style="max-width:150px; max-height:150px;" />');
        });
        frame.open();
    });
});
