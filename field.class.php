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
 * Calculated profile field definition.
 *
 * @package    profilefield_calculated
 * @copyright  2016 onwards Antonello Moro {@link http://treagles.it}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Class profile_field_calculated
 *
 * @package    profilefield_calculated
 * @copyright  2016 onwards Antonello Moro {@link http://treagles.it}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_field_calculated extends profile_field_base {
    /** @var string Placeholder in the configured query that is replaced by the id of the user. */
    public const USERPLACEHOLDER = '§current_user§';

    /** @var string|null Result of the configured query: '' if it returned no row, null if it could not be run. */
    protected $calculated = null;

    /**
     * Constructor method.
     *
     * Runs the configured query for the user and takes its result as the value of the field.
     *
     * @param int $fieldid
     * @param int $userid
     * @param stdClass|null $fielddata
     */
    public function __construct($fieldid = 0, $userid = 0, $fielddata = null) {
        parent::__construct($fieldid, $userid, $fielddata);

        if (empty($this->userid) || empty($this->field)) {
            // Nothing to calculate without a user or a field definition.
            return;
        }

        $this->calculated = $this->run_query();
        // The value is always the current result. A value saved earlier is never shown in its place.
        $this->data = $this->calculated ?? '';
    }

    /**
     * Run the configured query for the user of this field.
     *
     * A query that fails must never break the page: core builds every profile field whenever it loads a user,
     * also on login.
     *
     * @return string|null the value, '' if the query returned no row, null if the query could not be run
     */
    protected function run_query(): ?string {
        global $DB;

        $sql = trim((string)($this->field->param1 ?? ''));
        if ($sql === '') {
            return '';
        }
        $sql = str_replace(self::USERPLACEHOLDER, (string)(int)$this->userid, $sql);

        try {
            $records = $DB->get_records_sql($sql, null, 0, 1);
        } catch (dml_exception $e) {
            debugging(
                "profilefield_calculated: the query of field '{$this->field->shortname}' failed: " . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            return null;
        }

        $record = reset($records);
        if (!$record || !property_exists($record, 'data') || $record->data === null) {
            return '';
        }
        return (string)$record->data;
    }

    /**
     * The result of the configured query, unescaped.
     *
     * @return string|null the value, '' if the query returned no row, null if the query could not be run
     */
    public function get_calculated_value(): ?string {
        return $this->calculated;
    }

    /**
     * Create the code snippet for this field instance
     * Overwrites the base class method
     * @param moodleform $mform Moodle form instance
     */
    public function edit_field_add($mform) {
        $label = format_string($this->field->name);
        $mform->addElement('text', $this->inputname, $label);
        $mform->setType($this->inputname, PARAM_TEXT);
        $mform->hardFreeze($this->inputname);
        $mform->setConstant($this->inputname, $this->data);
    }

    /**
     * Display the data for this field.
     *
     * @return string escaped for output
     */
    public function display_data() {
        return s($this->calculated ?? '');
    }
}
