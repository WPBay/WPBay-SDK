"use strict";
String.prototype.wpbayToSlug = function ()
{
	var str = this;
	str = str.replace(/^\s+|\s+$/g, ''); 
	str = str.toLowerCase();

	var from = "àáäâèéëêìíïîòóöôùúüûñçěščřžýúůďťň·/_,:;";
	var to   = "aaaaeeeeiiiioooouuuuncescrzyuudtn------";

	for (var i=0, l=from.length ; i<l ; i++)
	{
		str = str.replace(new RegExp(from.charAt(i), 'g'), to.charAt(i));
	}

	str = str.replace('.', '-')
		.replace(/[^a-z0-9 -]/g, '') 
		.replace(/\s+/g, '-') 
		.replace(/-+/g, '-')
		.replace( /\//g, '' );

	return str;
}
jQuery(document).ready(function($) 
{
    $(document).on('click', '.wpbay-notice.notice.is-dismissible', function() {
        var notice = $(this);
        var noticeKey = notice.data('notice-key');
        var productSlug = notice.data('slug');
        $.post(wpbay_sdk_ajax.ajax_url, {
            action: 'wpbay_sdk_dismiss_admin_notice' + productSlug.wpbayToSlug(),
            notice_key: noticeKey,
            _ajax_nonce: wpbay_sdk_ajax.nonce
        }, function(response) {
            if (response.success) {
                notice.fadeOut();
            }
        });
    });
});
