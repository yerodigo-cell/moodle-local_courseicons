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
$action = optional_param('action', '', PARAM_ALPHANUMEXT);

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

if ($action === 'delete') {
    require_sesskey();
    $delcmid = required_param('delcmid', PARAM_INT);
    $DB->delete_records('local_courseicons', ['cmid' => $delcmid]);
    $modcontext = context_module::instance($delcmid);
    $fs = get_file_storage();
    $fs->delete_area_files($modcontext->id, 'local_courseicons', 'activityicon', 0);

    cache::make('local_courseicons', 'course_css')->delete($course->id);

    redirect(
        $url,
        get_string('successdeleted', 'local_courseicons'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
} else if ($action === 'bulkdelete' || $action === 'bulkuploadform') {
    require_sesskey();
    $cmids = optional_param_array('cmids', [], PARAM_INT);
    if (!empty($cmids)) {
        $fs = get_file_storage();
        foreach ($cmids as $delcmid) {
            $DB->delete_records('local_courseicons', ['cmid' => $delcmid]);
            $modcontext = context_module::instance($delcmid);
            $fs->delete_area_files($modcontext->id, 'local_courseicons', 'activityicon', 0);
        }

        cache::make('local_courseicons', 'course_css')->delete($course->id);

        redirect(
            $url,
            get_string('successdeleted', 'local_courseicons'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } else {
        redirect($url);
    }
}

$bulkcmids = optional_param('bulkcmids', '', PARAM_SEQUENCE);
if ($action === 'bulkuploadform' && !empty($cmids) && is_array($cmids)) {
    $bulkcmids = implode(',', $cmids);
}

if ($cmid > 0 || !empty($bulkcmids)) {
    if (!empty($bulkcmids)) {
        $cmids_arr = explode(',', $bulkcmids);
        $modcontext = context_module::instance($cmids_arr[0]);
        $modname = get_string('uploadiconbulk', 'local_courseicons', count($cmids_arr));
    } else {
        $cm = get_coursemodule_from_id('', $cmid, $course->id, false, MUST_EXIST);
        $modcontext = context_module::instance($cmid);
        $modname = $cm->name;
    }

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
        'bulkcmids' => $bulkcmids ?? '',
        'iconfile_filemanager' => $draftitemid,
    ];

    $mform = new icon_upload_form($url, [
        'courseid' => $course->id,
        'cmid' => $cmid,
        'bulkcmids' => $bulkcmids ?? '',
        'modname' => $modname,
    ]);

    $mform->set_data($formdata);

    if ($mform->is_cancelled()) {
        redirect($url);
    } else if ($data = $mform->get_data()) {
        if (!empty($data->deleteicon)) {
            if (!empty($bulkcmids)) {
                $cmids_arr = explode(',', $bulkcmids);
                $fs = get_file_storage();
                foreach ($cmids_arr as $delcmid) {
                    $DB->delete_records('local_courseicons', ['cmid' => $delcmid]);
                    $delcontext = context_module::instance($delcmid);
                    $fs->delete_area_files($delcontext->id, 'local_courseicons', 'activityicon', 0);
                }
            } else {
                $DB->delete_records('local_courseicons', ['cmid' => $cmid]);
                $fs = get_file_storage();
                $fs->delete_area_files($modcontext->id, 'local_courseicons', 'activityicon', 0);
            }

            cache::make('local_courseicons', 'course_css')->delete($course->id);

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

            $fs = get_file_storage();

            if (!empty($bulkcmids)) {
                $cmids_arr = explode(',', $bulkcmids);
                $files = $fs->get_area_files($modcontext->id, 'local_courseicons', 'activityicon', 0, 'id', false);
                $sourcefile = !empty($files) ? reset($files) : null;

                foreach ($cmids_arr as $savecmid) {
                    $savecontext = context_module::instance($savecmid);
                    
                    if ($savecmid != $cmids_arr[0] && $sourcefile) {
                        $fs->delete_area_files($savecontext->id, 'local_courseicons', 'activityicon', 0);
                        $filerecord = [
                            'contextid' => $savecontext->id,
                            'component' => 'local_courseicons',
                            'filearea'  => 'activityicon',
                            'itemid'    => 0,
                            'filepath'  => '/',
                            'filename'  => $sourcefile->get_filename(),
                        ];
                        $fs->create_file_from_storedfile($filerecord, $sourcefile);
                    }

                    $record = new stdClass();
                    $record->courseid = $course->id;
                    $record->cmid = $savecmid;
                    $record->timemodified = time();

                    if ($existing = $DB->get_record('local_courseicons', ['cmid' => $savecmid])) {
                        $record->id = $existing->id;
                        $DB->update_record('local_courseicons', $record);
                    } else {
                        $record->timecreated = time();
                        $DB->insert_record('local_courseicons', $record);
                    }
                }
            } else {
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
            }

            cache::make('local_courseicons', 'course_css')->delete($course->id);

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

if (($cmid > 0 || !empty($bulkcmids)) && isset($mform)) {
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

    $modnames = [];
    foreach ($modinfo->cms as $module) {
        $ignoredmodules = ['label', 'subsection', 'qbank', 'questionbank', 'course_questionbank'];
        if (in_array($module->modname, $ignoredmodules) || !$module->has_view()) {
            continue;
        }
        $modnames[$module->modname] = get_string('pluginname', 'mod_' . $module->modname);
    }
    asort($modnames);

    echo html_writer::start_div('row mb-3');
    echo html_writer::start_div('col-md-6 col-sm-12 mb-2');
    echo html_writer::empty_tag('input', [
        'type' => 'text',
        'id' => 'courseicons-search',
        'class' => 'form-control',
        'placeholder' => get_string('searchactivities', 'local_courseicons')
    ]);
    echo html_writer::end_div();

    echo html_writer::start_div('col-md-6 col-sm-12');
    $filteroptions = ['all' => get_string('alltypes', 'local_courseicons')] + $modnames;
    echo html_writer::select($filteroptions, 'filter', 'all', false, [
        'id' => 'courseicons-filter',
        'class' => 'form-control custom-select form-select'
    ]);
    echo html_writer::end_div();
    echo html_writer::end_div();

    $table = new html_table();
    $table->head = [
        '<input type="checkbox" id="courseicons-select-all" title="' . get_string('selectall') . '">',
        get_string('modulename', 'local_courseicons'),
        get_string('preview', 'local_courseicons'),
        get_string('currenticon', 'local_courseicons'),
        get_string('actions', 'local_courseicons'),
    ];
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

        $hascustom = isset($customicons[$module->id]) ? 1 : 0;
        $checkboxhtml = html_writer::empty_tag('input', [
            'type' => 'checkbox',
            'name' => 'cmids[]',
            'value' => $module->id,
            'class' => 'courseicons-bulk-checkbox',
            'data-hascustom' => $hascustom,
        ]);

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

                $delurl = new moodle_url('/local/courseicons/manage.php', [
                    'id' => $course->id,
                    'action' => 'delete',
                    'delcmid' => $module->id,
                    'sesskey' => sesskey(),
                ]);

                $delicon = $OUTPUT->pix_icon('t/delete', get_string('delete'));
                $delaction = html_writer::link($delurl, $delicon, [
                    'class' => 'courseicons-delete-single ms-2',
                    'data-confirm' => get_string('deleteiconconfirm', 'local_courseicons'),
                ]);
                $previewhtml .= $delaction;
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
        $modname_cell = $modicon . ' ' . format_string($module->name);

        $row = new html_table_row([$checkboxhtml, $modname_cell, $previewhtml, $status, $actionlink]);
        $row->attributes['class'] = 'courseicons-row';
        $row->attributes['data-modname'] = $module->modname;
        $row->attributes['data-name'] = format_string($module->name);
        $table->data[] = $row;
    }

    echo html_writer::start_tag('form', [
        'action' => new moodle_url('/local/courseicons/manage.php'),
        'method' => 'post',
        'id' => 'courseicons-bulk-form',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $course->id]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'id' => 'courseicons-bulk-action', 'value' => 'bulkdelete']);

    echo html_writer::table($table);

    echo html_writer::start_div('mt-3');
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'value' => get_string('bulkupload', 'local_courseicons'),
        'class' => 'btn btn-primary me-2',
        'id' => 'courseicons-bulk-upload',
        'disabled' => 'disabled',
    ]);

    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'value' => get_string('bulkdelete', 'local_courseicons'),
        'class' => 'btn btn-secondary',
        'id' => 'courseicons-bulk-submit',
        'data-confirm' => get_string('deleteselectedconfirm', 'local_courseicons'),
        'disabled' => 'disabled',
    ]);
    echo html_writer::end_div();

    echo html_writer::end_tag('form');

    $PAGE->requires->js_call_amd('local_courseicons/manage', 'init');
}

echo $OUTPUT->footer();
