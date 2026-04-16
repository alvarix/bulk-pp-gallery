(function ($) {
    'use strict';

    var $modal = null;
    var selectedIds = [];

    $(document).ready(function () {
        $modal = $('#ppgal2-modal');

        // --- List view: intercept bulk action ---
        $('form#posts-filter').on('submit', function (e) {
            var action = $('select[name="action"]').val() || $('select[name="action2"]').val();
            if (action !== 'ppgal2_create_posts') return;
            e.preventDefault();
            collectListViewAndOpen();
        });

        $('input#doaction2').on('click', function (e) {
            if ($('select[name="action2"]').val() === 'ppgal2_create_posts') {
                e.preventDefault();
                collectListViewAndOpen();
            }
        });

        // --- Grid view: add toolbar button ---
        setupGridViewButton();

        // --- Modal handlers ---
        $('#ppgal2-modal-cancel, .ppgal2-modal-overlay').on('click', closeModal);
        $('#ppgal2-modal-confirm').on('click', confirmCreate);
    });

    /**
     * Collect selected IDs from list view checkboxes.
     */
    function collectListViewAndOpen() {
        selectedIds = [];
        $('input[name="media[]"]:checked').each(function () {
            selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0) {
            alert('Please select at least one image first.');
            return;
        }

        openModal();
    }

    /**
     * Add a "Create PP Gallery Posts" button to the grid view toolbar.
     * The grid view uses Backbone/wp.media, so we poll until the toolbar
     * is rendered, then inject our button.
     */
    function setupGridViewButton() {
        var attempts = 0;
        var maxAttempts = 50;

        var poller = setInterval(function () {
            attempts++;
            // Grid view toolbar selector
            var $toolbar = $('.media-toolbar-secondary');

            if ($toolbar.length && !$('#ppgal2-grid-create').length) {
                var $btn = $('<button type="button" class="button media-button" id="ppgal2-grid-create">' +
                    'Create PP Gallery Posts</button>');

                $btn.on('click', function () {
                    collectGridViewAndOpen();
                });

                $toolbar.append($btn);
                clearInterval(poller);
            }

            if (attempts >= maxAttempts) {
                clearInterval(poller);
            }
        }, 200);
    }

    /**
     * Collect selected attachment IDs from the grid view selection.
     * wp.media stores selection state in the Backbone browser.
     */
    function collectGridViewAndOpen() {
        selectedIds = [];

        // Try to get selection from the media library grid's Backbone state
        if (window.wp && wp.media && wp.media.frame) {
            var selection = wp.media.frame.state().get('selection');
            if (selection && selection.length) {
                selection.each(function (attachment) {
                    selectedIds.push(attachment.id);
                });
            }
        }

        // Fallback: look for visually selected items
        if (selectedIds.length === 0) {
            $('.attachment.selected, .attachment[aria-checked="true"]').each(function () {
                var id = $(this).data('id');
                if (id) selectedIds.push(id);
            });
        }

        if (selectedIds.length === 0) {
            alert('Please select at least one image first.\n\nIn grid view: click "Bulk select" in the toolbar, select images, then click this button.');
            return;
        }

        openModal();
    }

    /**
     * Open the confirmation modal with the count of selected images.
     */
    function openModal() {
        $modal.find('.ppgal2-modal-count').text(selectedIds.length + ' image(s) selected.');
        $('#ppgal2-modal-progress').hide();
        $('#ppgal2-modal-confirm').prop('disabled', false);
        $modal.show();
    }

    function closeModal() {
        $modal.hide();
    }

    /**
     * Send the bulk create AJAX request.
     */
    function confirmCreate() {
        $('#ppgal2-modal-confirm').prop('disabled', true);
        $('#ppgal2-modal-progress').show();

        $.ajax({
            url: ppgal2Data.ajaxUrl,
            method: 'POST',
            data: {
                action: 'ppgal2_bulk_create',
                nonce: ppgal2Data.nonce,
                attachment_ids: selectedIds,
            },
            success: function (response) {
                if (response.success) {
                    alert(response.data.message);
                    closeModal();
                    window.location.reload();
                } else {
                    alert('Error: ' + response.data);
                    $('#ppgal2-modal-confirm').prop('disabled', false);
                    $('#ppgal2-modal-progress').hide();
                }
            },
            error: function () {
                alert('Request failed. Please try again.');
                $('#ppgal2-modal-confirm').prop('disabled', false);
                $('#ppgal2-modal-progress').hide();
            },
        });
    }
})(jQuery);
