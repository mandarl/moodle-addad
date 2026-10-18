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
 * CLI: send the member portal a signed "the course catalog changed" ping, now.
 *
 * Used to test the connection after setting the webhook URL and secret. The portal's first ping
 * after it is set up also records the courses that already exist, so it never announces them.
 *
 * @package     local_addad
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(['dry-run' => false, 'send' => false, 'help' => false], ['h' => 'help']);

if ($options['help'] || $unrecognised || ($options['dry-run'] == $options['send'])) {
    echo "Sends the member portal a signed course-catalog-changed ping (see the Portal webhook settings).\n\n" .
        "Options:\n" .
        "  --dry-run   Print what would be sent without sending it\n" .
        "  --send      Send the ping and print the portal's answer\n" .
        "  -h, --help  Print this help\n";
    exit($options['help'] ? 0 : 1);
}

if (!\local_addad\announcer::is_enabled()) {
    echo "The Portal webhook URL and secret are not both set (Site administration > Plugins > Local plugins > AddAD).\n";
    exit(1);
}

$url = trim((string) get_config('local_addad', 'announceurl'));
$secret = (string) get_config('local_addad', 'announcesecret');
$body = \local_addad\announcer::build_body('manual', time());
$host = parse_url($url, PHP_URL_HOST) . parse_url($url, PHP_URL_PATH);

echo "To:   $host\n";
echo "Body: $body\n";
if ($options['dry-run']) {
    echo "Dry run: nothing sent.\n";
    exit(0);
}

try {
    $status = \local_addad\announcer::send($url, $secret, $body);
} catch (\moodle_exception $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
    exit(2);
}
echo "Portal answered HTTP $status\n";
exit(($status >= 200 && $status < 300) ? 0 : 2);
