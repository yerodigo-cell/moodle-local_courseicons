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



/**
 * Injects CSS and Preloads globally.
 * Consolidates instant loading by eliminating transitions or unnecessary hidden code.
 *
 * @return string
 */
function local_courseicons_standard_head_html(): string {
    global $COURSE, $DB;
    static $alreadyinjected = false;

    if ($alreadyinjected || empty($COURSE->id) || $COURSE->id <= 1) {
        return '';
    }

    $cache = cache::make('local_courseicons', 'course_css');
    $cachedhtml = $cache->get($COURSE->id);
    if ($cachedhtml !== false) {
        $alreadyinjected = true;
        return $cachedhtml === 'empty' ? '' : $cachedhtml;
    }

    $records = $DB->get_records('local_courseicons', ['courseid' => $COURSE->id]);
    $defaults = $DB->get_records('local_courseicons_def', ['courseid' => $COURSE->id]);
    if (empty($records) && empty($defaults)) {
        $cache->set($COURSE->id, 'empty');
        return '';
    }

    $alreadyinjected = true;
    $fs = get_file_storage();
    $css = "\n<!-- local_courseicons CSS -->\n<style type=\"text/css\">\n";

    $selectorsbg = [];
    $selectorsicon = [];
    $selectorsheadericon = [];
    $selectorstileicon = [];
    $selectorstilecontainer = [];
    $dynamiccontent = '';

    // Process defaults first (so individual overrides them if they have same specificity).
    foreach ($defaults as $defrecord) {
        $coursecontext = context_course::instance($COURSE->id);
        $files = $fs->get_area_files($coursecontext->id, 'local_courseicons', 'defaulticon', $defrecord->id, 'id', false);
        if (!empty($files)) {
            $file = reset($files);
            if ($file->get_filesize() <= 102400) {
                $mimetype = $file->get_mimetype();
                $base64 = base64_encode($file->get_content());
                $url = "data:{$mimetype};base64,{$base64}";
            } else {
                $murl = moodle_url::make_pluginfile_url(
                    $coursecontext->id,
                    'local_courseicons',
                    'defaulticon',
                    $defrecord->id,
                    '/',
                    $file->get_filename()
                );
                $murl->param('t', $defrecord->timemodified);
                $url = $murl->out(false);
            }

            $modname = $defrecord->modname;

            // Group selectors for static rules.
            $selectorsbg[] = ".path-course-view .activity-item.modtype_{$modname} .activityiconcontainer, " .
                ".path-mod-{$modname} .activity-item.modtype_{$modname} .activityiconcontainer";
            $selectorsbg[] = ".path-course-view li.activity.{$modname} .activityiconcontainer";
            $selectorsbg[] = ".path-course-view li.subtile.{$modname} .tile-icon";
            $selectorsbg[] = ".path-mod-{$modname} .page-header-image .activityiconcontainer";
            $selectorsbg[] = ".path-mod-{$modname} .page-header-headings .activityiconcontainer";

            $selicon = [
                ".path-course-view .activity-item.modtype_{$modname} .activityiconcontainer img, " .
                ".path-mod-{$modname} .activity-item.modtype_{$modname} .activityiconcontainer img",
                ".path-course-view li.activity.{$modname} .activityiconcontainer img",
                ".path-course-view li.activity.{$modname} .activityinstance > a > img.activityicon",
                ".path-course-view li.activity.{$modname} .activityinstance > a > img.icon",
            ];
            $selectorsicon = array_merge($selectorsicon, $selicon);

            $selheader = [
                ".path-mod-{$modname} .page-header-image img",
                ".path-mod-{$modname} .page-header-headings img.activityicon",
            ];
            $selectorsheadericon = array_merge($selectorsheadericon, $selheader);

            $seltile = [".path-course-view li.subtile.{$modname} .tile-icon img"];
            $selectorstileicon = array_merge($selectorstileicon, $seltile);

            $selectorstilecontainer[] = ".path-course-view li.subtile.{$modname} .tile-icon";

            $dynamiccontent .= implode(', ', $selicon) . ", " . implode(', ', $selheader) .
                ", " . implode(', ', $seltile) . " { content: url('{$url}') !important; }\n";
        }
    }

    foreach ($records as $record) {
        $modcontext = context_module::instance($record->cmid, IGNORE_MISSING);
        if (!$modcontext) {
            continue;
        }

        $files = $fs->get_area_files($modcontext->id, 'local_courseicons', 'activityicon', 0, 'id', false);

        if (!empty($files)) {
            $file = reset($files);
            // Limit Base64 encoding to 100KB to avoid massive HTML payloads.
            if ($file->get_filesize() <= 102400) {
                $mimetype = $file->get_mimetype();
                $base64 = base64_encode($file->get_content());
                $url = "data:{$mimetype};base64,{$base64}";
            } else {
                $murl = moodle_url::make_pluginfile_url(
                    $modcontext->id,
                    'local_courseicons',
                    'activityicon',
                    0,
                    '/',
                    $file->get_filename()
                );
                $murl->param('t', $record->timemodified);
                $url = $murl->out(false);
            }

            $cmid = $record->cmid;

            // Group selectors for static rules.
            $selectorsbg[] = ".path-course-view .activity-item[data-id=\"{$cmid}\"] .activityiconcontainer, " .
                ".path-mod .activity-item[data-id=\"{$cmid}\"] .activityiconcontainer";
            $selectorsbg[] = ".path-course-view #module-{$cmid} .activityiconcontainer";
            $selectorsbg[] = ".path-course-view li.subtile#module-{$cmid} .tile-icon";
            $selectorsbg[] = ".path-course-view li.subtile[data-id=\"{$cmid}\"] .tile-icon";
            $selectorsbg[] = ".path-mod.cmid-{$cmid} .page-header-image .activityiconcontainer";
            $selectorsbg[] = ".path-mod.cmid-{$cmid} .page-header-headings .activityiconcontainer";

            $selicon = [
                ".path-course-view .activity-item[data-id=\"{$cmid}\"] .activityiconcontainer img, " .
                ".path-mod .activity-item[data-id=\"{$cmid}\"] .activityiconcontainer img",
                ".path-course-view #module-{$cmid} .activityiconcontainer img",
                ".path-course-view #module-{$cmid} .activityinstance > a > img.activityicon",
                ".path-course-view #module-{$cmid} .activityinstance > a > img.icon",
            ];
            $selectorsicon = array_merge($selectorsicon, $selicon);

            $selheader = [
                ".path-mod.cmid-{$cmid} .page-header-image img",
                ".path-mod.cmid-{$cmid} .page-header-headings img.activityicon",
            ];
            $selectorsheadericon = array_merge($selectorsheadericon, $selheader);

            $seltile = [
                ".path-course-view li.subtile#module-{$cmid} .tile-icon img",
                ".path-course-view li.subtile[data-id=\"{$cmid}\"] .tile-icon img",
            ];
            $selectorstileicon = array_merge($selectorstileicon, $seltile);

            $selectorstilecontainer[] = ".path-course-view li.subtile#module-{$cmid} .tile-icon";
            $selectorstilecontainer[] = ".path-course-view li.subtile[data-id=\"{$cmid}\"] .tile-icon";

            // Dynamic rule (unique URL per cmid).
            $dynamiccontent .= implode(', ', $selicon) . ", " . implode(', ', $selheader) .
                ", " . implode(', ', $seltile) . " { content: url('{$url}') !important; }\n";
        }
    }

    if (!empty($selectorsbg)) {
        // Output grouped static rules once.
        $css .= implode(",\n", $selectorsbg) . " {\n" .
            "    background-color: transparent !important;\n" .
            "    background: transparent !important;\n" .
            "    box-shadow: none !important;\n" .
            "    border: none !important;\n}\n\n";
        $css .= implode(",\n", $selectorsicon) . " {\n" .
            "    object-fit: contain !important;\n" .
            "    width: 32px !important;\n" .
            "    height: 32px !important;\n" .
            "    filter: none !important;\n" .
            "    border-radius: 0 !important;\n}\n\n";
        $css .= implode(",\n", $selectorsheadericon) . " {\n" .
            "    object-fit: contain !important;\n" .
            "    width: 50px !important;\n" .
            "    height: 50px !important;\n" .
            "    filter: none !important;\n" .
            "    border-radius: 0 !important;\n}\n\n";
        $css .= implode(",\n", $selectorstileicon) . " {\n" .
            "    object-fit: contain !important;\n" .
            "    width: 100% !important;\n" .
            "    height: 110px !important;\n" .
            "    transform: scale(1.4) !important;\n" .
            "    padding: 0 !important;\n" .
            "    margin: 0 !important;\n" .
            "    max-width: none !important;\n}\n\n";
        $css .= implode(",\n", $selectorstilecontainer) . " {\n" .
            "    display: flex !important;\n" .
            "    justify-content: center !important;\n" .
            "    align-items: center !important;\n" .
            "    width: 100% !important;\n}\n\n";
        $css .= $dynamiccontent;
    }

    $css .= "</style>\n";
    $finalhtml = $css;

    $cache->set($COURSE->id, $finalhtml);

    return $finalhtml;
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
 *
 * @param \global_navigation $navigation
 */
function local_courseicons_extend_navigation(global_navigation $navigation): void {
    global $PAGE, $COURSE, $DB;

    if (empty($COURSE->id) || $COURSE->id <= 1) {
        return;
    }

    // Add plugin namespace class to body to satisfy Moodle CSS namespacing requirements.

    static $jsloaded = false;
    if ($jsloaded) {
        return;
    }
    $jsloaded = true;

    $records = $DB->get_records('local_courseicons', ['courseid' => $COURSE->id]);
    $defaults = $DB->get_records('local_courseicons_def', ['courseid' => $COURSE->id]);

    if (!empty($records) || !empty($defaults)) {
        $icondata = [];
        $defaultdata = [];
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

        $coursecontext = context_course::instance($COURSE->id);
        foreach ($defaults as $defrecord) {
            $files = $fs->get_area_files($coursecontext->id, 'local_courseicons', 'defaulticon', $defrecord->id, 'id', false);
            if (!empty($files)) {
                $file = reset($files);
                $murl = moodle_url::make_pluginfile_url(
                    $coursecontext->id,
                    'local_courseicons',
                    'defaulticon',
                    $defrecord->id,
                    '/',
                    $file->get_filename()
                );
                $murl->param('t', $defrecord->timemodified);

                $defaultdata[] = [
                    'modname' => $defrecord->modname,
                    'url' => $murl->out(false),
                ];
            }
        }

        if (!empty($icondata) || !empty($defaultdata)) {
            // Send data to JS for deep DOM manipulation.
            $PAGE->requires->js_call_amd('local_courseicons/swapper', 'init', [$icondata, $defaultdata]);
        }
    }
}

/**
 * Extends the course navigation (Moodle 4.0+ secondary navigation).
 * This purely adds the "Customize activity icons" button for teachers.
 *
 * @param \navigation_node $navigation
 * @param \stdClass $course
 * @param \context $context
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
 * @param \stdClass $course
 * @param \cm_info $cm
 * @param \context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
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
    if ($context->contextlevel != CONTEXT_MODULE && $context->contextlevel != CONTEXT_COURSE) {
        return false;
    }

    if ($filearea !== 'activityicon' && $filearea !== 'defaulticon') {
        return false;
    }

    if ($context->contextlevel == CONTEXT_MODULE) {
        require_course_login($course, true, $cm);
    } else {
        require_course_login($course);
    }

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
