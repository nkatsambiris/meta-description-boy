jQuery(document).ready(function($) {
    $('.editinline').on('click', function() {
        var postID = $(this).closest('tr').attr('id').replace('post-', '');
        var yoastMetaDesc = $('#post-' + postID + ' .column-meta_description_boy_yst_meta_description').text();
        $('textarea[name="mdb-yoast-meta-description"]').val(yoastMetaDesc);
        $('.generate-meta-description').data('post-id', postID);
    });
    $('.save').on('click', function() {
        
        var postID = $(this).closest('tr').attr('id').replace('edit-', '');
        var updatedMetaDesc = $('textarea[name="mdb-yoast-meta-description"]').val();
        $.ajax({
            url: ajax_object.ajaxurl,
            type: 'POST',
            data: {
                action: 'save_yoast_meta_description',
                post_id: postID,
                meta_desc: updatedMetaDesc
            },
            
            success: function(response) {
                console.log('Meta description for post_ID:' + postID + ' updated sucessfully.');
            }
        });
    });

    $(document).on('click', '.generate-meta-description', function() {
        var postID = $(this).data('post-id');
        var $button = $(this);
        $button.text('Generating...').prop('disabled', true);
    
        $.ajax({
            type: 'POST',
            url: ajax_object.ajaxurl,
            data: {
                action: 'meta_description_boy_generate_description',
                post_id: postID,
                nonce: $('#meta_description_boy_nonce').val()

            },
            success: function(response) {
                if (response.success) {
                    // Update the textarea with the new meta description
                    $('textarea[name="mdb-yoast-meta-description"]').val(response.data.description);
            
                    // Optionally, display a success message to the user
                    // You can append a message near the textarea or button
                } else {
                    // Handle failure - Display the error message from the response
                    // Display WordPress styled dismissible error notice
                    var errorMessage = '<div class="notice notice-warning is-dismissible"><p>' + response.data.message + '</p></div>';
                    $('.mdb-error-notice').prepend(errorMessage); // Replace '#some-element' with the selector where you want to show the notice
                }
                $button.text('Generate Meta Description').prop('disabled', false);
            },
            error: function() {
                // Handle AJAX error (e.g., display an error message)
                $button.text('Generate Meta Description').prop('disabled', false);

                // Display WordPress styled dismissible error notice for AJAX error
                var ajaxErrorMessage = '<div class="notice notice-warning is-dismissible"><p>AJAX request failed: ' + textStatus + ', ' + errorThrown + '</p></div>';
                $('.mdb-error-notice').prepend(ajaxErrorMessage); // Replace '#some-element' with the selector where you want to show the notice
            }
            
        });
    });
    

});

