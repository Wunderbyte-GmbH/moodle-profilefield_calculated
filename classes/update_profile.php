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

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/user/profile/lib.php');

/**
 * Stores the calculated profile fields of a user when the user logs in.
 *
 * @package    profilefield_calculated
 * @copyright  2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_profile {
    /**
     * Store the current result of every calculated profile field for the user who logged in.
     *
     * Only calculated fields are written. A query without a row empties the stored value; a query that
     * could not be run leaves the stored value as it is.
     *
     * @param \core\event\user_loggedin $event
     * @return void
     */
    public static function user_loggedin(\core\event\user_loggedin $event): void {
        global $DB;

        $userid = (int)$event->objectid;
        $values = [];
        $fields = $DB->get_records('user_info_field', ['datatype' => 'calculated']);
        foreach ($fields as $field) {
            $fieldobject = profile_get_user_field($field->datatype, $field->id, $userid, $field);
            $value = $fieldobject->get_calculated_value();
            if ($value === null) {
                continue;
            }
            $values[$field->shortname] = $value;
        }

        if ($values) {
            profile_save_custom_fields($userid, $values);
        }
    }
}
