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
 * Signs and sends the "the course catalog changed" signal to the DMS member portal.
 *
 * The portal re-reads the whole catalog when it gets the signal, so the body only says that
 * something changed (and when); it carries no course data to trust or leak.
 *
 * @package     local_addad
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_addad;

defined('MOODLE_INTERNAL') || die();

class announcer {

    /** Header carrying "sha256=" followed by the hex HMAC-SHA256 of the raw body. */
    const SIGNATURE_HEADER = 'X-Addad-Signature';

    /** Seconds to wait for the portal; Moodle retries a failed task later. */
    const TIMEOUT = 10;

    /**
     * Whether the plugin is set up to tell the portal. Both settings empty is the "off" state, so
     * the plugin can be installed before the portal side exists and switched off again by clearing
     * the URL.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return trim((string) get_config('local_addad', 'announceurl')) !== ''
            && (string) get_config('local_addad', 'announcesecret') !== '';
    }

    /**
     * The JSON body. Deterministic, because the signature covers these exact bytes.
     *
     * @param string $event What triggered it: course_change, or manual from the CLI.
     * @param int $time Unix time of sending.
     * @return string
     */
    public static function build_body(string $event, int $time): string {
        return json_encode(['event' => $event, 'time' => $time], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * The signature header value for a body.
     *
     * @param string $secret The shared secret.
     * @param string $body The exact bytes being sent.
     * @return string "sha256=" and the hex HMAC-SHA256.
     */
    public static function sign(string $secret, string $body): string {
        return 'sha256=' . hash_hmac('sha256', $body, $secret);
    }

    /**
     * POSTs a signed body to the portal.
     *
     * @param string $url The portal webhook URL.
     * @param string $secret The shared secret.
     * @param string $body The JSON body.
     * @return int The HTTP status code the portal answered with.
     * @throws \moodle_exception If the portal could not be reached at all.
     */
    public static function send(string $url, string $secret, string $body): int {
        global $CFG;
        // The curl class is not autoloaded; CLI scripts and some cron paths haven't loaded it yet.
        require_once($CFG->libdir . '/filelib.php');

        $curl = new \curl();
        $curl->post($url, $body, [
            'CURLOPT_TIMEOUT' => self::TIMEOUT,
            'CURLOPT_CONNECTTIMEOUT' => 5,
            'CURLOPT_HTTPHEADER' => [
                'Content-Type: application/json',
                self::SIGNATURE_HEADER . ': ' . self::sign($secret, $body),
            ],
        ]);
        if ($curl->get_errno()) {
            // Never put the URL or secret in the message: it ends up in the task log.
            throw new \moodle_exception('announcefailed', 'local_addad', '', 'could not reach the portal (curl error ' .
                $curl->get_errno() . ')');
        }
        $info = $curl->get_info();
        return (int) ($info['http_code'] ?? 0);
    }
}
