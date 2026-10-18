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
$string['chairmappings_desc'] = 'One mapping per line: <code>category idnumber = AD group name[, another AD group name]</code>. Every member of the listed AD groups (including nested groups) gets the chair role on that top-level category, and loses it when removed from all of them. Roles assigned by hand are not touched. Synced every 15 minutes, or run <code>php local/addad/cli/sync_chair_roles.php --dry-run</code>.';
$string['task_syncchairroles'] = 'Sync committee chair groups to category roles';
$string['announceheading'] = 'Tell the member portal about new courses';
$string['announceheading_desc'] = 'When a course is created, shown, or its category or enrolment methods change, tell the DMS member portal so it can notify members who follow that committee. The portal works out which courses are actually new. Leave the URL empty to switch this off.';
$string['announceurl'] = 'Portal webhook URL';
$string['announceurl_desc'] = 'The portal address to notify, for example <code>https://members.example.org/webhook/moodle</code>. Empty switches the feature off.';
$string['announcesecret'] = 'Portal webhook secret';
$string['announcesecret_desc'] = 'Shared secret used to sign each request (header X-Addad-Signature). It must match the portal\'s ENV_MOODLE_WEBHOOK_SECRET.';
$string['task_announcecoursechange'] = 'Tell the member portal the course catalog changed';
$string['announcefailed'] = 'Could not tell the member portal about the course change: {$a}';
