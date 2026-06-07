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
        $mform = $this->_form;
        $customdata = $this->_customdata;

        $courseid = $customdata['courseid'];
        $cmid = $customdata['cmid'];
        $modname = $customdata['modname'];

        $mform->addElement('hidden', 'id', $courseid);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'cmid', $cmid);
        $mform->setType('cmid', PARAM_INT);

        $bulkcmids = $customdata['bulkcmids'] ?? '';
        if (!empty($bulkcmids)) {
            $mform->addElement('hidden', 'bulkcmids', $bulkcmids);
            $mform->setType('bulkcmids', PARAM_SEQUENCE);
        }

        $mform->addElement('header', 'general', get_string('uploadicon', 'local_courseicons', $modname));

        $filemanageropts = [
            'subdirs' => 0,
            'maxbytes' => 2097152,
            'maxfiles' => 1,
            // GIF support added.
            'accepted_types' => ['.svg', '.png', '.jpg', '.jpeg', '.gif'],
        ];

        $mform->addElement(
            'filemanager',
            'iconfile_filemanager',
            get_string('uploadicon', 'local_courseicons', $modname),
            null,
            $filemanageropts
        );

        $mform->addElement('checkbox', 'deleteicon', get_string('deleteicon', 'local_courseicons'));

        $this->add_action_buttons(true, get_string('savechanges', 'local_courseicons'));
    }
}
