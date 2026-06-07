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
 * Manage custom icons for course activities.
 *
 * @package    local_courseicons
 * @copyright  2026 Yeison Díaz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

require_once('../../config.php');
require_once($CFG->libdir . '/filelib.php');

use local_courseicons\form\icon_upload_form;

$courseid = required_param('id', PARAM_INT);
$cmid = optional_param('cmid', 0, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

require_login($course);
$context = context_course::instance($course->id);
require_capability('local/courseicons:manage', $context);

$url = new moodle_url('/local/courseicons/manage.php', ['id' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('manageicons', 'local_courseicons'));
$PAGE->set_heading($course->fullname);

if ($cmid > 0) {
    $cm = get_coursemodule_from_id('', $cmid, $course->id, false, MUST_EXIST);
    $modcontext = context_module::instance($cmid);

    $draftitemid = file_get_submitted_draft_itemid('iconfile_filemanager');
    $fileopts = ['subdirs' => 0, 'maxfiles' => 1];
    file_prepare_draft_area(
        $draftitemid,
        $modcontext->id,
        'local_courseicons',
        'activityicon',
        0,
        $fileopts
    );

    $formdata = [
        'id' => $course->id,
        'cmid' => $cmid,
        'iconfile_filemanager' => $draftitemid,
    ];

    $mform = new icon_upload_form($url, [
        'courseid' => $course->id,
        'cmid' => $cmid,
        'modname' => $cm->name,
    ]);

    $mform->set_data($formdata);

    if ($mform->is_cancelled()) {
        redirect($url);
    } else if ($data = $mform->get_data()) {
        if (!empty($data->deleteicon)) {
            $DB->delete_records('local_courseicons', ['cmid' => $cmid]);
            $fs = get_file_storage();
            $fs->delete_area_files($modcontext->id, 'local_courseicons', 'activityicon', 0);

            redirect(
                $url,
                get_string('successdeleted', 'local_courseicons'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        } else {
            $fileopts = ['subdirs' => 0, 'maxfiles' => 1];
            file_save_draft_area_files(
                $data->iconfile_filemanager,
                $modcontext->id,
                'local_courseicons',
                'activityicon',
                0,
                $fileopts
            );

            $record = new stdClass();
            $record->courseid = $course->id;
            $record->cmid = $cmid;
            $record->timemodified = time();

            if ($existing = $DB->get_record('local_courseicons', ['cmid' => $cmid])) {
                $record->id = $existing->id;
                $DB->update_record('local_courseicons', $record);
            } else {
                $record->timecreated = time();
                $DB->insert_record('local_courseicons', $record);
            }

            redirect(
                $url,
                get_string('successupdated', 'local_courseicons'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manageicons', 'local_courseicons'));

if ($cmid > 0 && isset($mform)) {
    echo $OUTPUT->box_start('generalbox');
    $mform->display();
    echo $OUTPUT->box_end();
} else {
    $modinfo = get_fast_modinfo($course);

    // Get full records to have the modification date (timemodified).
    $customicons = $DB->get_records('local_courseicons', ['courseid' => $course->id], '', 'cmid, id, timemodified');

    // PHPCS: Extract strings outside loops to optimize memory.
    $strcustomized = get_string('customized', 'local_courseicons');
    $strdefault = get_string('default', 'local_courseicons');
    $strediticon = get_string('editicon', 'local_courseicons');

    $table = new html_table();
    $table->head = [
        get_string('modulename', 'local_courseicons'),
        get_string('preview', 'local_courseicons'),
        get_string('currenticon', 'local_courseicons'),
        get_string('actions', 'local_courseicons'),
    ];
    // Add align-middle so the image and buttons are vertically centered.
    $table->attributes['class'] = 'generaltable table table-striped align-middle';

    foreach ($modinfo->cms as $module) {
        // Ignore labels, subsections (M4.3+), question banks (M5.x+) or structural items.
        $ignoredmodules = ['label', 'subsection', 'qbank', 'questionbank', 'course_questionbank'];
        if (in_array($module->modname, $ignoredmodules) || !$module->has_view()) {
            continue;
        }

        $editurl = new moodle_url('/local/courseicons/manage.php', [
            'id' => $course->id,
            'cmid' => $module->id,
        ]);

        $actionlink = html_writer::link(
            $editurl,
            $strediticon,
            ['class' => 'btn btn-sm btn-primary']
        );

        $previewhtml = '';

        if (isset($customicons[$module->id])) {
            $record = $customicons[$module->id];
            $status = html_writer::span($strcustomized, 'badge badge-success bg-success text-white');

            // Build the preview of the uploaded image.
            $modcontext = context_module::instance($module->id);
            $fs = get_file_storage();
            $files = $fs->get_area_files($modcontext->id, 'local_courseicons', 'activityicon', 0, 'id', false);

            if (!empty($files)) {
                $file = reset($files);
                $murl = moodle_url::make_pluginfile_url(
                    $modcontext->id,
                    'local_courseicons',
                    'activityicon',
                    0,
                    '/',
                    $file->get_filename()
                );
                $murl->param('t', $record->timemodified); // To avoid caching.
                $previewhtml = html_writer::empty_tag('img', [
                    'src' => $murl->out(false),
                    'alt' => get_string('customiconpreview', 'local_courseicons'),
                    'style' => 'width: 36px; height: 36px; object-fit: contain;',
                ]);
            }
        } else {
            $status = html_writer::span($strdefault, 'badge badge-secondary bg-secondary text-white');

            // Show the default icon slightly opaque.
            $previewhtml = $OUTPUT->pix_icon(
                'monologo',
                '',
                $module->modname,
                ['style' => 'width: 32px; height: 32px; opacity: 0.4;']
            );
        }

        $modicon = $OUTPUT->pix_icon('monologo', '', $module->modname, ['class' => 'icon']);
        $modname = $modicon . ' ' . format_string($module->name);

        // Add the new $previewhtml variable to the row.
        $table->data[] = [$modname, $previewhtml, $status, $actionlink];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
