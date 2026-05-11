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
 * Core library functions and hooks for local_courseicons.
 *
 * @package    local_courseicons
 * @copyright  2026 Yeison Díaz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

defined('MOODLE_INTERNAL') || die();

/**
 * Extends the global navigation.
 * We use this global hook to guarantee our JS is injected on the course page,
 * regardless of how the theme builds its menus.
 *
 * @param \global_navigation $navigation The global navigation object.
 */
function local_courseicons_extend_navigation(global_navigation $navigation): void {
    global $PAGE, $COURSE, $DB;

    // Solo inyectar si estamos dentro del contexto de un curso real.
    // Hemos eliminado la restricción estricta de pagetype para que funcione en cualquier formato de curso.
    if (empty($COURSE->id) || $COURSE->id <= 1) {
        return;
    }

    // Prevenir inyección múltiple en la misma página.
    static $jsloaded = false;
    if ($jsloaded) {
        return;
    }
    $jsloaded = true;

    $records = $DB->get_records('local_courseicons', ['courseid' => $COURSE->id]);
    
    if (!empty($records)) {
        $icondata = [];
        $fs = get_file_storage();
        
        foreach ($records as $record) {
            $modcontext = context_module::instance($record->cmid);
            $files = $fs->get_area_files($modcontext->id, 'local_courseicons', 'activityicon', 0, 'id', false);
            
            if (!empty($files)) {
                $file = reset($files);
                $filename = $file->get_filename();
                
                // Construcción segura de la URL sin romper los parámetros de Moodle.
                $murl = moodle_url::make_pluginfile_url(
                    $modcontext->id,
                    'local_courseicons',
                    'activityicon',
                    0,
                    '/',
                    $filename
                );
                
                // Agregamos la fecha de forma oficial a través de la API de Moodle para evadir la caché.
                $murl->param('t', $record->timemodified);

                $icondata[] = [
                    'cmid' => $record->cmid,
                    'url' => $murl->out(false),
                ];
            }
        }

        if (!empty($icondata)) {
            $PAGE->requires->js_call_amd('local_courseicons/swapper', 'init', [$icondata]);
        }
    }
}

/**
 * Extends the course navigation (Moodle 4.0+ secondary navigation).
 * This purely adds the "Customize activity icons" button for teachers.
 *
 * @param \navigation_node $navigation The navigation node to extend.
 * @param \stdClass $course The course object.
 * @param \context $context The context object.
 */
function local_courseicons_extend_navigation_course(
    navigation_node $navigation,
    stdClass $course,
    context $context
): void {
    if (has_capability('local/courseicons:manage', $context)) {
        $url = new moodle_url('/local/courseicons/manage.php', ['id' => $course->id]);
        $node = $navigation->add(
            get_string('manageicons', 'local_courseicons'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'local_courseicons_manage',
            new pix_icon('i/settings', '')
        );
        $node->showinflatnavigation = true;
    }
}

/**
 * Serves the custom activity icons files.
 *
 * @param stdClass $course The course object.
 * @param stdClass $cm The course module object.
 * @param context $context The context object.
 * @param string $filearea The file area.
 * @param array $args Extra arguments.
 * @param bool $forcedownload Whether to force download.
 * @param array $options Additional options.
 * @return bool
 */
function local_courseicons_pluginfile(
    $course,
    $cm,
    $context,
    $filearea,
    $args,
    $forcedownload,
    array $options = []
) {
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    if ($filearea !== 'activityicon') {
        return false;
    }

    require_course_login($course, true, $cm);

    $itemid = (int)array_shift($args);
    $filename = array_pop($args);

    if (!$filename) {
        return false;
    }

    $fs = get_file_storage();
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    
    $file = $fs->get_file($context->id, 'local_courseicons', $filearea, $itemid, $filepath, $filename);

    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}
