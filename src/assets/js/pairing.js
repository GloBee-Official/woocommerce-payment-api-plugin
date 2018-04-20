'use strict';
(function ($) {
    $(function () {
        $('#globee_api_token_form').on('change', '.globee-pairing-network', function (e) {
            var livenet = 'https://globee.com/api-tokens';
            var testnet = 'https://test.globee.com/api-tokens';
            if ($('.globee-pairing-network').val() === 'livenet') {
                $('.globee-pairing-link').attr('href', livenet).html(livenet);
            } else {
                $('.globee-pairing-link').attr('href', testnet).html(testnet);
            }
        });
        $('#globee_api_token_form').on('click', '.globee-pairing-find', function (e) {
            e.preventDefault();
            $('.globee-pairing').hide();
            $('.globee-pairing').after('<div class="globee-pairing-loading" style="width: 20em; text-align: center"><img src="' + ajax_loader_url + '"></div>');
            $.post(GloBeeAjax.ajaxurl, {
                'action': 'globee_pair_code',
                'pairing_code': $('.globee-pairing-code').val(),
                'network': $('.globee-pairing-network').val(),
                'pairNonce': GloBeeAjax.pairNonce
            }).done(function (data) {
                $('.globee-pairing-loading').remove();
                if (data && data.sin && data.label) {
                    $('.globee-token').removeClass('globee-token-livenet').removeClass('globee-token-testnet').addClass('globee-token-'+data.network);
                    $('.globee-token-token-label').text(data.label);
                    $('.globee-token-token-sin').text(data.sin);
                    $('.globee-token').hide().removeClass('globee-token-hidden').fadeIn(500);
                    $('.globee-pairing-code').val('');
                    $('.globee-pairing-network').val('livenet');
                    $('#message').remove();
                    $('h2.woo-nav-tab-wrapper').after('<div id="message" class="updated fade"><p><strong>You have been paired with your GloBee account!</strong></p></div>');
                } else if (data && data.success === false) {
                    $('.globee-pairing').show();
                    alert('Unable to pair with GloBee.');
                }
            });
        });
        $('#globee_api_token_form').on('click', '.globee-token-revoke', function (e) {
            e.preventDefault();
            if (confirm('Are you sure you want to revoke the token?')) {
                $.post(GloBeeAjax.ajaxurl, {
                    'action': 'globee_revoke_token',
                    'revokeNonce': GloBeeAjax.revokeNonce
                }).always(function (data) {
                    $('.globee-token').fadeOut(500, function () {
                        $('.globee-pairing').removeClass('.globee-pairing-hidden').show();
                        $('#message').remove();
                        $('h2.woo-nav-tab-wrapper').after('<div id="message" class="updated fade"><p><strong>You have revoked your token!</strong></p></div>');
                    });
                });
            }
        });
    });
}(jQuery));
