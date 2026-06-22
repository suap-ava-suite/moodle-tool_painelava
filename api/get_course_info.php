<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 *
 * @package    tool_painelava
 * @copyright  2024 IFRN
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_painelava;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState
if (!defined('NO_MOODLE_COOKIES')) {
    define('NO_MOODLE_COOKIES', true);
}

require_once('../../../../config.php');
require_once('../locallib.php');
require_once("servicelib.php");

/**
 * Service to get details, teachers, and workload (carga horaria) for a course.
 *
 * @package    tool_painelava
 * @copyright  2024 IFRN
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_course_info_service extends \tool_painelava\service
{
    /**
     * Executes the service call to fetch course info.
     *
     * @return array Course details including docentes and workload.
     * @throws \dml_exception If record is not found.
     */
    public function do_call() {
        global $DB, $USER, $OUTPUT, $PAGE;

        $courseid = \tool_painelava\aget($_GET, 'courseid', 0);
        $username = strtolower(\tool_painelava\aget($_GET, 'username', ''));

        $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname, shortname, summary', MUST_EXIST);
        $context = \context_course::instance($course->id);

        $cargahoraria = "";

        // Adicionamos decvalue na consulta.
        $sqlcf = "SELECT d.intvalue, d.charvalue, d.decvalue
                    FROM {customfield_data} d
                    JOIN {customfield_field} f ON d.fieldid = f.id
                    WHERE d.instanceid = ? AND f.shortname = 'carga_horaria'";

        if ($cfrecord = $DB->get_record_sql($sqlcf, [$course->id])) {
            // Verifica na ordem de probabilidade para campos numéricos.
            if ($cfrecord->decvalue !== null) {
                // Se for salvo como decimal (ex: 40.00000), usamos floatval para tirar os zeros extras.
                $cargahoraria = floatval($cfrecord->decvalue);
            } else if ($cfrecord->charvalue !== null && $cfrecord->charvalue !== '') {
                // Se for salvo como texto puro.
                $cargahoraria = trim($cfrecord->charvalue);
            } else if ($cfrecord->intvalue !== null) {
                // Em último caso.
                $cargahoraria = $cfrecord->intvalue;
            }
        }

        // Verifica se o usuário atual já está inscrito no curso.
        $isenrolled = false;
        if (!empty($username)) {
            $userrecord = $DB->get_record('user', ['username' => $username], 'id');
            if ($userrecord) {
                $sql = "SELECT ue.id
                        FROM {user_enrolments} ue
                        JOIN {enrol} e ON e.id = ue.enrolid
                        WHERE e.courseid = ?
                          AND ue.userid = ?
                          AND ue.status = 0
                          AND e.status = 0";

                $isenrolled = $DB->record_exists_sql($sql, [$course->id, $userrecord->id]);
            }
        }

        $summary = format_text($course->summary, FORMAT_HTML);

        if (!$OUTPUT) {
            $PAGE->set_url('/admin/tool/painelava/api/get_course_info.php');
            $OUTPUT = $PAGE->get_renderer('core');
        }

        $teachers = get_enrolled_users($context, 'moodle/course:update');
        $docentes = [];

        foreach ($teachers as $teacher) {
            $userpicture = new \user_picture($teacher);
            $userpicture->size = 100;

            $pictureurl = $userpicture->get_url($PAGE, null)->out(false);

            $description = format_text(
                $teacher->description,
                $teacher->descriptionformat,
                ['context' => \context_user::instance($teacher->id)]
            );

            $docentes[] = [
                'fullname' => fullname($teacher),
                'picture'  => $pictureurl,
                'description' => trim($description),
            ];
        }

        // Ordenação alfabética.
        usort($docentes, function ($a, $b) {
            return strcoll($a['fullname'], $b['fullname']);
        });

        return [
            "id" => $course->id,
            "fullname" => $course->fullname,
            "shortname" => $course->shortname,
            "summary" => trim(($summary)),
            "is_enrolled" => $isenrolled,
            "docentes" => $docentes,
            "carga_horaria" => $cargahoraria,
        ];
    }
}
