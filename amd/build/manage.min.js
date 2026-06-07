define(['jquery', 'core/str', 'core/notification'], function($, str, Notification) {
    return {
        init: function() {
            var selectAll = $('#courseicons-select-all');
            var checkboxes = $('.courseicons-bulk-checkbox');
            var submitBtn = $('#courseicons-bulk-submit');
            var uploadBtn = $('#courseicons-bulk-upload');
            var bulkForm = $('#courseicons-bulk-form');
            var actionInput = $('#courseicons-bulk-action');

            var searchInput = $('#courseicons-search');
            var filterSelect = $('#courseicons-filter');
            var tableRows = $('.courseicons-row');

            var filterTable = function() {
                var searchText = searchInput.val().toLowerCase();
                var filterType = filterSelect.val();

                tableRows.each(function() {
                    var row = $(this);
                    var modname = row.data('modname');
                    var name = row.data('name').toLowerCase();

                    var matchSearch = name.indexOf(searchText) !== -1;
                    var matchFilter = filterType === 'all' || modname === filterType;

                    if (matchSearch && matchFilter) {
                        row.show();
                    } else {
                        row.hide();
                        // Uncheck hidden rows so they don't get bulk processed accidentally.
                        row.find('.courseicons-bulk-checkbox').prop('checked', false);
                    }
                });
                updateButtonState();
            };

            searchInput.on('input', filterTable);
            filterSelect.on('change', filterTable);

            var updateButtonState = function() {
                var checkedCheckboxes = checkboxes.filter(':checked');
                var checkedCount = checkedCheckboxes.length;

                if (checkedCount > 0) {
                    uploadBtn.removeAttr('disabled');

                    var hasCustom = false;
                    checkedCheckboxes.each(function() {
                        if ($(this).data('hascustom') == 1) {
                            hasCustom = true;
                            return false; // Break loop.
                        }
                        return true;
                    });

                    if (hasCustom) {
                        submitBtn.removeAttr('disabled');
                    } else {
                        submitBtn.attr('disabled', 'disabled');
                    }
                } else {
                    submitBtn.attr('disabled', 'disabled');
                    uploadBtn.attr('disabled', 'disabled');
                }
            };

            selectAll.on('change', function() {
                // Only select visible checkboxes.
                var isChecked = $(this).prop('checked');
                checkboxes.each(function() {
                    if ($(this).closest('.courseicons-row').is(':visible')) {
                        $(this).prop('checked', isChecked);
                    }
                });
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

            var submitAction = 'delete';
            submitBtn.on('click', function() {
                submitAction = 'delete';
            });
            uploadBtn.on('click', function() {
                submitAction = 'upload';
            });

            // Bulk actions.
            bulkForm.on('submit', function(e) {
                if (submitAction === 'upload') {
                    actionInput.val('bulkuploadform');
                    return true; // Allow normal submission.
                }

                e.preventDefault();
                actionInput.val('bulkdelete');
                var confirmMsg = submitBtn.data('confirm');

                str.get_strings([
                    {key: 'confirm'},
                    {key: 'delete'}
                ]).done(function(s) {
                    Notification.confirm(s[0], confirmMsg, s[1], s[0], function() {
                        bulkForm[0].submit();
                    });
                });
                return false;
            });
        }
    };
});
