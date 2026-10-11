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
 * Scheduled task that syncs committee chair groups to category roles.
 *
 * @package     local_addad
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_addad\task;

defined('MOODLE_INTERNAL') || die();

class sync_chair_roles extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('task_syncchairroles', 'local_addad');
    }

    public function execute() {
        foreach (\local_addad\chair_sync::run() as $entry) {
            if ($entry['error'] !== null) {
                mtrace("  {$entry['category']}: skipped, {$entry['error']}");
            } else {
                mtrace("  {$entry['category']}: +" . count($entry['added']) . ' -' . count($entry['removed']));
            }
        }
    }
}
