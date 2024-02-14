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
 * Dynamic menu profile field definition.
 *
 * @package    profilefield_calculated
 * @copyright  2016 onwards Antonello Moro {@link http://treagles.it}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Class profile_field_calculated
 *
 * @copyright  2016 onwards Antonello Moro {@link http://treagles.it}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_field_calculated extends profile_field_base {

    /**
     * Constructor method.
     *
     * Pulls out the options for the menu from the database and sets the the corresponding key for the data if it exists.
     *
     * @param int $fieldid
     * @param int $userid
     */
    public function __construct($fieldid = 0, $userid = 0, $fielddata = null) {
        // First call parent constructor.
        parent::__construct($fieldid, $userid, $fielddata);
        // Only if we actually need data.

        global $DB;
        $sql = $this->field->param1;
        $sql = str_replace('§current_user§',$this->userid,$sql);

        $rs = $DB->get_record_sql($sql);
        if($rs) {
            $this->data = $rs->data;
        }
    }


    /**
     * Create the code snippet for this field instance
     * Overwrites the base class method
     * @param moodleform $mform Moodle form instance
     */
    public function edit_field_add($mform) {
        $label = format_string($this->field->name);
        $mform->addElement('text', $this->inputname, $label);
        $mform->setType( $this->inputname, PARAM_TEXT);
        $mform->hardFreeze($this->inputname);
        $mform->setConstant($this->inputname, $this->data);
    }

    /**
     * Display the data for this field.
     */
    public function display_data() {
        global $DB;
        $sql = $this->field->param1;
        $sql = str_replace('§current_user§',$this->userid,$sql);
        $rs = $DB->get_record_sql($sql);
        return $rs->data;
    }


}