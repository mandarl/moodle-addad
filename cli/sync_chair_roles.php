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
 * CLI: sync committee chair groups to category roles, now.
 *
 * @package     local_addad
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(['dry-run' => false, 'run' => false, 'help' => false], ['h' => 'help']);

if ($options['help'] || $unrecognised || ($options['dry-run'] == $options['run'])) {
    echo "Syncs committee chair AD groups to category roles (see the chairmappings setting).\n\n" .
        "Options:\n" .
        "  --dry-run   Print the changes without making them\n" .
        "  --run       Make the changes\n" .
        "  -h, --help  Print this help\n";
    exit($options['help'] ? 0 : 1);
}

$report = \local_addad\chair_sync::run((bool) $options['dry-run']);
if (!$report) {
    echo "No mappings configured or LDAP unavailable.\n";
    exit(1);
}

$failed = false;
foreach ($report as $entry) {
    echo "{$entry['category']} <- {$entry['group']}\n";
    if ($entry['error'] !== null) {
        echo "  SKIPPED: {$entry['error']}\n";
        $failed = true;
        continue;
    }
    foreach ($entry['added'] as $username) {
        echo "  + $username\n";
    }
    foreach ($entry['removed'] as $username) {
        echo "  - $username\n";
    }
    if (!$entry['added'] && !$entry['removed']) {
        echo "  no changes\n";
    }
}
exit($failed ? 2 : 0);
