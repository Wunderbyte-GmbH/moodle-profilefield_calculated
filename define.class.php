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
 * Based on moodle menu by Shane Elliot
 *
 * @package   profilefield_calculated
 * @copyright 2016 onwards Antonello Moro {@link http://treagles.it}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Class profile_define_calculated
 *
 * @copyright 2016 onwards Antonello Moro {@link http://treagles.it}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_define_calculated extends profile_define_base
{

    /**
     * Adds elements to the form for creating/editing this type of profile field.
     *
     * @param moodleform $form
     */
    public function define_form_specific($form) {

        // Param 1 for menu type contains the options.
        $form->addElement(
            'textarea', 'param1', get_string('sqlquery', 'profilefield_calculated'),
            array('rows' => 6, 'cols' => 40)
        );
        $form->setType('param1', PARAM_TEXT);
        $form->addHelpButton('param1', 'param1sqlhelp', 'profilefield_calculated');
        // Default data.
        $form->addElement('text', 'defaultdata', get_string('profiledefaultdata', 'admin'), 'size="50"');
        $form->setType('defaultdata', PARAM_TEXT);

        // Let's see if the user can modify the sql.
        $context = context_system::instance();
        $hascap = has_capability('profilefield/calculated:caneditsql', $context);

        if (!$hascap) {
            $form->hardFreeze('param1');
            $form->hardFreeze('defaultdata');
        }
    }

    /**
     * Validates data for the profile field.
     *
     * @param  array $data
     * @param  array $files
     * @return array
     */
    public function define_validate_specific($data, $files) {
        global $DB, $USER;
        $err = array();

        $data->param1 = str_replace("\r", '', $data->param1);
        // Le'ts try to execute the query.
        $sql = $data->param1;
        $sql = str_replace('§current_user§',$USER->id,$sql);

        try {
            $rs = $DB->get_records_sql($sql);
            if (!$rs) {
                $err['param1'] = get_string('queryerrorfalse', 'profilefield_calculated');
            } else {
                if (count($rs) == 0) {
                    $err['param1'] = get_string('queryerrorempty', 'profilefield_calculated');
                } else {
                    $firstval = reset($rs);
                    if (!object_property_exists($firstval, 'data')) {
                        $err['param1'] = get_string('queryerroridmissing', 'profilefield_calculated');
                    }
                }
            }
        } catch (Exception $e) {
            $err['param1'] = get_string('sqlerror', 'profilefield_calculated') . ': ' .$e->getMessage();
        }
        return $err;
    }

    /**
     * Processes data before it is saved.
     *
     * @param  array|stdClass $data
     * @return array|stdClass
     */
    public function define_save_preprocess($data) {
        $data->param1 = str_replace("\r", '', $data->param1);

        return $data;
    }

}


