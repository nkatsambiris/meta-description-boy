jQuery(document).ready(function($) {
    $('input[name="meta_description_boy_selected_model"]').on('change', function() {
        var selectedModel = $(this).val();
        
        $.ajax({
            type: 'POST',
            url: meta_description_boy_model_data.ajax_url,
            data: {
                action: 'meta_description_boy_update_selected_model',
                selected_model: selectedModel,
                nonce: meta_description_boy_model_data.nonce
            }
        });
    });
});
