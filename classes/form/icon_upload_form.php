<?php
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
 * Form for uploading a custom SVG icon for a course module.
 *
 * @package    local_courseicons
 * @copyright  2026 Yeison Díaz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_courseicons\form;

use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Class icon_upload_form.
 */
class icon_upload_form extends moodleform {
    /**
     * Form definition.
     */
    public function definition(): void {
        global $CFG, $OUTPUT, $PAGE, $DB;

        $mform = $this->_form;
        $customdata = $this->_customdata;

        $courseid = $customdata['courseid'];
        $cmid = $customdata['cmid'];
        $modname = $customdata['modname'];

        $mform->addElement('hidden', 'id', $courseid);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'cmid', $cmid);
        $mform->setType('cmid', PARAM_INT);

        $defmodname = $customdata['defmodname'] ?? '';
        if (!empty($defmodname)) {
            $mform->addElement('hidden', 'defmodname', $defmodname);
            $mform->setType('defmodname', PARAM_ALPHANUMEXT);
        }

        $bulkcmids = $customdata['bulkcmids'] ?? '';
        if (!empty($bulkcmids)) {
            $mform->addElement('hidden', 'bulkcmids', $bulkcmids);
            $mform->setType('bulkcmids', PARAM_SEQUENCE);
        }

        if (!empty($defmodname)) {
            $headingtext = get_string('uploaddefaulticon', 'local_courseicons', $modname);
        } else if (!empty($bulkcmids)) {
            $headingtext = $modname;
        } else {
            $headingtext = get_string('uploadicon', 'local_courseicons', $modname);
        }
        $mform->addElement('static', 'form_heading', '', \html_writer::tag('h3', $headingtext, ['class' => 'mb-4 mt-2']));

        // Get library icons.
        $librarydir = $CFG->dirroot . '/local/courseicons/pix/library';
        $libraryicons = [];
        if (is_dir($librarydir)) {
            $files = scandir($librarydir);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                $ext = pathinfo($file, PATHINFO_EXTENSION);
                if (in_array(strtolower($ext), ['svg', 'png', 'jpg', 'jpeg', 'gif'])) {
                    $libraryicons[] = $file;
                }
            }
        }

        // Detect current icon file to determine default tab and selected icon.
        $currentfilename = '';
        if (empty($bulkcmids)) {
            $fs = get_file_storage();
            if (!empty($defmodname)) {
                $defrecord = $DB->get_record('local_courseicons_def', ['courseid' => $courseid, 'modname' => $defmodname]);
                if ($defrecord) {
                    $context = \context_course::instance($courseid);
                    $files = $fs->get_area_files($context->id, 'local_courseicons', 'defaulticon', $defrecord->id, 'id', false);
                    if (!empty($files)) {
                        $file = reset($files);
                        $currentfilename = $file->get_filename();
                    }
                }
            } else if ($cmid > 0) {
                $modcontext = \context_module::instance($cmid, IGNORE_MISSING);
                if ($modcontext) {
                    $files = $fs->get_area_files($modcontext->id, 'local_courseicons', 'activityicon', 0, 'id', false);
                    if (!empty($files)) {
                        $file = reset($files);
                        $currentfilename = $file->get_filename();
                    }
                }
            }
        }

        $iscurrentinlibrary = false;
        if (!empty($currentfilename) && in_array($currentfilename, $libraryicons)) {
            $iscurrentinlibrary = true;
        }

        $defaulttab = 'library';
        $selectedicon = $iscurrentinlibrary ? $currentfilename : '';

        // Add hidden inputs for library support.
        $mform->addElement('hidden', 'active_tab', $defaulttab);
        $mform->setType('active_tab', PARAM_ALPHA);

        $mform->addElement('hidden', 'library_icon', $selectedicon);
        $mform->setType('library_icon', PARAM_FILE);

        // Add Tabs Switcher.
        $tabshtml = '<div class="courseicons-tabs">';

        $libactiveclass = ($defaulttab === 'library') ? ' active' : '';
        $tabshtml .= '<button type="button" class="courseicons-tab-btn' . $libactiveclass . '" data-tab="library">' .
            get_string('selectfromlibrary', 'local_courseicons') . '</button>';

        $uploadactiveclass = ($defaulttab === 'upload') ? ' active' : '';
        $tabshtml .= '<button type="button" class="courseicons-tab-btn' . $uploadactiveclass . '" data-tab="upload">' .
            get_string('uploadcustom', 'local_courseicons') . '</button>';

        $tabshtml .= '</div>';

        $mform->addElement('static', 'tab_switcher', '', $tabshtml);

        // Library panel (grid only).
        $libpanehtml = '<div class="courseicons-grid">';

        foreach ($libraryicons as $iconfile) {
            $iconname = pathinfo($iconfile, PATHINFO_FILENAME);
            $displayname = ucfirst(str_replace(['-', '_'], ' ', $iconname));
            $iconurl = $OUTPUT->image_url('library/' . $iconname, 'local_courseicons');
            $selectedclass = ($selectedicon === $iconfile) ? ' selected' : '';

            $libpanehtml .= '<div class="courseicons-grid-item' . $selectedclass . '" ' .
                'data-icon="' . s($iconfile) . '" data-name="' . s($displayname) . '">';
            $libpanehtml .= '<img src="' . $iconurl . '" alt="' . s($displayname) . '">';
            $libpanehtml .= '<span class="courseicons-grid-item-name">' . s($displayname) . '</span>';
            $libpanehtml .= '</div>';
        }

        $libpanehtml .= '</div>'; // End courseicons-grid.

        $mform->addElement('static', 'tab_pane_library', '', $libpanehtml);

        // Standard filemanager.
        $filemanageropts = [
            'subdirs' => 0,
            'maxbytes' => 2097152,
            'maxfiles' => 1,
            'accepted_types' => ['.svg', '.png', '.jpg', '.jpeg', '.gif'],
        ];

        $mform->addElement(
            'filemanager',
            'iconfile_filemanager',
            get_string('uploadicon', 'local_courseicons', $modname),
            null,
            $filemanageropts
        );

        if (!empty($defmodname)) {
            $mform->addElement('checkbox', 'deleteicon', get_string('deletedefault', 'local_courseicons'));
        } else {
            $mform->addElement('checkbox', 'deleteicon', get_string('deleteicon', 'local_courseicons'));
        }

        $this->add_action_buttons(true, get_string('savechanges', 'local_courseicons'));

        // Require AMD JavaScript.
        $PAGE->requires->js_call_amd('local_courseicons/form', 'init', [[
            'noIconsFoundStr' => get_string('noiconsfound', 'local_courseicons'),
        ]]);
    }
}
