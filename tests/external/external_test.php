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
 * Unit tests for the tool_painelava external API.
 *
 * @package    tool_painelava
 * @copyright  2024 IFRN
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_painelava\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

use externallib_advanced_testcase;

/**
 * Tests for tool_painelava\external\get_user_courses.
 *
 * @package    tool_painelava
 * @copyright  2024 IFRN
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_painelava\external\get_user_courses
 */
final class external_test extends externallib_advanced_testcase {
    /**
     * Set up the test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    // Parameter & permission tests.

    /**
     * A regular user cannot view another user's courses without the capability.
     */
    public function test_cannot_view_other_user_without_capability(): void {
        $viewer = $this->getDataGenerator()->create_user();
        $target = $this->getDataGenerator()->create_user();
        $this->setUser($viewer);

        $this->expectException(\required_capability_exception::class);
        get_user_courses::execute($target->id);
    }

    /**
     * A manager with tool/painelava:viewothercourses can view another user's courses.
     */
    public function test_manager_can_view_other_user(): void {
        global $DB;

        $manager = $this->getDataGenerator()->create_user();
        $target  = $this->getDataGenerator()->create_user();

        // Assign manager role at system level.
        $managerrole = $DB->get_record('role', ['shortname' => 'manager']);
        role_assign($managerrole->id, $manager->id, \context_system::instance()->id);

        $this->setUser($manager);
        $result = get_user_courses::execute($target->id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('diario', $result);
    }

    /**
     * Requesting courses for a deleted user throws an exception.
     */
    public function test_deleted_user_throws_exception(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'deleted', 1, ['id' => $user->id]);

        $admin = get_admin();
        $this->setUser($admin);

        try {
            get_user_courses::execute($user->id);
            $this->fail('Expected moodle_exception was not thrown.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('invaliduser', $e->errorcode);
        }
    }

    // Course type classification tests.

    /**
     * A course whose shortname starts with "FIC-" is classified as "fic".
     */
    public function test_fic_course_classified_by_prefix(): void {
        $user   = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['shortname' => 'FIC-001', 'fullname' => 'FIC test']);
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $this->setUser($user);
        $result = get_user_courses::execute($user->id);

        $this->assertCount(1, $result['fic'], 'Course should be in fic group');
        $this->assertCount(0, $result['outros'], 'No course should be in outros group');
        $this->assertEquals('FIC-001', $result['fic'][0]['shortname']);
    }

    /**
     * A course whose shortname starts with "COORD-" is classified as "coordenacao".
     */
    public function test_coordenacao_course_classified_by_prefix(): void {
        $user   = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['shortname' => 'COORD-TI', 'fullname' => 'Coord TI']);
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $this->setUser($user);
        $result = get_user_courses::execute($user->id);

        $this->assertCount(1, $result['coordenacao']);
        $this->assertEquals('COORD-TI', $result['coordenacao'][0]['shortname']);
    }

    /**
     * A course whose shortname starts with "LAB-" is classified as "laboratorio".
     */
    public function test_laboratorio_course_classified_by_prefix(): void {
        $user   = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['shortname' => 'LAB-NET', 'fullname' => 'Lab de Redes']);
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $this->setUser($user);
        $result = get_user_courses::execute($user->id);

        $this->assertCount(1, $result['laboratorio']);
        $this->assertEquals('LAB-NET', $result['laboratorio'][0]['shortname']);
    }

    /**
     * A course whose shortname starts with "MODELO-" is classified as "modelo".
     */
    public function test_modelo_course_classified_by_prefix(): void {
        $user   = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['shortname' => 'MODELO-BASE', 'fullname' => 'Modelo Base']);
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $this->setUser($user);
        $result = get_user_courses::execute($user->id);

        $this->assertCount(1, $result['modelo']);
        $this->assertEquals('MODELO-BASE', $result['modelo'][0]['shortname']);
    }

    /**
     * A course with no matching prefix falls into "outros".
     */
    public function test_unknown_course_classified_as_outros(): void {
        $user   = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['shortname' => 'MATH101', 'fullname' => 'Math']);
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $this->setUser($user);
        $result = get_user_courses::execute($user->id);

        $this->assertCount(1, $result['outros']);
    }

    // Return structure tests.

    /**
     * Returned course data contains all expected keys.
     */
    public function test_course_data_structure(): void {
        $user   = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['shortname' => 'FIC-STRUCT']);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $this->setUser($user);
        $result = get_user_courses::execute($user->id);

        $this->assertNotEmpty($result['fic']);
        $coursedata = $result['fic'][0];

        $requiredkeys = [
            'id', 'shortname', 'fullname', 'idnumber',
            'summary', 'summaryformat', 'startdate', 'enddate',
            'visible', 'category', 'course_type', 'role', 'roles', 'customfields',
        ];
        foreach ($requiredkeys as $key) {
            $this->assertArrayHasKey($key, $coursedata, "Missing key: $key");
        }
    }

    /**
     * The student role is returned correctly for an enrolled student.
     */
    public function test_student_role_returned(): void {
        $user   = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['shortname' => 'FIC-ROLE']);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $this->setUser($user);
        $result = get_user_courses::execute($user->id);

        $coursedata = $result['fic'][0];
        $this->assertEquals('student', $coursedata['role']);
        $this->assertCount(1, $coursedata['roles']);
        $this->assertEquals('student', $coursedata['roles'][0]['shortname']);
    }

    /**
     * A user enrolled in multiple courses receives all of them in the result.
     */
    public function test_multiple_courses_returned(): void {
        $user = $this->getDataGenerator()->create_user();

        $courses = [
            $this->getDataGenerator()->create_course(['shortname' => 'FIC-A']),
            $this->getDataGenerator()->create_course(['shortname' => 'FIC-B']),
            $this->getDataGenerator()->create_course(['shortname' => 'LAB-X']),
        ];

        foreach ($courses as $course) {
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
        }

        $this->setUser($user);
        $result = get_user_courses::execute($user->id);

        $this->assertCount(2, $result['fic']);
        $this->assertCount(1, $result['laboratorio']);
    }

    /**
     * A user with no enrolments receives empty arrays for all types.
     */
    public function test_user_with_no_courses(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $result = get_user_courses::execute($user->id);

        foreach (['diario', 'fic', 'coordenacao', 'laboratorio', 'modelo', 'outros'] as $type) {
            $this->assertCount(0, $result[$type], "Expected empty array for type $type");
        }
    }
}
