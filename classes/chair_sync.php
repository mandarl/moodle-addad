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
 * Keeps a role on a top-level course category in step with the members of an AD group.
 *
 * @package     local_addad
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_addad;

defined('MOODLE_INTERNAL') || die();

/**
 * Syncs "category idnumber = AD group CN" mappings into category-level role assignments.
 *
 * Only assignments tagged with the local_addad component are added or removed, so roles that
 * admins assigned by hand are never touched.
 */
class chair_sync {

    /** Component recorded on the role assignments this class manages. */
    const COMPONENT = 'local_addad';

    /** AD matching rule that resolves nested group membership. */
    const IN_CHAIN = '1.2.840.113556.1.4.1941';

    /**
     * Parses the chairmappings setting.
     *
     * @param string $text One "category idnumber = AD group CN" per line; blank and # lines are ignored.
     * @return string[] AD group CN keyed by category idnumber.
     */
    public static function parse_mappings(string $text): array {
        $mappings = [];
        foreach (preg_split('/\R/', $text) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            [$idnumber, $group] = array_map('trim', explode('=', $line, 2));
            if ($idnumber !== '' && $group !== '') {
                $mappings[$idnumber] = $group;
            }
        }
        return $mappings;
    }

    /**
     * Runs the sync for every configured mapping.
     *
     * @param bool $dryrun Report the changes without making them.
     * @return array[] One entry per mapping: category, group, added[], removed[], error (string|null).
     */
    public static function run(bool $dryrun = false): array {
        global $DB;

        $report = [];
        $mappings = self::parse_mappings((string) get_config('local_addad', 'chairmappings'));
        if (!$mappings) {
            return $report;
        }

        $roleshortname = get_config('local_addad', 'chairrole') ?: 'manager';
        $role = $DB->get_record('role', ['shortname' => $roleshortname]);
        if (!$role) {
            mtrace("local_addad: role '$roleshortname' does not exist");
            return $report;
        }

        $auth = get_auth_plugin('ldap');
        $ldap = $auth->ldap_connect();
        if (!$ldap) {
            mtrace('local_addad: could not connect to LDAP, nothing changed');
            return $report;
        }
        $contexts = array_filter(array_map('trim', explode(';', (string) $auth->config->contexts)));

        foreach ($mappings as $idnumber => $group) {
            $entry = ['category' => $idnumber, 'group' => $group, 'added' => [], 'removed' => [], 'error' => null];
            try {
                self::sync_category($ldap, $contexts, $role, $idnumber, $group, $dryrun, $entry);
            } catch (\Throwable $e) {
                $entry['error'] = $e->getMessage();
            }
            $report[] = $entry;
        }

        $auth->ldap_close();
        return $report;
    }

    /**
     * Syncs one category. Throws before changing anything if the group can't be read, so a failed
     * lookup never removes existing chairs.
     */
    protected static function sync_category($ldap, array $contexts, \stdClass $role, string $idnumber,
            string $group, bool $dryrun, array &$entry): void {
        global $DB;

        $category = $DB->get_record('course_categories', ['idnumber' => $idnumber, 'parent' => 0]);
        if (!$category) {
            throw new \Exception('Top-level category not found for idnumber ' . $idnumber);
        }
        $catcontext = \context_coursecat::instance($category->id);

        $usernames = self::group_usernames($ldap, $contexts, $group);
        $wanted = [];
        if ($usernames) {
            [$insql, $params] = $DB->get_in_or_equal($usernames, SQL_PARAMS_NAMED);
            $wanted = $DB->get_records_select_menu('user', "username $insql AND deleted = 0 AND auth = :auth",
                $params + ['auth' => 'ldap'], '', 'id, username');
        }

        $current = $DB->get_records_sql_menu(
            "SELECT ra.userid, u.username
               FROM {role_assignments} ra
               JOIN {user} u ON u.id = ra.userid
              WHERE ra.contextid = :ctx AND ra.roleid = :role AND ra.component = :component",
            ['ctx' => $catcontext->id, 'role' => $role->id, 'component' => self::COMPONENT]);

        foreach (array_diff_key($wanted, $current) as $userid => $username) {
            if (!$dryrun) {
                role_assign($role->id, $userid, $catcontext->id, self::COMPONENT);
            }
            $entry['added'][] = $username;
        }
        foreach (array_diff_key($current, $wanted) as $userid => $username) {
            if (!$dryrun) {
                role_unassign($role->id, $userid, $catcontext->id, self::COMPONENT);
            }
            $entry['removed'][] = $username;
        }
    }

    /**
     * Returns the lower-cased sAMAccountNames of the group's members, including nested groups.
     *
     * @throws \Exception If the group can't be found or the search fails.
     * @return string[]
     */
    protected static function group_usernames($ldap, array $contexts, string $group): array {
        if (!$contexts || !preg_match('/dc=.*$/i', $contexts[0], $root)) {
            throw new \Exception('No usable auth_ldap context to derive the search base from');
        }
        $base = $root[0];

        $search = @ldap_search($ldap, $base, '(&(objectClass=group)(cn=' . ldap_escape($group, '', LDAP_ESCAPE_FILTER) . '))',
            ['distinguishedname']);
        if (!$search) {
            throw new \Exception('LDAP search for group failed: ' . ldap_error($ldap));
        }
        $groups = ldap_get_entries($ldap, $search);
        if ($groups['count'] !== 1) {
            throw new \Exception("Expected one AD group named '$group', found " . $groups['count']);
        }
        $groupdn = $groups[0]['distinguishedname'][0];

        $search = @ldap_search($ldap, $base,
            '(&(objectClass=user)(memberOf:' . self::IN_CHAIN . ':=' . ldap_escape($groupdn, '', LDAP_ESCAPE_FILTER) . '))',
            ['samaccountname']);
        if (!$search) {
            throw new \Exception('LDAP search for group members failed: ' . ldap_error($ldap));
        }
        $members = ldap_get_entries($ldap, $search);
        $usernames = [];
        for ($i = 0; $i < $members['count']; $i++) {
            $usernames[] = \core_text::strtolower($members[$i]['samaccountname'][0]);
        }
        return $usernames;
    }
}
