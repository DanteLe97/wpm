jQuery(document).ready(function($){
    // Check connection buttons
    $('.sfa-check').on('click', function(e){
        e.preventDefault();
        var src = $(this).data('source');
        var $status = $('#sfa-status-'+src);
        var $btn = $(this);
        
        $btn.prop('disabled', true);
        $status.removeClass('sfa-success sfa-error').text('Checking...');
        
        $.post(sfa_ajax.ajax_url, { 
            action: 'sfa_check_connection', 
            _ajax_nonce: sfa_ajax.nonce, 
            source: src
        }, function(resp){
            $btn.prop('disabled', false);
            if(resp.success){
                $status.addClass('sfa-success').text('✓ Connected: ' + JSON.stringify(resp.data));
            } else {
                $status.addClass('sfa-error').text('✗ Error: ' + (resp.data.message || JSON.stringify(resp.data)));
            }
        }).fail(function(){
            $btn.prop('disabled', false);
            $status.addClass('sfa-error').text('✗ Request failed');
        });
    });
    
    // OAuth Connect buttons
    $('#sfa-instagram-connect').on('click', function(e){
        e.preventDefault();
        if(!sfa_ajax.instagram_url){
            alert('Please fill in Instagram App ID first');
            return;
        }
        var width = 600;
        var height = 700;
        var left = (screen.width - width) / 2;
        var top = (screen.height - height) / 2;
        window.open(
            sfa_ajax.instagram_url,
            'Instagram OAuth',
            'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',resizable=yes,scrollbars=yes'
        );
    });
    
    $('#sfa-facebook-connect').on('click', function(e){
        e.preventDefault();
        if(!sfa_ajax.facebook_url){
            alert('Please fill in Facebook App ID first');
            return;
        }
        var width = 600;
        var height = 700;
        var left = (screen.width - width) / 2;
        var top = (screen.height - height) / 2;
        window.open(
            sfa_ajax.facebook_url,
            'Facebook OAuth',
            'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',resizable=yes,scrollbars=yes'
        );
    });
    
    // Manual fetch button
    $('#sfa-manual-fetch').on('click', function(e){
        e.preventDefault();
        var $btn = $(this);
        var $status = $('#sfa-fetch-status');
        
        $btn.prop('disabled', true);
        $status.removeClass('sfa-success sfa-error').text('Fetching...');
        
        $.post(sfa_ajax.ajax_url, {
            action: 'sfa_manual_fetch',
            _ajax_nonce: sfa_ajax.nonce
        }, function(resp){
            $btn.prop('disabled', false);
            if(resp.success){
                $status.addClass('sfa-success').text('✓ Fetch completed successfully');
            } else {
                $status.addClass('sfa-error').text('✗ Error: ' + (resp.data.message || 'Unknown error'));
            }
        }).fail(function(){
            $btn.prop('disabled', false);
            $status.addClass('sfa-error').text('✗ Request failed');
        });
    });
});
