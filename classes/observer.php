<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Event observers
 *
 * @package   local_addad
 * @copyright 2020 Dallas Makerspace
 * @author    Justin Walker
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();



class local_addad_observer
{
    /**
     * Triggered when user completes a course.
     *
     * @param \core\event\course_completed $event
     */
    public static function adduser(\core\event\course_completed $event) 
    {

	$filename = "/var/www/log/snowie-addad.log";
	$file_handle = fopen($filename, 'a');
	fwrite($file_handle, "In course completed!".PHP_EOL);
	fflush($file_handle);

        try {
		// JGP debug
                error_log("local_add_observer has been called - Entry");

		global $DB;
		$TABLENAME = "addad_completion";

		// Get username of completed user
		$user = $DB->get_record('user', array('id'=>$event->relateduserid));
		$username = $user->username;
	fwrite($file_handle, "Found username: " . $username.PHP_EOL);
	fflush($file_handle);

		// Find AD Group from course
		$field_handler = \core_customfield\handler::get_handler('core_course', 'course');
		$course_fields = $field_handler->get_instance_data($event->courseid, true);
		$adgroup = "";
		foreach( $course_fields as $field)
		{
			if( $field->get_field()->get('shortname') == 'ad_group')
			{
				$adgroup = $field->get_value();
				break;
			}
		}
		fwrite($file_handle, "Ad Group?: " . $adgroup.PHP_EOL);
                fflush($file_handle);
		// If no AD group, stop
		if( empty($adgroup) )
		{
			return;
		}

		$logEntry = new stdClass();
		$logEntry->userid = $event->relateduserid;
		$logEntry->courseid = $event->courseid;
		$logEntry->timestamp = time();
		$logEntry->username = $username;
		// JGP 2025-09-07 - debugging
                $logEntry->comments = "DEBUG: Initial row insert";

		$logEntry->id =	$DB->insert_record($TABLENAME, $logEntry);


		// Get LDAP configuration from ldap plugin
		$record = $DB->get_record('config_plugins', array('plugin'=>'auth_ldap', 'name'=>'host_url'));
		$host_url = $record->value;
		$record = $DB->get_record('config_plugins', array('plugin'=>'auth_ldap', 'name'=>'bind_dn'));
		$bind_dn = $record->value;
		$record = $DB->get_record('config_plugins', array('plugin'=>'auth_ldap', 'name'=>'bind_pw'));
		$bind_pw = $record->value;
		//$record = $DB->get_record('config_plugins', array('plugin'=>'auth_ldap', 'name'=>'contexts'));
		//$root = $record->value;
		$root = "dc=dms,dc=local";

		// JGP 2025-09-07 - debugging
		$logEntry->comments = "DEBUG: LDAP $host_url $bind_dn $bind_pw";
		$DB->update_record( $TABLENAME, $logEntry);
		
	fwrite($file_handle, "Attempting AD Bind...".PHP_EOL);
	fflush($file_handle);
		// Setup connection and bind
		$connection = ldap_connect($host_url);
		ldap_set_option($connection,LDAP_OPT_PROTOCOL_VERSION,3);
		ldap_set_option($connection, LDAP_OPT_REFERRALS,0);
		$session=ldap_bind($connection, $bind_dn, $bind_pw);

		// JGP 2025-09-07 - debugging
		if( empty($session))
		{

	fwrite($file_handle, "No AD Session!".PHP_EOL);
	fflush($file_handle);
			$logEntry->comments = "DEBUG: LDAP session is empty";
			$DB->update_record( $TABLENAME, $logEntry);
			return;
		}
		// Search for the user and get DN
		$usersearch = ldap_search($connection, $root,"sAMAccountName=$username");
		$userinfo = ldap_get_entries($connection, $usersearch);
		$userdn = $userinfo[0]["distinguishedname"][0];

		// Search for the group and get DN
		$groupsearch = ldap_search($connection, $root, "CN=$adgroup");
		$groupinfo = ldap_get_entries($connection, $groupsearch);
		$groupdn = $groupinfo[0]["distinguishedname"][0];

	fwrite($file_handle, "UserDN: ".$userdn." ;; GroupDN: ".$groupdn.PHP_EOL);
	fflush($file_handle);
		if( empty($userdn))
		{
			$logEntry->comments = "Could not find username $username";
			$DB->update_record( $TABLENAME, $logEntry );
			return;
		}
		if( empty($groupdn))
		{
			$logEntry->comments = "Could not find group $adgroup";
			$DB->update_record( $TABLENAME, $logEntry );
			return;
		}
		
		// JGP 2025-09-07 - debugging
		$logEntry->comments = "DEBUG: Found user and group OK";
		$DB->update_record( $TABLENAME, $logEntry);

		// Check for group membership already
		try {
			if( local_addad_observer::checkGroupEx($connection, $userdn, $groupdn) )
			{
				$logEntry->comments = "Already in group";
				$DB->update_record( $TABLENAME, $logEntry );
				return;
			}
		} catch (Exception $e) {
			$logEntry->comments = "DEBUG: exception checking group membership $e->getMessage()";
			$DB->update_record( $TABLENAME, $logEntry);
			return;
		}

		// JGP 2025-09-07 - debugging
		$logEntry->comments = "DEBUG: User not in group, attempting to add";
		$DB->update_record( $TABLENAME, $logEntry);
		
		// Add user to group
		try {
			$res = ldap_mod_add( $connection, $groupdn, array("member"=>$userdn));
		} catch (Exception $e) {
			$logEntry->comments = "DEBUG: exception adding to group $e->getMessage()";
			$DB->update_record( $TABLENAME, $logEntry);
			return;
		}

		if(! $res)
		{
			$logEntry->comments = "LDAP returned $res for user $username and group $adgroup";
			$DB->update_record( $TABLENAME, $logEntry );
			return;
		}
		else
		{
			$logEntry->added = "true";
                        $logEntry->comments = $adgroup;
			$DB->update_record( $TABLENAME, $logEntry );
		}
	} finally {
	    fclose($file_handle);
	}
    } // end function

    public static function checkGroupEx($ad, $userdn, $groupdn)
    {
        $result = ldap_read($ad, $userdn, '(objectclass=*)', array( 'memberof' ));

        if (! $result)
        {
           return false;
        }

        $entries = ldap_get_entries($ad, $result);
        if ($entries['count'] <= 0 ||
	    empty($entries[0]['memberof'] ))
        {
            return false;
        }

        for ($i = 0; $i < $entries[0]['memberof']['count']; $i ++)
        {
            if ($entries[0]['memberof'][$i] == $groupdn)
            {
                return true;
            }
	    // Follow links
            //elseif (checkGroupEx($ad, $entries[0]['memberof'][$i], $groupdn))
            //{
            //    return true;
            //}
        }

        return false;
    }
}
