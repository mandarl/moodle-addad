<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Notices changes that may have published a course and queues a signal to the member portal.
 *
 * These observers run inside a teacher's own course edit, so they must never throw and must stay
 * cheap: they only queue an ad hoc task, which does the HTTP call on cron.
 *
 * @package     local_addad
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_addad;

defined('MOODLE_INTERNAL') || die();

class course_observer {

    /**
     * A course was created.
     *
     * @param \core\event\course_created $event
     */
    public static function course_created(\core\event\course_created $event): void {
        self::queue();
    }

    /**
     * A course was edited. Any edit fires this, so only changes that can affect whether it is
     * published (visibility or category) are passed on; if Moodle doesn't say what changed, pass
     * it on, because the portal sorts out what is actually new.
     *
     * @param \core\event\course_updated $event
     */
    public static function course_updated(\core\event\course_updated $event): void {
        $fields = $event->other['updatedfields'] ?? null;
        if (is_array($fields) && !array_key_exists('visible', $fields) && !array_key_exists('category', $fields)) {
            return;
        }
        self::queue();
    }

    /**
     * A category was edited: showing or hiding it shows or hides every course inside.
     *
     * @param \core\event\course_category_updated $event
     */
    public static function course_category_updated(\core\event\course_category_updated $event): void {
        self::queue();
    }

    /**
     * An enrolment method was added or changed: a course only counts as announceable once members
     * can self-enrol, which may be set up after it was made visible.
     *
     * @param \core\event\base $event
     */
    public static function enrol_instance_changed(\core\event\base $event): void {
        self::queue();
    }

    /**
     * Queues one pending announcement task. The task data is constant on purpose: Moodle then
     * keeps a single pending copy however many events arrive before cron runs it, and the portal
     * re-reads the whole catalog each time anyway.
     */
    private static function queue(): void {
        try {
            if (!announcer::is_enabled()) {
                return;
            }
            $task = new \local_addad\task\announce_course_change();
            $task->set_component('local_addad');
            $task->set_custom_data(['event' => 'course_change']);
            \core\task\manager::queue_adhoc_task($task, true);
        } catch (\Throwable $e) {
            debugging('local_addad: could not queue the portal announcement: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
