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
 * Hook listeners for local_courseicons.
 *
 * @package    local_courseicons
 * @copyright  2026 Yeison Díaz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_courseicons;

/**
 * Class hook_listener
 *
 * @package    local_courseicons
 * @copyright  2026 Yeison Díaz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_listener {
    /**
     * Hook callback for standard head HTML generation.
     *
     * @param \core\hook\output\before_standard_head_html_generation $hook
     */
    public static function before_standard_head_html_generation(
        \core\hook\output\before_standard_head_html_generation $hook
    ): void {
        $hook->add_html(local_courseicons_standard_head_html());
    }

    /**
     * Fallback handler for Moodle course copy/restore.
     * We bypass Moodle's XML core parser completely to avoid crashes.
     * We map the old course modules to the new ones by comparing their names and types.
     *
     * @param \core\event\course_restored $event
     */
    public static function course_restored(\core\event\course_restored $event): void {
        try {
            global $DB;

            $newcourseid = $event->objectid;
            
            // Find the old course ID from the event data
            $oldcourseid = $event->other['originalcourseid'] ?? null;
            if (!$oldcourseid || $oldcourseid == $newcourseid) {
                return;
            }

            // Get all icons from the old course
            $oldrecords = $DB->get_records('local_courseicons', ['courseid' => $oldcourseid]);
            if (empty($oldrecords)) {
                return;
            }

            $oldmodinfo = get_fast_modinfo($oldcourseid);
            $newmodinfo = get_fast_modinfo($newcourseid);
            $fs = get_file_storage();

            foreach ($oldrecords as $oldrecord) {
                $oldcmid = $oldrecord->cmid;

                // Find the old cm's name and modname
                if (!isset($oldmodinfo->cms[$oldcmid])) {
                    continue;
                }
                $oldcm = $oldmodinfo->cms[$oldcmid];
                $oldname = $oldcm->name;
                $oldmodname = $oldcm->modname;

                // Search for the corresponding cm in the new course
                $newcmid = null;
                foreach ($newmodinfo->get_cms() as $newcm) {
                    if ($newcm->name === $oldname && $newcm->modname === $oldmodname) {
                        $newcmid = $newcm->id;
                        break;
                    }
                }

                if (!$newcmid) {
                    continue;
                }

                // Create new record
                if (!$DB->record_exists('local_courseicons', ['cmid' => $newcmid])) {
                    $newrecord = new \stdClass();
                    $newrecord->courseid = $newcourseid;
                    $newrecord->cmid = $newcmid;
                    $newrecord->timecreated = time();
                    $newrecord->timemodified = time();
                    $DB->insert_record('local_courseicons', $newrecord);
                }

                // Copy the file
                $oldmodcontext = \context_module::instance($oldcmid, IGNORE_MISSING);
                $newmodcontext = \context_module::instance($newcmid, IGNORE_MISSING);

                if ($oldmodcontext && $newmodcontext) {
                    $oldfiles = $fs->get_area_files($oldmodcontext->id, 'local_courseicons', 'activityicon', 0, 'id', false);
                    foreach ($oldfiles as $oldfile) {
                        if ($oldfile->is_directory()) {
                            continue;
                        }
                        if ($fs->file_exists($newmodcontext->id, 'local_courseicons', 'activityicon', 0, $oldfile->get_filepath(), $oldfile->get_filename())) {
                            continue;
                        }
                        $newfilerecord = [
                            'contextid' => $newmodcontext->id,
                            'component' => 'local_courseicons',
                            'filearea'  => 'activityicon',
                            'itemid'    => 0,
                            'filepath'  => $oldfile->get_filepath(),
                            'filename'  => $oldfile->get_filename(),
                        ];
                        $fs->create_file_from_storedfile($newfilerecord, $oldfile);
                    }
                }
            }

            // Copy default icons
            $olddefaults = $DB->get_records('local_courseicons_def', ['courseid' => $oldcourseid]);
            if (!empty($olddefaults)) {
                $oldcoursecontext = \context_course::instance($oldcourseid, IGNORE_MISSING);
                $newcoursecontext = \context_course::instance($newcourseid, IGNORE_MISSING);

                foreach ($olddefaults as $olddef) {
                    if (!$DB->record_exists('local_courseicons_def', ['courseid' => $newcourseid, 'modname' => $olddef->modname])) {
                        $newdef = new \stdClass();
                        $newdef->courseid = $newcourseid;
                        $newdef->modname = $olddef->modname;
                        $newdef->timecreated = time();
                        $newdef->timemodified = time();
                        $newdef->id = $DB->insert_record('local_courseicons_def', $newdef);

                        if ($oldcoursecontext && $newcoursecontext) {
                            $oldfiles = $fs->get_area_files($oldcoursecontext->id, 'local_courseicons', 'defaulticon', $olddef->id, 'id', false);
                            foreach ($oldfiles as $oldfile) {
                                if ($oldfile->is_directory()) {
                                    continue;
                                }
                                $newfilerecord = [
                                    'contextid' => $newcoursecontext->id,
                                    'component' => 'local_courseicons',
                                    'filearea'  => 'defaulticon',
                                    'itemid'    => $newdef->id,
                                    'filepath'  => $oldfile->get_filepath(),
                                    'filename'  => $oldfile->get_filename(),
                                ];
                                $fs->create_file_from_storedfile($newfilerecord, $oldfile);
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // We MUST catch everything to guarantee the async task never hangs at 100%.
            error_log("LOCAL_COURSEICONS OBSERVER ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }

}
