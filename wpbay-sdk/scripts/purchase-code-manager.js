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
function wpbayLoading(btn)
{
    btn.attr('disabled','disabled');
}
function wpbayRmLoading(btn)
{
    btn.removeAttr('disabled');
}
jQuery(document).ready(function($) 
{
    $(document).on('click', '.wpbay-purchase-code-register', function() 
    {
        var clicked = $(this);
        wpbayLoading(clicked);
        var wpbay_slug = clicked.attr('data-id');
        var purchase_code = jQuery('#wpbay_sdk_purchase_code' + wpbay_slug).val();
        var wpbay_sdk_security = jQuery('#wpbay_sdk_security' + wpbay_slug).val();
        if(purchase_code == '')
        {
            wpbayRmLoading(clicked);
            alert('You must enter a purchase code!');
        }
        if(wpbay_sdk_security == '')
        {
            wpbayRmLoading(clicked);
            alert('Incorrect backend call.');
        }
        $.post(wpbay_sdk_ajax.ajax_url, {
            action: 'wpbay_sdk_purchase_code_actions' + wpbay_slug.wpbayToSlug(),
            purchase_code: purchase_code,
            wpbay_sdk_security: wpbay_sdk_security,
            wpbay_slug: wpbay_slug,
            wpbay_sdk_action: 'register'
        }, function(response) {
            wpbayRmLoading(clicked);
            if (typeof response !== 'object') {
                response = JSON.parse(response);
            }
            if(response && response.status && response.status == 'success')
            {
                alert('Purchase code registered successfully!');
                $(window).off('beforeunload');
                window.onbeforeunload = function () {return null;};
                location.reload();
            }
            else
            {
                alert('Fail: ' + (response.message ? response.message : 'An unknown error occurred.'));
            }
        }).fail(function(jqXHR, textStatus, errorThrown) 
        {
            wpbayRmLoading(clicked);
            alert('AJAX error: ' + textStatus + ' - ' + errorThrown);
        });
    });
    $(document).on('click', '.wpbay-purchase-code-revoke', function() 
    {
        var clicked = $(this);
        wpbayLoading(clicked);
        var wpbay_slug = clicked.attr('data-id');
        var purchase_code = jQuery('#wpbay_sdk_purchase_code' + wpbay_slug).val();
        var wpbay_sdk_security = jQuery('#wpbay_sdk_security' + wpbay_slug).val();
        if(purchase_code == '')
        {
            wpbayRmLoading(clicked);
            alert('You must enter a purchase code!');
        }
        if(wpbay_sdk_security == '')
        {
            wpbayRmLoading(clicked);
            alert('Incorrect backend call.');
        }
        $.post(wpbay_sdk_ajax.ajax_url, {
            action: 'wpbay_sdk_purchase_code_actions' + wpbay_slug.wpbayToSlug(),
            purchase_code: purchase_code,
            wpbay_sdk_security: wpbay_sdk_security,
            wpbay_slug: wpbay_slug,
            wpbay_sdk_action: 'revoke'
        }, function(response) {
            wpbayRmLoading(clicked);
            if (typeof response !== 'object') {
                response = JSON.parse(response);
            }
            if(response && response.status && response.status == 'success')
            {
                alert('Purchase code revoked successfully!');
                $(window).off('beforeunload');
                window.onbeforeunload = function () {return null;};
                location.reload();
            }
            else
            {
                alert('Fail: ' + (response.message ? response.message : 'An unknown error occurred.'));
            }
        }).fail(function(jqXHR, textStatus, errorThrown) 
        {
            wpbayRmLoading(clicked);
            alert('AJAX error: ' + textStatus + ' - ' + errorThrown);
        });
    });
    $(document).on('click', '.wpbay-purchase-code-check', function() 
    {
        var clicked = $(this);
        wpbayLoading(clicked);
        var wpbay_slug = clicked.attr('data-id');
        var purchase_code = jQuery('#wpbay_sdk_purchase_code' + wpbay_slug).val();
        var wpbay_sdk_security = jQuery('#wpbay_sdk_security' + wpbay_slug).val();
        if(purchase_code == '')
        {
            wpbayRmLoading(clicked);
            alert('You must enter a purchase code!');
        }
        if(wpbay_sdk_security == '')
        {
            wpbayRmLoading(clicked);
            alert('Incorrect backend call.');
        }
        $.post(wpbay_sdk_ajax.ajax_url, {
            action: 'wpbay_sdk_purchase_code_actions' + wpbay_slug.wpbayToSlug(),
            purchase_code: purchase_code,
            wpbay_sdk_security: wpbay_sdk_security,
            wpbay_slug: wpbay_slug,
            wpbay_sdk_action: 'check'
        }, function(response) {
            wpbayRmLoading(clicked);
            if (typeof response !== 'object') {
                response = JSON.parse(response);
            }
            if(response && response.status && response.status == 'success')
            {
                alert('Purchase code checked successfully!');
            }
            else
            {
                alert('Fail: ' + (response.message ? response.message : 'An unknown error occurred.'));
            }
        }).fail(function(jqXHR, textStatus, errorThrown) 
        {
            wpbayRmLoading(clicked);
            alert('AJAX error: ' + textStatus + ' - ' + errorThrown);
        });
    });
    $(document).on('click', '.wpbay-purchase-code-registered', function() 
    {
        var clicked = $(this);
        wpbayLoading(clicked);
        var wpbay_slug = clicked.attr('data-id');
        var purchase_code = jQuery('#wpbay_sdk_purchase_code' + wpbay_slug).val();
        var wpbay_sdk_security = jQuery('#wpbay_sdk_security' + wpbay_slug).val();
        if(purchase_code == '')
        {
            wpbayRmLoading(clicked);
            alert('You must enter a purchase code!');
        }
        if(wpbay_sdk_security == '')
        {
            wpbayRmLoading(clicked);
            alert('Incorrect backend call.');
        }
        $.post(wpbay_sdk_ajax.ajax_url, {
            action: 'wpbay_sdk_purchase_code_actions' + wpbay_slug.wpbayToSlug(),
            purchase_code: purchase_code,
            wpbay_sdk_security: wpbay_sdk_security,
            wpbay_slug: wpbay_slug,
            wpbay_sdk_action: 'registered'
        }, function(response) {
            wpbayRmLoading(clicked);
            if (typeof response !== 'object') {
                response = JSON.parse(response);
            }
            if(response && response.status && response.status == 'success')
            {
                alert('Purchase code is registered!');
            }
            else
            {
                alert('Fail: ' + (response.message ? response.message : 'An unknown error occurred.'));
            }
        }).fail(function(jqXHR, textStatus, errorThrown) 
        {
            wpbayRmLoading(clicked);
            alert('AJAX error: ' + textStatus + ' - ' + errorThrown);
        });
    });
});
