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
 * Language strings.
 *
 * @package     local_addad
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'AddAD';
$string['privacy:metadata'] = 'The AddAD plugin only logs course completions to AD group additions in its own table.';
$string['chairrole'] = 'Chair role';
$string['chairrole_desc'] = 'Shortname of the role given to each committee chair on their category.';
$string['chairmappings'] = 'Chair groups';
$string['chairmappings_desc'] = 'One mapping per line: <code>category idnumber = AD group name</code>. Every member of the AD group (including nested groups) gets the chair role on that top-level category, and loses it when removed from the group. Roles assigned by hand are not touched. Synced hourly, or run <code>php local/addad/cli/sync_chair_roles.php --dry-run</code>.';
$string['task_syncchairroles'] = 'Sync committee chair groups to category roles';
