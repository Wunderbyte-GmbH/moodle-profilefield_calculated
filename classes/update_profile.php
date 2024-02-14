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

namespace profilefield_calculated;

use core_user;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/user/profile/lib.php');

class update_profile {

    /**
     * Update profile field calculated for a user.
     *
     * @param \core\event\user_loggedin $event
     * @return void
     */
    public static function update_profile(\core\event\user_loggedin $event): void {
        global $DB;
        $data = $event->get_data();
        $userid = $data['objectid'];
        $user = core_user::get_user($userid);
        profile_load_custom_fields($user);
        $fields = $DB->get_records('user_info_field', ['datatype' => 'calculated']);
        foreach ($fields as $field) {
            $fieldobject = profile_get_user_field($field->datatype, $field->id, $userid, $field);
            $data[$field->shortname] = $fieldobject->display_data();
        }
        profile_save_custom_fields($userid, $data);
    }
}