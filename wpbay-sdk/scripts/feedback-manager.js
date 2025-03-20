"use strict";
jQuery(document).ready(function($) {
    $.each(FeedbackManagerData, function(productBaseName, data) {
        // Handle plugin deactivation
        $(document).on('click', 'tr[data-plugin="' + productBaseName + '"] .deactivate a', function(e) {
            e.preventDefault();
            var deactivateUrl = $(this).attr('href');

            var modalId = 'wpbay-feedback-modal-' + data.product_slug;
            var reasonName = 'wpbay-feedback-reason-' + data.product_slug;
            var detailsId = 'wpbay-feedback-details-' + data.product_slug;
            var detailsContainerId = 'wpbay-feedback-details-container-' + data.product_slug;

            showFeedbackModal(modalId, reasonName, detailsId, detailsContainerId, data, function() {
                window.location.href = deactivateUrl;
            });
        });

        // Handle theme deactivation
        if (data.is_active_theme) {
            // Handle theme activation via theme modal dialog
            $(document).on('click', '.theme-actions .activate, a.activatelink', function(e) {
                e.preventDefault();
                var activateUrl = $(this).attr('href');

                // Extract the theme slug from the activateUrl
                var urlParams = new URLSearchParams(activateUrl.split('?')[1]);
                var newThemeSlug = urlParams.get('stylesheet');

                if (newThemeSlug === productBaseName) {
                    // User is reactivating your theme; no action needed
                    window.location.href = activateUrl;
                    return;
                }

                var modalId = 'wpbay-feedback-modal-' + productBaseName;
                var reasonName = 'wpbay-feedback-reason-' + productBaseName;
                var detailsId = 'wpbay-feedback-details-' + productBaseName;
                var detailsContainerId = 'wpbay-feedback-details-container-' + productBaseName;

                showFeedbackModal(modalId, reasonName, detailsId, detailsContainerId, data, function() {
                    window.location.href = activateUrl;
                });
            });
        }
    });

    function showFeedbackModal(modalId, reasonName, detailsId, detailsContainerId, data, onSuccess) {
        if ($('#' + modalId).length === 0) {
            var reasons = data.reasons;
            var reasonsHtml = '<ul class="wpbay-feedback-reasons">';
            $.each(reasons, function(value, label) {
                reasonsHtml += '<li>';
                reasonsHtml += '<label>';
                reasonsHtml += '<input type="radio" name="' + reasonName + '" value="' + value + '"> ';
                reasonsHtml += label;
                reasonsHtml += '</label>';
                if (value === 'other') {
                    reasonsHtml += '<div id="' + detailsContainerId + '" style="display:none;">';
                    reasonsHtml += '<p>' + data.details_prompt + '</p>';
                    reasonsHtml += '<textarea id="' + detailsId + '" style="width:100%; height:100px;"></textarea>';
                    reasonsHtml += '</div>';
                }
                reasonsHtml += '</li>';
            });
            reasonsHtml += '</ul>';

            $('body').append(`
                <div id="` + modalId + `" style="display:none;" title="` + data.dialog_title + `">
                    <p>` + data.dialog_message + `</p>
                    ` + reasonsHtml + `
                </div>
            `);

            // Initialize the dialog here
            $('#' + modalId).dialog({
                autoOpen: false,
                modal: true,
                buttons: [
                    {
                        text: "Cancel",
                        class: "button",
                        click: function() {
                            $(this).dialog("close");
                        }
                    },
                    {
                        text: "Skip",
                        class: "button",
                        click: function() {
                            onSuccess();
                            $(this).dialog("close");
                        }
                    },
                    {
                        text: "Submit",
                        class: "button button-primary",
                        click: function() {
                            var selectedReason = $('input[name="' + reasonName + '"]:checked').val();
                            var details = $('#' + detailsId).val();

                            if (!selectedReason) {
                                alert(data.select_reason);
                                return;
                            }

                            var postData = {
                                action: 'wpbay_sdk_submit_feedback',
                                reason: selectedReason,
                                details: details,
                                product_slug: data.product_slug,
                                product_id: data.product_id,
                                nonce: data.nonce
                            };

                            $.post(data.ajax_url, postData, function(response) {
                                onSuccess();
                            });

                            $(this).dialog("close");
                        }
                    }
                ]
            });
        }

        // Open the dialog
        $('#' + modalId).dialog('open');

        // Handle the change event for the radio buttons
        $('input[name="' + reasonName + '"]').off('change').on('change', function() {
            if ($(this).val() === 'other') {
                $('#' + detailsContainerId).show();
            } else {
                $('#' + detailsContainerId).hide();
            }
        });
    }
});
