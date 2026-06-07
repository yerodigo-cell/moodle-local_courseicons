define(['jquery', 'core/str', 'core/notification'], function($, str, Notification) {
    return {
        init: function() {
            var selectAll = $('#courseicons-select-all');
            var checkboxes = $('.courseicons-bulk-checkbox');
            var submitBtn = $('#courseicons-bulk-submit');
            var bulkForm = $('#courseicons-bulk-form');

            var updateButtonState = function() {
                var checkedCount = checkboxes.filter(':checked').length;
                if (checkedCount > 0) {
                    submitBtn.removeAttr('disabled');
                } else {
                    submitBtn.attr('disabled', 'disabled');
                }
            };

            selectAll.on('change', function() {
                checkboxes.prop('checked', $(this).prop('checked'));
                updateButtonState();
            });

            checkboxes.on('change', function() {
                if (!$(this).prop('checked')) {
                    selectAll.prop('checked', false);
                }
                updateButtonState();
            });

            // Single delete confirm.
            $('.courseicons-delete-single').on('click', function(e) {
                e.preventDefault();
                var link = $(this).attr('href');
                var confirmMsg = $(this).data('confirm');

                str.get_strings([
                    {key: 'confirm'},
                    {key: 'delete'}
                ]).done(function(s) {
                    Notification.confirm(s[0], confirmMsg, s[1], s[0], function() {
                        window.location.href = link;
                    });
                });
            });

            // Bulk delete confirm.
            bulkForm.on('submit', function(e) {
                e.preventDefault();
                var confirmMsg = submitBtn.data('confirm');

                str.get_strings([
                    {key: 'confirm'},
                    {key: 'delete'}
                ]).done(function(s) {
                    Notification.confirm(s[0], confirmMsg, s[1], s[0], function() {
                        bulkForm[0].submit();
                    });
                });
            });
        }
    };
});
