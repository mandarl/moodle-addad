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

namespace local_addad;

/**
 * Tests for the signing and body of the portal announcement.
 *
 * The expected values are computed independently with openssl and are the same ones the portal's
 * MoodleWebhookHandlerTest pins, so the two sides can't drift apart.
 *
 * @package     local_addad
 * @covers      \local_addad\announcer
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class announcer_test extends \basic_testcase {

    /** HMAC-SHA256 of the body below with the key "s3cret-key" (openssl dgst -sha256 -hmac). */
    const KNOWN_HMAC = 'debe50de98d94077c60fdbee13fb60621fbc6ca71d636df05f162580d36e121f';

    /** HMAC-SHA256 of the empty string with the same key. */
    const EMPTY_HMAC = '03b5d47de6f8548b83940d24f99f8345cb156d953238ce3a1b7cb6ece090dcd5';

    public function test_sign_matches_the_reference_hmac(): void {
        $body = '{"event":"manual","courseid":0,"categoryid":0,"time":1,"note":"café"}';

        $this->assertSame('sha256=' . self::KNOWN_HMAC, announcer::sign('s3cret-key', $body));
        $this->assertSame('sha256=' . self::EMPTY_HMAC, announcer::sign('s3cret-key', ''));
    }

    public function test_sign_depends_on_both_body_and_secret(): void {
        $this->assertNotSame(announcer::sign('a', 'body'), announcer::sign('b', 'body'));
        $this->assertNotSame(announcer::sign('a', 'body'), announcer::sign('a', 'body '));
    }

    public function test_body_is_deterministic_compact_json(): void {
        $this->assertSame('{"event":"course_change","time":1760000000}', announcer::build_body('course_change', 1760000000));
        $this->assertSame('{"event":"manual","time":5}', announcer::build_body('manual', 5));
    }
}
