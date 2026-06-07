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

    $selectors_bg = [];
    $selectors_icon = [];
    $selectors_header_icon = [];
    $selectors_tile_icon = [];
    $selectors_tile_container = [];
    $dynamic_content = '';

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
            
            $cmid = $record->cmid;
            
            // Group selectors for static rules.
            $selectors_bg[] = ".path-local-courseicons .activity-item[data-id=\"{$cmid}\"] .activityiconcontainer";
            $selectors_bg[] = ".path-local-courseicons #module-{$cmid} .activityiconcontainer";
            $selectors_bg[] = ".path-local-courseicons li.subtile[data-id=\"{$cmid}\"] .tile-icon";
            $selectors_bg[] = ".path-local-courseicons.cmid-{$cmid} .page-header-image .activityiconcontainer";
            $selectors_bg[] = ".path-local-courseicons.cmid-{$cmid} .page-header-headings .activityiconcontainer";

            $sel_icon = [
                ".path-local-courseicons .activity-item[data-id=\"{$cmid}\"] .activityiconcontainer img",
                ".path-local-courseicons #module-{$cmid} .activityiconcontainer img",
                ".path-local-courseicons #module-{$cmid} .activityinstance > a > img.activityicon",
                ".path-local-courseicons #module-{$cmid} .activityinstance > a > img.icon"
            ];
            $selectors_icon = array_merge($selectors_icon, $sel_icon);

            $sel_header = [
                ".path-local-courseicons.cmid-{$cmid} .page-header-image img",
                ".path-local-courseicons.cmid-{$cmid} .page-header-headings img.activityicon"
            ];
            $selectors_header_icon = array_merge($selectors_header_icon, $sel_header);

            $sel_tile = [".path-local-courseicons li.subtile[data-id=\"{$cmid}\"] .tile-icon img"];
            $selectors_tile_icon = array_merge($selectors_tile_icon, $sel_tile);

            $selectors_tile_container[] = ".path-local-courseicons li.subtile[data-id=\"{$cmid}\"] .tile-icon";

            // Dynamic rule (unique URL per cmid).
            $dynamic_content .= implode(', ', $sel_icon) . ", " . implode(', ', $sel_header) . ", " . implode(', ', $sel_tile) . " { content: url('{$url}') !important; }\n";
        }
    }

    if (!empty($selectors_bg)) {
        // Output grouped static rules once.
        $css .= implode(",\n", $selectors_bg) . " {\n    background-color: transparent !important;\n    background: transparent !important;\n    box-shadow: none !important;\n    border: none !important;\n}\n\n";
        $css .= implode(",\n", $selectors_icon) . " {\n    object-fit: contain !important;\n    width: 32px !important;\n    height: 32px !important;\n    filter: none !important;\n    border-radius: 0 !important;\n}\n\n";
        $css .= implode(",\n", $selectors_header_icon) . " {\n    object-fit: contain !important;\n    width: 50px !important;\n    height: 50px !important;\n    filter: none !important;\n    border-radius: 0 !important;\n}\n\n";
        $css .= implode(",\n", $selectors_tile_icon) . " {\n    object-fit: contain !important;\n    width: 100% !important;\n    height: 110px !important;\n    transform: scale(1.4) !important;\n    padding: 0 !important;\n    margin: 0 !important;\n    max-width: none !important;\n}\n\n";
        $css .= implode(",\n", $selectors_tile_container) . " {\n    display: flex !important;\n    justify-content: center !important;\n    align-items: center !important;\n    width: 100% !important;\n}\n\n";
        $css .= $dynamic_content;
    }

    $css .= "</style>\n";
    return $html . $css;
}

/**
 * Compatibility alias to ensure injection in older Moodle versions.
 *
 * @return string
 */
function local_courseicons_before_standard_html_head(): string {
    global $CFG;
    // Prevent double execution in Moodle >= 4.4, which uses the Hooks API instead.
    if (!empty($CFG->branch) && $CFG->branch >= '404') {
        return '';
    }
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

    // Add plugin namespace class to body to satisfy Moodle CSS namespacing requirements.
    $PAGE->add_body_class('path-local-courseicons');

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
