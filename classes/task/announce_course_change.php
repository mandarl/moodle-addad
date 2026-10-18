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
 * Ad hoc task that tells the member portal the course catalog changed.
 *
 * @package     local_addad
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_addad\task;

defined('MOODLE_INTERNAL') || die();

class announce_course_change extends \core\task\adhoc_task {

    public function get_name() {
        return get_string('task_announcecoursechange', 'local_addad');
    }

    public function execute() {
        // Cleared settings (the kill switch) turn an already queued task into a no-op.
        if (!\local_addad\announcer::is_enabled()) {
            mtrace('  portal webhook is not configured, nothing to send');
            return;
        }

        $url = trim((string) get_config('local_addad', 'announceurl'));
        $secret = (string) get_config('local_addad', 'announcesecret');
        $body = \local_addad\announcer::build_body('course_change', time());

        $status = \local_addad\announcer::send($url, $secret, $body);
        if ($status < 200 || $status >= 300) {
            // Throwing makes Moodle retry this task later with a growing delay.
            throw new \moodle_exception('announcefailed', 'local_addad', '', 'the portal answered HTTP ' . $status);
        }
        mtrace("  portal answered HTTP $status");
    }
}
