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
 * Manage global custom icons for course activities.
 *
 * @package    local_courseicons
 * @copyright  2026 Yeison Díaz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

require_once('../../config.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/adminlib.php');

use local_courseicons\form\global_icon_upload_form;

admin_externalpage_setup('local_courseicons');

$modname = optional_param('modname', '', PARAM_ALPHANUMEXT);
$action = optional_param('action', '', PARAM_ALPHANUMEXT);

$context = context_system::instance();

$url = new moodle_url('/local/courseicons/globalmanage.php');

if ($action === 'delete' && !empty($modname)) {
    require_sesskey();
    if ($globalrecord = $DB->get_record('local_courseicons_global', ['modname' => $modname])) {
        $DB->delete_records('local_courseicons_global', ['id' => $globalrecord->id]);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'local_courseicons', 'globalicon', $globalrecord->id);
    }
    cache::make('local_courseicons', 'course_css')->purge();

    redirect(
        $url,
        get_string('successdeleted', 'local_courseicons'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
} else if ($action === 'bulkdelete') {
    require_sesskey();
    $modnames = optional_param_array('modnames', [], PARAM_ALPHANUMEXT);
    if (!empty($modnames)) {
        $fs = get_file_storage();
        foreach ($modnames as $mname) {
            if ($globalrecord = $DB->get_record('local_courseicons_global', ['modname' => $mname])) {
                $DB->delete_records('local_courseicons_global', ['id' => $globalrecord->id]);
                $fs->delete_area_files($context->id, 'local_courseicons', 'globalicon', $globalrecord->id);
            }
        }

        cache::make('local_courseicons', 'course_css')->purge();

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

if (!empty($modname)) {
    $globalrecord = $DB->get_record('local_courseicons_global', ['modname' => $modname]);
    if (!$globalrecord) {
        $globalrecord = new \stdClass();
        $globalrecord->modname = $modname;
        $globalrecord->timecreated = time();
        $globalrecord->timemodified = time();
        $globalrecord->id = $DB->insert_record('local_courseicons_global', $globalrecord);
    }
    
    $filearea = 'globalicon';
    $fileitemid = $globalrecord->id;

    $draftitemid = file_get_submitted_draft_itemid('iconfile_filemanager');
    $fileopts = ['subdirs' => 0, 'maxfiles' => 1];
    file_prepare_draft_area(
        $draftitemid,
        $context->id,
        'local_courseicons',
        $filearea,
        $fileitemid,
        $fileopts
    );

    $formdata = [
        'modname' => $modname,
        'iconfile_filemanager' => $draftitemid,
    ];

    $mform = new global_icon_upload_form($url, [
        'modname' => $modname,
    ]);

    $mform->set_data($formdata);

    if ($mform->is_cancelled()) {
        redirect($url);
    } else if ($data = $mform->get_data()) {
        if (!empty($data->deleteicon)) {
            $DB->delete_records('local_courseicons_global', ['id' => $globalrecord->id]);
            $fs = get_file_storage();
            $fs->delete_area_files($context->id, 'local_courseicons', 'globalicon', $globalrecord->id);

            cache::make('local_courseicons', 'course_css')->purge();

            redirect(
                $url,
                get_string('successdeleted', 'local_courseicons'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        } else {
            $activetab = $data->active_tab ?? 'upload';
            $libraryicon = $data->library_icon ?? '';
            $saved = false;

            if ($activetab === 'library' && !empty($libraryicon)) {
                $libraryicon = clean_param($libraryicon, PARAM_FILE);
                $filepath = $CFG->dirroot . '/local/courseicons/pix/library/' . $libraryicon;
                $realbase = realpath($CFG->dirroot . '/local/courseicons/pix/library');
                $realfile = realpath($filepath);

                if ($realfile !== false && strpos($realfile, $realbase) === 0 && file_exists($realfile)) {
                    $fs = get_file_storage();
                    $fs->delete_area_files($context->id, 'local_courseicons', $filearea, $fileitemid);

                    $filerecord = [
                        'contextid' => $context->id,
                        'component' => 'local_courseicons',
                        'filearea'  => $filearea,
                        'itemid'    => $fileitemid,
                        'filepath'  => '/',
                        'filename'  => $libraryicon,
                    ];
                    $fs->create_file_from_pathname($filerecord, $realfile);
                    $saved = true;
                }
            }

            if (!$saved) {
                $fileopts = ['subdirs' => 0, 'maxfiles' => 1];
                file_save_draft_area_files(
                    $data->iconfile_filemanager,
                    $context->id,
                    'local_courseicons',
                    $filearea,
                    $fileitemid,
                    $fileopts
                );
            }

            $fs = get_file_storage();
            \local_courseicons\image_optimizer::optimize_area_files(
                $context->id,
                'local_courseicons',
                $filearea,
                $fileitemid
            );

            $globalrecord->timemodified = time();
            $DB->update_record('local_courseicons_global', $globalrecord);

            cache::make('local_courseicons', 'course_css')->purge();

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
$headingicon = $OUTPUT->pix_icon('icon', '', 'local_courseicons', ['class' => 'icon mr-2', 'style' => 'width: 40px; height: 40px; vertical-align: middle; margin-right: 10px; margin-bottom: 5px;']);
echo $OUTPUT->heading($headingicon . ' ' . get_string('globalmanage', 'local_courseicons'));

if (!empty($modname) && isset($mform)) {
    echo $OUTPUT->box_start('generalbox');
    $mform->display();
    echo $OUTPUT->box_end();
} else {
    echo html_writer::tag('p', get_string('globaliconsdesc', 'local_courseicons'), ['class' => 'text-muted']);

    echo html_writer::start_div('alert alert-info mt-3 mb-4');
    echo html_writer::tag('h4', get_string('iconhierarchy', 'local_courseicons'));
    echo html_writer::tag('div', get_string('iconhierarchy_desc', 'local_courseicons'));
    echo html_writer::end_div();

    $modules = $DB->get_records('modules', ['visible' => 1]);
    $modnames = [];
    foreach ($modules as $module) {
        $ignoredmodules = ['label', 'subsection', 'qbank', 'questionbank', 'course_questionbank'];
        if (in_array($module->name, $ignoredmodules)) {
            continue;
        }
        $modnames[$module->name] = get_string('pluginname', 'mod_' . $module->name);
    }
    asort($modnames);

    $globalicons = $DB->get_records('local_courseicons_global', [], '', 'modname, id, timemodified');

    $table = new html_table();
    $table->head = [
        html_writer::empty_tag('input', [
            'type' => 'checkbox',
            'id' => 'courseicons-select-all',
            'title' => get_string('selectall'),
            'class' => 'm-0',
        ]),
        get_string('activitytypes', 'local_courseicons'),
        get_string('preview', 'local_courseicons'),
        get_string('currenticon', 'local_courseicons'),
        html_writer::span(get_string('actions'), 'sr-only'),
    ];
    $table->attributes['class'] = 'generaltable table table-hover align-middle';

    foreach ($modnames as $mname => $pluginname) {
        $previewhtml = '';
        
        $actionmenu = new action_menu();
        $actionmenu->set_menu_trigger($OUTPUT->pix_icon('i/moremenu', get_string('actions')));
        $actionmenu->set_boundary('window');

        $editurl = new moodle_url('/local/courseicons/globalmanage.php', [
            'modname' => $mname,
        ]);

        $actionmenu->add(new action_menu_link_secondary(
            $editurl,
            new pix_icon('t/edit', get_string('edit')),
            get_string('edit')
        ));

        $hascustom = 0;
        if (isset($globalicons[$mname])) {
            $hascustom = 1;
            $grecord = $globalicons[$mname];
            $fs = get_file_storage();
            $files = $fs->get_area_files($context->id, 'local_courseicons', 'globalicon', $grecord->id, 'id', false);

            if (!empty($files)) {
                $file = reset($files);
                $murl = moodle_url::make_pluginfile_url(
                    $context->id,
                    'local_courseicons',
                    'globalicon',
                    $grecord->id,
                    '/',
                    $file->get_filename()
                );
                $murl->param('t', $grecord->timemodified);
                $previewhtml = html_writer::empty_tag('img', [
                    'src' => $murl->out(false),
                    'alt' => get_string('globalicons', 'local_courseicons'),
                    'style' => 'width: 36px; height: 36px; object-fit: contain;',
                ]);

                $delurl = new moodle_url('/local/courseicons/globalmanage.php', [
                    'action' => 'delete',
                    'modname' => $mname,
                    'sesskey' => sesskey(),
                ]);

                $actionmenu->add(new action_menu_link_secondary(
                    $delurl,
                    new pix_icon('t/delete', get_string('delete')),
                    get_string('delete'),
                    ['data-confirm' => get_string('deleteiconconfirm', 'local_courseicons'), 'class' => 'text-danger']
                ));
            }
        } else {
            $previewhtml = $OUTPUT->pix_icon(
                'monologo',
                '',
                $mname,
                ['style' => 'width: 32px; height: 32px; opacity: 0.4;']
            );
        }

        $statushtml = '';
        if ($hascustom) {
            $statushtml = html_writer::span(get_string('customized', 'local_courseicons'), 'badge bg-success text-white');
        } else {
            $statushtml = html_writer::span(get_string('default', 'local_courseicons'), 'badge bg-secondary text-white');
        }

        $checkboxhtml = html_writer::empty_tag('input', [
            'type' => 'checkbox',
            'name' => 'modnames[]',
            'value' => $mname,
            'class' => 'courseicons-bulk-checkbox m-0',
            'data-hascustom' => $hascustom,
        ]);

        $actionhtml = $OUTPUT->render($actionmenu);

        $modicon = $OUTPUT->pix_icon('monologo', '', $mname, ['class' => 'icon']);
        $modnamecell = $modicon . ' ' . format_string($pluginname);

        $cellaction = new html_table_cell($actionhtml);
        $cellaction->attributes['class'] = 'text-end';

        $row = new html_table_row([$checkboxhtml, $modnamecell, $previewhtml, $statushtml, $cellaction]);
        $table->data[] = $row;
    }

    echo html_writer::start_tag('form', [
        'action' => new moodle_url('/local/courseicons/globalmanage.php'),
        'method' => 'post',
        'id' => 'courseicons-bulk-form',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'action',
        'id' => 'courseicons-bulk-action',
        'value' => 'bulkdelete',
    ]);

    echo html_writer::table($table);

    echo html_writer::start_div('mt-3 mb-5 courseicons-floating-buttons');
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
