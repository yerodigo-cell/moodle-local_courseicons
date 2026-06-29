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
 * JS controller for the icon upload form tab switching and icon library grid in local_courseicons.
 *
 * @module     local_courseicons/form
 * @copyright  2026 Yeison Díaz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {
    return {
        init: function(config) {
            var activeTabInput = $('input[name="active_tab"]');
            var libraryIconInput = $('input[name="library_icon"]');
            var deleteIconCheckbox = $('input[name="deleteicon"]');

            var tabBtns = $('.courseicons-tab-btn');
            var libraryItems = $('.courseicons-grid-item');

            var libraryRow = $('#fitem_id_tab_pane_library');
            var filemanagerRow = $('#fitem_id_iconfile_filemanager');

            var updateTabVisibility = function(targetTab) {
                if (targetTab === 'library') {
                    libraryRow.show();
                    filemanagerRow.hide();
                } else {
                    libraryRow.hide();
                    filemanagerRow.show();
                }
            };

            // Tab Switching.
            tabBtns.on('click', function(e) {
                e.preventDefault();
                var btn = $(this);
                var targetTab = btn.data('tab');

                tabBtns.removeClass('active');
                btn.addClass('active');

                updateTabVisibility(targetTab);

                activeTabInput.val(targetTab);
            });

            // Initialize visibility.
            var initialTab = activeTabInput.val() || 'library';
            updateTabVisibility(initialTab);


            // Library Icon Selection.
            libraryItems.on('click', function() {
                var item = $(this);
                var isAlreadySelected = item.hasClass('selected');

                libraryItems.removeClass('selected');

                if (isAlreadySelected) {
                    libraryIconInput.val('');
                } else {
                    item.addClass('selected');
                    libraryIconInput.val(item.data('icon'));

                    // Automatically uncheck delete checkbox if selecting a library icon.
                    if (deleteIconCheckbox.length) {
                        deleteIconCheckbox.prop('checked', false);
                    }
                }
            });


        }
    };
});
