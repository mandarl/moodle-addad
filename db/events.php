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
 * Coursecompleted enrolment plugin event handler definition.
 *
 * @package   local_addad
 * @copyright 2020 Dallas Makerspace
 * @author    Justin Walker
 * @license   
 */

defined('MOODLE_INTERNAL') || die();

$observers = [['eventname' => '\core\event\course_completed',
               'callback' => 'local_addad_observer::adduser',
               'internal'  => true,
               'priority'  => 9999,
	     ],
    // Tell the member portal when a course may have been published. Not internal, so these run after
    // the edit is committed; the callbacks only queue an ad hoc task and never throw.
    [
        'eventname' => '\core\event\course_created',
        'callback' => '\local_addad\course_observer::course_created',
        'internal' => false,
    ],
    [
        'eventname' => '\core\event\course_updated',
        'callback' => '\local_addad\course_observer::course_updated',
        'internal' => false,
    ],
    [
        'eventname' => '\core\event\course_category_updated',
        'callback' => '\local_addad\course_observer::course_category_updated',
        'internal' => false,
    ],
    [
        'eventname' => '\core\event\enrol_instance_created',
        'callback' => '\local_addad\course_observer::enrol_instance_changed',
        'internal' => false,
    ],
    [
        'eventname' => '\core\event\enrol_instance_updated',
        'callback' => '\local_addad\course_observer::enrol_instance_changed',
        'internal' => false,
    ],
];
