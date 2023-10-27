jQuery(document).ready(function($) {
    
    // Prepend the "Generate Meta Description" button
    $('#meta_description_boy_generate_meta_description').on('click', function(e) {
        e.preventDefault();

        var $this = $(this); // Reference to the button
        var originalButtonText = $this.text(); // Store the original button text

        // Display spinner inside the button and disable it
        $this.html('<span class="spinner is-active" style="margin: 0; float: none;"></span> Generating...');
        $this.prop('disabled', true);

        $.ajax({
            type: 'POST',
            url: meta_description_boy_data.ajax_url,
            data: {
                action: 'meta_description_boy_generate_description',
                post_id: meta_description_boy_data.post_id,
                nonce: meta_description_boy_data.nonce
            },
            success: function(response) {
                // Restore the button to its original state
                $this.text(originalButtonText);
                $this.prop('disabled', false);
                
                var outputDiv = $('#meta_description_boy_output');
                
                if (response.success) {
                    // Get the meta description content
                    var metaDescription = response.data.description;
            
                    // Create the success notice and the Copy to Clipboard button
                    outputDiv.html('<div class="notice notice-success"><p>' + metaDescription + '</p></div><button id="copyToClipboard" class="button tagadd">Copy to Clipboard</button>');
                    
                    // Event listener for the Copy to Clipboard button
                    $('#copyToClipboard').click(function(e) {
                        e.preventDefault();  // Prevent the default behavior of the button
                        var $copyButton = $(this); // Reference to the copy button

                        if (navigator.clipboard) {
                            navigator.clipboard.writeText(metaDescription).then(function() {
                                $copyButton.text('Copied!'); // Update the button text
                            }).catch(function(err) {
                                console.error('Could not copy text: ', err);
                            });
                        } else {
                            // Browsers that don't support the Clipboard API
                            alert('Your browser does not support direct clipboard copy. Please manually copy the meta description.');
                        }
                    });
            
                } else {
                    outputDiv.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                }
            },
            error: function() {
                // Restore the button to its original state in case of an AJAX error
                $this.text(originalButtonText);
                $this.prop('disabled', false);
            }
        });
    });
});