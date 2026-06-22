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
 * External API: get_user_courses
 *
 * Returns the courses a user is enrolled in, separated by course type,
 * including all custom profile fields and the user's role in each course.
 *
 * @package    tool_painelava
 * @copyright  2024 IFRN
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_painelava\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/course/lib.php');

use context_course;
use context_system;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use moodle_exception;

/**
 * External function to retrieve user courses grouped by type.
 *
 * @package    tool_painelava
 * @copyright  2026 IFRN
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_user_courses extends external_api {
    /**
     * Defines the parameters for this external function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(
                PARAM_INT,
                'ID of the user whose courses will be returned. Use 0 for the current user.',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * Returns the courses for a given user, grouped by course type.
     *
     * @param  int   $userid  Target user ID (0 = current user).
     * @return array          Structured list of course groups.
     */
    public static function execute(int $userid = 0): array {
        global $DB, $USER, $CFG;

        // Validate parameters.
        ['userid' => $userid] = self::validate_parameters(
            self::execute_parameters(),
            ['userid' => $userid]
        );

        // Resolve user.
        if ($userid === 0) {
            $userid = (int) $USER->id;
        }

        // System-level context validation.
        $systemcontext = context_system::instance();
        self::validate_context($systemcontext);

        // Capability check: the caller must be the user themselves OR have the
        // tool_painelava:viewothercourses capability.
        if ($userid !== (int) $USER->id) {
            require_capability('tool/painelava:viewothercourses', $systemcontext);
        }

        // Ensure the target user exists.
        if (!$DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
            throw new moodle_exception('invaliduser', 'tool_painelava');
        }

        // Get plugin settings.
        $config = get_config('tool_painelava');
        $coursetypefield = !empty($config->coursetypefield) ? $config->coursetypefield : 'tipo_curso';

        // Fetch all enrollments for the user.
        $enrolledcourses = enrol_get_users_courses($userid, true, null, 'fullname ASC');

        // Build a map of course custom field data keyed by course id.
        $customfieldsmap = self::get_course_custom_fields_map(array_keys($enrolledcourses));

        // Prepare result containers for each recognised type.
        $types = [
            'diario'       => [],
            'fic'          => [],
            'coordenacao'  => [],
            'laboratorio'  => [],
            'modelo'       => [],
            'outros'       => [],
        ];

        foreach ($enrolledcourses as $course) {
            $coursedata = self::build_course_data($course, $userid, $customfieldsmap, $coursetypefield, $config);
            $type = $coursedata['course_type'];
            if (array_key_exists($type, $types)) {
                $types[$type][] = $coursedata;
            } else {
                $types['outros'][] = $coursedata;
            }
        }

        // Log the API call if logging is enabled.
        if (!empty($config->enablelogging)) {
            $event = \tool_painelava\event\user_courses_requested::create([
                'context'  => $systemcontext,
                'relateduserid' => $userid,
            ]);
            $event->trigger();
        }

        return [
            'diario'      => $types['diario'],
            'fic'         => $types['fic'],
            'coordenacao' => $types['coordenacao'],
            'laboratorio' => $types['laboratorio'],
            'modelo'      => $types['modelo'],
            'outros'      => $types['outros'],
        ];
    }

    /**
     * Defines the return structure of this external function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        $coursestruct = self::get_course_return_structure();
        return new external_single_structure([
            'diario'      => new external_multiple_structure($coursestruct, 'Cursos do tipo Diário'),
            'fic'         => new external_multiple_structure($coursestruct, 'Cursos FIC (Formação Inicial e Continuada)'),
            'coordenacao' => new external_multiple_structure($coursestruct, 'Salas de Coordenação'),
            'laboratorio' => new external_multiple_structure($coursestruct, 'Laboratórios'),
            'modelo'      => new external_multiple_structure($coursestruct, 'Cursos Modelo'),
            'outros'      => new external_multiple_structure($coursestruct, 'Outros cursos'),
        ]);
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Build the return structure for a single course.
     *
     * @return external_single_structure
     */
    private static function get_course_return_structure(): external_single_structure {
        return new external_single_structure([
            'id'           => new external_value(PARAM_INT, 'Course ID'),
            'shortname'    => new external_value(PARAM_TEXT, 'Course short name'),
            'fullname'     => new external_value(PARAM_TEXT, 'Course full name'),
            'idnumber'     => new external_value(PARAM_RAW, 'Course ID number'),
            'summary'      => new external_value(PARAM_RAW, 'Course summary'),
            'summaryformat' => new external_value(PARAM_INT, 'Summary format'),
            'startdate'    => new external_value(PARAM_INT, 'Course start date (unix timestamp)'),
            'enddate'      => new external_value(PARAM_INT, 'Course end date (unix timestamp)'),
            'visible'      => new external_value(PARAM_INT, 'Whether the course is visible'),
            'category'     => new external_value(PARAM_INT, 'Course category ID'),
            'course_type'  => new external_value(PARAM_ALPHANUMEXT, 'Identified course type'),
            'role'         => new external_value(PARAM_TEXT, 'User role shortname in this course'),
            'roles'        => new external_multiple_structure(
                new external_single_structure([
                    'roleid'    => new external_value(PARAM_INT, 'Role ID'),
                    'shortname' => new external_value(PARAM_TEXT, 'Role shortname'),
                    'name'      => new external_value(PARAM_TEXT, 'Role name'),
                ]),
                'All roles the user holds in this course'
            ),
            'customfields' => new external_multiple_structure(
                new external_single_structure([
                    'shortname' => new external_value(PARAM_ALPHANUMEXT, 'Custom field shortname'),
                    'name'      => new external_value(PARAM_TEXT, 'Custom field name'),
                    'type'      => new external_value(PARAM_TEXT, 'Custom field type'),
                    'value'     => new external_value(PARAM_RAW, 'Custom field value', VALUE_OPTIONAL),
                    'valueraw'  => new external_value(PARAM_RAW, 'Custom field raw value', VALUE_OPTIONAL),
                ]),
                'Course custom fields'
            ),
        ]);
    }

    /**
     * Build the data array for a single course.
     *
     * @param  object $course          Course record.
     * @param  int    $userid          Target user ID.
     * @param  array  $customfieldsmap Map of course id => custom fields data.
     * @param  string $coursetypefield Shortname of the custom field used as course type.
     * @param  object $config          Plugin configuration object.
     * @return array
     */
    private static function build_course_data(
        object $course,
        int $userid,
        array $customfieldsmap,
        string $coursetypefield,
        object $config
    ): array {
        $customfields = $customfieldsmap[$course->id] ?? [];

        // Determine course type from custom field first, then shortname prefixes.
        $type = self::resolve_course_type($course, $customfields, $coursetypefield, $config);

        // Get the user roles in this course.
        $coursecontext = context_course::instance($course->id);
        $roles = get_user_roles($coursecontext, $userid, false);

        $rolesdata = [];
        $primaryrole = '';
        foreach ($roles as $role) {
            $rolename = role_get_name($role, $coursecontext);
            $rolesdata[] = [
                'roleid'    => (int) $role->roleid,
                'shortname' => $role->shortname,
                'name'      => $rolename,
            ];
            if ($primaryrole === '') {
                $primaryrole = $role->shortname;
            }
        }

        // Format custom fields for output.
        $customfieldsout = [];
        foreach ($customfields as $cf) {
            $customfieldsout[] = [
                'shortname' => $cf['shortname'],
                'name'      => $cf['name'],
                'type'      => $cf['type'],
                'value'     => isset($cf['value']) ? (string) $cf['value'] : '',
                'valueraw'  => isset($cf['valueraw']) ? (string) $cf['valueraw'] : '',
            ];
        }

        return [
            'id'            => (int)  $course->id,
            'shortname'     => $course->shortname,
            'fullname'      => $course->fullname,
            'idnumber'      => $course->idnumber ?? '',
            'summary'       => $course->summary ?? '',
            'summaryformat' => (int) ($course->summaryformat ?? FORMAT_HTML),
            'startdate'     => (int) ($course->startdate ?? 0),
            'enddate'       => (int) ($course->enddate ?? 0),
            'visible'       => (int) ($course->visible ?? 1),
            'category'      => (int) ($course->category ?? 0),
            'course_type'   => $type,
            'role'          => $primaryrole,
            'roles'         => $rolesdata,
            'customfields'  => $customfieldsout,
        ];
    }

    /**
     * Determine the course type label.
     *
     * Priority:
     *  1. Custom field with shortname = $coursetypefield
     *  2. Shortname prefix matching
     *  3. Falls back to 'outros'
     *
     * @param  object $course          Course record.
     * @param  array  $customfields    Custom fields for this course.
     * @param  string $coursetypefield Field shortname to look for.
     * @param  object $config          Plugin config.
     * @return string                  One of: diario, fic, coordenacao, laboratorio, modelo, outros.
     */
    private static function resolve_course_type(
        object $course,
        array $customfields,
        string $coursetypefield,
        object $config
    ): string {
        // 1. Check custom field value.
        foreach ($customfields as $cf) {
            if ($cf['shortname'] === $coursetypefield) {
                $val = strtolower(trim($cf['valueraw'] ?? $cf['value'] ?? ''));
                return self::normalise_type_value($val);
            }
        }

        // 2. Check shortname prefixes configured in settings.
        $shortname = $course->shortname;

        $prefixes = [
            'fic'         => $config->prefix_fic ?? 'FIC-',
            'coordenacao' => $config->prefix_coordenacao ?? 'COORD-',
            'laboratorio' => $config->prefix_laboratorio ?? 'LAB-',
            'modelo'      => $config->prefix_modelo ?? 'MODELO-',
        ];

        foreach ($prefixes as $type => $prefix) {
            if ($prefix !== '' && stripos($shortname, $prefix) === 0) {
                return $type;
            }
        }

        // Check "diario" prefix last (may be empty / default).
        $prefixdiario = $config->prefix_diario ?? '';
        if ($prefixdiario !== '' && stripos($shortname, $prefixdiario) === 0) {
            return 'diario';
        }

        return 'outros';
    }

    /**
     * Normalise a raw type value to a known type key.
     *
     * @param  string $val Raw value from custom field.
     * @return string
     */
    private static function normalise_type_value(string $val): string {
        $map = [
            'diario'       => 'diario',
            'diário'       => 'diario',
            'fic'          => 'fic',
            'coordenacao'  => 'coordenacao',
            'coordenação'  => 'coordenacao',
            'laboratorio'  => 'laboratorio',
            'laboratório'  => 'laboratorio',
            'modelo'       => 'modelo',
        ];

        return $map[$val] ?? 'outros';
    }

    /**
     * Build a map of course_id => [custom field data] for an array of course ids.
     *
     * @param  int[] $courseids
     * @return array<int, array>
     */
    private static function get_course_custom_fields_map(array $courseids): array {
        if (empty($courseids)) {
            return [];
        }

        $handler = \core_course\customfield\course_handler::create();
        $map = [];

        foreach ($courseids as $courseid) {
            $map[$courseid] = [];
            try {
                $fieldsdata = $handler->get_instance_data($courseid, true);
                foreach ($fieldsdata as $fielddata) {
                    $field = $fielddata->get_field();
                    $map[$courseid][] = [
                        'shortname' => $field->get('shortname'),
                        'name'      => $field->get('name'),
                        'type'      => $field->get('type'),
                        'value'     => $fielddata->export_value(),
                        // get_value() is part of the stable data API since Moodle 3.10+
                        // and is guaranteed available in our required Moodle 4.0+.
                        'valueraw'  => $fielddata->get_value(),
                    ];
                }
            } catch (\Exception $e) {
                // If custom fields are not available for this course, continue.
                debugging('tool_painelava: could not get custom fields for course ' . $courseid . ': ' . $e->getMessage());
            }
        }

        return $map;
    }
}
