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
 * Injects CSS and Preloads globally.
 * Consolidates instant loading by eliminating transitions or unnecessary hidden code.
 *
 * @return string
 */
function local_courseicons_standard_head_html(): string {
    global $COURSE, $DB;
    static $already_injected = false;

    if ($already_injected || empty($COURSE->id) || $COURSE->id <= 1) {
        return '';
    }

    $records = $DB->get_records('local_courseicons', ['courseid' => $COURSE->id]);
    if (empty($records)) {
        return '';
    }

    $already_injected = true;
    $fs = get_file_storage();
    $html = "\n<!-- local_courseicons CSS & Preloads -->\n";
    $css = "<style type=\"text/css\">\n";

    foreach ($records as $record) {
        $modcontext = context_module::instance($record->cmid, IGNORE_MISSING);
        if (!$modcontext) {
            continue;
        }

        $files = $fs->get_area_files($modcontext->id, 'local_courseicons', 'activityicon', 0, 'id', false);
        
        if (!empty($files)) {
            $file = reset($files);
            $murl = moodle_url::make_pluginfile_url($modcontext->id, 'local_courseicons', 'activityicon', 0, '/', $file->get_filename());
            $murl->param('t', $record->timemodified);
            $url = $murl->out(false);
            
            // Force early download.
            $html .= "<link rel=\"preload\" href=\"{$url}\" as=\"image\">\n";
            
            // Instant CSS visual replacement (Browser native).
            $css .= "
/* Activity {$record->cmid} - Transparent backgrounds (Course page and activity interior) */
.activity-item[data-id=\"{$record->cmid}\"] .activityiconcontainer,
#module-{$record->cmid} .activityiconcontainer,
li.subtile[data-id=\"{$record->cmid}\"] .tile-icon,
body.cmid-{$record->cmid} .page-header-image .activityiconcontainer,
body.cmid-{$record->cmid} .page-header-headings .activityiconcontainer {
    background-color: transparent !important;
    background: transparent !important;
    box-shadow: none !important;
    border: none !important;
}

/* FIX 1.27: Strict selectors so they don't bleed to Action Menus or Group Menus */
.activity-item[data-id=\"{$record->cmid}\"] .activityiconcontainer img,
#module-{$record->cmid} .activityiconcontainer img,
#module-{$record->cmid} .activityinstance > a > img.activityicon,
#module-{$record->cmid} .activityinstance > a > img.icon {
    content: url('{$url}') !important;
    object-fit: contain !important;
    width: 32px !important;
    height: 32px !important;
    filter: none !important;
    border-radius: 0 !important;
}

/* FIX 1.28: Large icon in the header INSIDE the activity page */
body.cmid-{$record->cmid} .page-header-image img,
body.cmid-{$record->cmid} .page-header-headings img.activityicon {
    content: url('{$url}') !important;
    object-fit: contain !important;
    width: 50px !important;
    height: 50px !important;
    filter: none !important;
    border-radius: 0 !important;
}

li.subtile[data-id=\"{$record->cmid}\"] .tile-icon img {
    content: url('{$url}') !important;
    object-fit: contain !important;
    width: 100% !important;
    height: 110px !important;
    transform: scale(1.4) !important;
    padding: 0 !important;
    margin: 0 !important;
    max-width: none !important;
}

li.subtile[data-id=\"{$record->cmid}\"] .tile-icon {
    display: flex !important;
    justify-content: center !important;
    align-items: center !important;
    width: 100% !important;
}
";
        }
    }

    $css .= "</style>\n";
    return $html . $css;
}

/**
 * Compatibility alias to ensure injection in different Moodle versions.
 *
 * @return string
 */
function local_courseicons_before_standard_html_head(): string {
    return local_courseicons_standard_head_html();
}

/**
 * Extends the global navigation.
 * We use this global hook to guarantee our JS is injected on the course page.
 */
function local_courseicons_extend_navigation(global_navigation $navigation): void {
    global $PAGE, $COURSE, $DB;

    if (empty($COURSE->id) || $COURSE->id <= 1) {
        return;
    }

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
            $modcontext = context_module::instance($record->cmid, IGNORE_MISSING);
            if (!$modcontext) {
                continue;
            }

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
                
                $murl->param('t', $record->timemodified);

                $icondata[] = [
                    'cmid' => $record->cmid,
                    'url' => $murl->out(false),
                ];
            }
        }

        if (!empty($icondata)) {
            // Send data to JS for deep DOM manipulation.
            $PAGE->requires->js_call_amd('local_courseicons/swapper', 'init', [$icondata]);
        }
    }
}

/**
 * Extends the course navigation (Moodle 4.0+ secondary navigation).
 * This purely adds the "Customize activity icons" button for teachers.
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
