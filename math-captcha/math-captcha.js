// Math CAPTCHA plugin JavaScript
// Modify the add_link function to include the CAPTCHA answer in AJAX requests

jQuery(document).ready(function($) {
    // Store the original add_link function
    if (typeof add_link === 'function') {
        var original_add_link = add_link;
        
        // Override the add_link function to include CAPTCHA answer
        add_link = function() {
            var captcha_answer = $('#math-captcha-answer').val();
            
            // If CAPTCHA field exists and is empty, show error
            if ($('#math-captcha-answer').length > 0 && !captcha_answer) {
                feedback('Please solve the math CAPTCHA to shorten URLs.', 'fail');
                return false;
            }
            
            // Get the original parameters
            var newurl = $('#add-url').val();
            var nonce = $('#nonce-add').val();
            var keyword = $('#add-keyword').val();
            var nextid = parseInt($('#main_table tbody tr[id^="id-"]').length) + 1;
            
            if ( !newurl || newurl == 'http://' || newurl == 'https://' ) {
                return;
            }
            
            // Disable button and show loading
            if( $('#add-button').hasClass('disabled') ) {
                return false;
            }
            add_loading('#add-button');
            
            // Make AJAX request with CAPTCHA answer
            $.getJSON(
                ajaxurl,
                {action:'add', url: newurl, keyword: keyword, nonce: nonce, rowid: nextid, math_captcha_answer: captcha_answer},
                function(data){
                    if(data.status == 'success') {
                        $('#main_table tbody').prepend( data.html ).trigger('update');
                        $('#nourl_found').css('display', 'none');
                        zebra_table();
                        increment_counter();
                        toggle_share_fill_boxes( data.url.url, data.shorturl, data.url.title );
                    }

                    add_link_reset();
                    end_loading('#add-button');
                    end_disable('#add-button');

                    feedback(data.message, data.status);
                    
                    // If CAPTCHA was wrong, generate a new question
                    if (data.code === 'error:captcha_wrong' || data.code === 'error:captcha_missing') {
                        // Reload the page to get a new CAPTCHA question
                        // This is the simplest approach to ensure a new question is generated
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    }
                }
            );
        };
    }
});
