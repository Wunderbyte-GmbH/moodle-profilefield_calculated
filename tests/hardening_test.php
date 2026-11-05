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

use advanced_testcase;
use stdClass;

/**
 * Robustness tests for the calculated profile field and its login observer.
 *
 * @package    profilefield_calculated
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\profile_field_calculated::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(update_profile::class)]
final class hardening_test extends advanced_testcase {
    /** @var string Query resolving the e-mail address in the profile field "peoplelead" to the id of an active account. */
    private const SUPERVISORSQL = "SELECT s.id, s.id AS data
                                     FROM {user} s
                                     JOIN {user_info_data} d ON d.data = s.email
                                     JOIN {user_info_field} f ON f.id = d.fieldid AND f.shortname = 'peoplelead'
                                    WHERE d.userid = §current_user§ AND s.deleted = 0 AND s.suspended = 0";

    /** @var string Query on a table that does not exist. */
    private const BROKENSQL = "SELECT x.id, x.data FROM {profilefield_calculated_gone} x WHERE x.id = §current_user§";

    /**
     * Load the profile library.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/user/profile/lib.php');
    }

    /**
     * Create a calculated profile field.
     *
     * @param string $shortname
     * @param string $sql
     * @return stdClass the field record
     */
    private function create_calculated_field(string $shortname, string $sql): stdClass {
        return $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'calculated',
            'shortname' => $shortname,
            'name' => $shortname,
            'param1' => $sql,
        ]);
    }

    /**
     * Run the login event for a user, as the observer sees it.
     *
     * @param stdClass $user
     */
    private function login(stdClass $user): void {
        \core\event\user_loggedin::create([
            'userid' => $user->id,
            'objectid' => $user->id,
            'other' => ['username' => $user->username],
        ])->trigger();
    }

    /**
     * Read the stored value of a profile field for a user.
     *
     * @param int $fieldid
     * @param int $userid
     * @return string|false false if no record exists
     */
    private function stored_value(int $fieldid, int $userid) {
        global $DB;
        return $DB->get_field('user_info_data', 'data', ['fieldid' => $fieldid, 'userid' => $userid]);
    }

    /**
     * Create an employee whose "peoplelead" profile field holds the e-mail address of a supervisor.
     *
     * @return array [employee, supervisor, calculated field]
     */
    private function create_employee_with_supervisor(): array {
        $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text',
            'shortname' => 'peoplelead',
            'name' => 'People lead',
        ]);
        $field = $this->create_calculated_field('supervisorid', self::SUPERVISORSQL);
        $supervisor = $this->getDataGenerator()->create_user(['email' => 'lead@example.com']);
        $employee = $this->getDataGenerator()->create_user(['profile_field_peoplelead' => 'lead@example.com']);
        return [$employee, $supervisor, $field];
    }

    /**
     * A working query stores the calculated value when the user logs in.
     */
    public function test_login_stores_calculated_value(): void {
        $this->resetAfterTest();
        [$employee, $supervisor, $field] = $this->create_employee_with_supervisor();

        $this->login($employee);

        $this->assertEquals($supervisor->id, $this->stored_value($field->id, $employee->id));
    }

    /**
     * When the query returns no row any more, the next login empties the stored value.
     */
    public function test_login_empties_stored_value_when_query_returns_no_row(): void {
        global $DB;
        $this->resetAfterTest();
        [$employee, $supervisor, $field] = $this->create_employee_with_supervisor();
        $this->login($employee);
        $this->assertEquals($supervisor->id, $this->stored_value($field->id, $employee->id));

        $DB->set_field('user', 'suspended', 1, ['id' => $supervisor->id]);
        $this->login($employee);

        $this->assertSame('', (string)$this->stored_value($field->id, $employee->id));
        $record = profile_user_record($employee->id, false);
        $this->assertSame('', (string)$record->supervisorid);
    }

    /**
     * A query that fails does not break loading the profile fields of a user, which core does on every login.
     */
    public function test_failing_query_does_not_break_loading_of_user_data(): void {
        $this->resetAfterTest();
        $this->create_calculated_field('broken', self::BROKENSQL);
        $user = $this->getDataGenerator()->create_user();

        $complete = get_complete_user_data('id', $user->id);

        $this->assertEquals($user->id, $complete->id);
        $this->assertSame('', (string)$complete->profile['broken']);
        $this->assertNotEmpty($this->getDebuggingMessages());
        $this->resetDebugging();
    }

    /**
     * A query that fails leaves the value stored earlier untouched on login.
     */
    public function test_failing_query_keeps_stored_value_on_login(): void {
        global $DB;
        $this->resetAfterTest();
        [$employee, $supervisor, $field] = $this->create_employee_with_supervisor();
        $this->login($employee);

        $DB->set_field('user_info_field', 'param1', self::BROKENSQL, ['id' => $field->id]);
        $this->login($employee);

        $this->assertEquals($supervisor->id, $this->stored_value($field->id, $employee->id));
        $this->assertNotEmpty($this->getDebuggingMessages());
        $this->resetDebugging();
    }

    /**
     * The login observer only writes calculated fields; a field named like an event property keeps its value.
     */
    public function test_login_does_not_overwrite_other_profile_fields(): void {
        $this->resetAfterTest();
        $other = $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text',
            'shortname' => 'userid',
            'name' => 'Staff number',
        ]);
        $this->create_calculated_field('calc', 'SELECT u.id, u.username AS data FROM {user} u WHERE u.id = §current_user§');
        $user = $this->getDataGenerator()->create_user(['profile_field_userid' => 'staff-4711']);

        $this->login($user);

        $this->assertSame('staff-4711', $this->stored_value($other->id, $user->id));
    }

    /**
     * Displaying a field whose query returns no row gives an empty value.
     */
    public function test_display_is_empty_when_query_returns_no_row(): void {
        $this->resetAfterTest();
        $field = $this->create_calculated_field(
            'norow',
            'SELECT u.id, u.username AS data FROM {user} u WHERE u.id = §current_user§ AND u.id < 0'
        );
        $user = $this->getDataGenerator()->create_user();

        $fieldobject = profile_get_user_field('calculated', $field->id, $user->id, $field);

        $this->assertSame('', (string)$fieldobject->display_data());
    }

    /**
     * The displayed value is escaped, the stored value is the plain result of the query.
     */
    public function test_display_escapes_the_calculated_value(): void {
        $this->resetAfterTest();
        $field = $this->create_calculated_field(
            'markup',
            "SELECT u.id, '<b>bold</b>' AS data FROM {user} u WHERE u.id = §current_user§"
        );
        $user = $this->getDataGenerator()->create_user();

        $fieldobject = profile_get_user_field('calculated', $field->id, $user->id, $field);
        $this->assertStringNotContainsString('<b>', $fieldobject->display_data());

        $this->login($user);
        $this->assertSame('<b>bold</b>', $this->stored_value($field->id, $user->id));
    }
}
