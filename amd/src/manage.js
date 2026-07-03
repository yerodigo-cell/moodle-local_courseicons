// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Manage JS script for local_courseicons.
 *
 * @module     local_courseicons/manage
 * @copyright  2026 Yeison Díaz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/str', 'core/notification'], function($, str, Notification) {
    return {
        init: function() {
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
                $('#courseicons-bulk-submit').trigger('update-state');
            };

            searchInput.on('input', filterTable);
            filterSelect.on('change', filterTable);

            var bindBulkForm = function(selectAllId, checkboxClass, submitBtnId, uploadBtnId, formId, actionInputId, rowClass) {
                var selectAll = $(selectAllId);
                var checkboxes = $(checkboxClass);
                var submitBtn = $(submitBtnId);
                var uploadBtn = uploadBtnId ? $(uploadBtnId) : $();
                var bulkForm = $(formId);
                var actionInput = $(actionInputId);
                var defaultAction = actionInput.val();

                var updateButtonState = function() {
                    var checkedCheckboxes = checkboxes.filter(':checked');
                    var checkedCount = checkedCheckboxes.length;

                    if (checkedCount > 0) {
                        if (uploadBtn.length) uploadBtn.removeAttr('disabled');

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
                        if (uploadBtn.length) uploadBtn.attr('disabled', 'disabled');
                    }
                };

                // Expose update state for filtering
                submitBtn.on('update-state', updateButtonState);

                selectAll.on('change', function() {
                    var isChecked = $(this).prop('checked');
                    checkboxes.each(function() {
                        if (rowClass) {
                            if ($(this).closest(rowClass).is(':visible')) {
                                $(this).prop('checked', isChecked);
                            }
                        } else {
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

                var submitAction = 'delete';
                if (submitBtn.length) {
                    submitBtn.on('click', function() {
                        submitAction = 'delete';
                    });
                }
                if (uploadBtn.length) {
                    uploadBtn.on('click', function() {
                        submitAction = 'upload';
                    });
                }

                if (bulkForm.length) {
                    bulkForm.on('submit', function(e) {
                        if (submitAction === 'upload') {
                            if (actionInput.length) actionInput.val('bulkuploadform');
                            return true; // Allow normal submission.
                        }

                        e.preventDefault();
                        if (actionInput.length) {
                            actionInput.val(defaultAction);
                        }
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

            // Single delete confirm is generic
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

            // Bind Individual Icons Form
            bindBulkForm('#courseicons-select-all', '.courseicons-bulk-checkbox', '#courseicons-bulk-submit', '#courseicons-bulk-upload', '#courseicons-bulk-form', '#courseicons-bulk-action', '.courseicons-row');
            
            // Bind Default Icons Form
            bindBulkForm('#courseicons-select-all-def', '.courseicons-bulk-checkbox-def', '#courseicons-bulk-def-submit', null, '#courseicons-bulk-def-form', '#courseicons-bulk-def-action', null);
        }
    };
});
