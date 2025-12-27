jQuery(document).ready(function($) {
    'use strict';

    // MAC Menu API Key validation
    $('#kvp-form').on('submit', function (e) {
        e.preventDefault();
        if ($('#kvp-key-input').val() != '' && $('#kvp-key-input').val() != 'MAC Menu') {
            var key = $('#kvp-key-input').val();
            $.ajax({
                url: kvp_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'kvp_handle_ajax_request',
                    key: key
                },
                success: function (response) {
                    location.reload();
                    if (response.success) {
                        $('#kvp-result').text(response.data).css('color', 'green');
                    } else {
                        $('#kvp-result').text(response.data).css('color', 'red');
                    }
                },
                error: function () {
                    $('#kvp-result').text('Error occurred.').css('color', 'red');
                }
            });
        }
    });
}); 