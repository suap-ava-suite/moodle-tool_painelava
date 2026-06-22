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

if (!defined('NO_MOODLE_COOKIES')) {
    define('NO_MOODLE_COOKIES', true);
}

require_once('../../../../config.php');
require_once('../locallib.php');
require_once("servicelib.php");

class suspend_enrol_service extends \tool_painelava\service
{
    function do_call() {
        global $DB, $CFG;

        // 1. Parâmetros
        $username = strtolower(\tool_painelava\aget($_GET, 'username', ''));
        $courseid = \tool_painelava\aget($_GET, 'courseid', 0);

        if (empty($username) || empty($courseid)) {
            throw new \Exception("Username e CourseID são obrigatórios.", 400);
        }

        // 2. Busca Usuário e Método de Inscrição
        $user = $DB->get_record('user', ['username' => $username], 'id', MUST_EXIST);

        // Buscamos a inscrição específica desse usuário neste curso
        // ENROL_USER_ACTIVE = 0, ENROL_USER_SUSPENDED = 1
        $user_enrolment = $DB->get_record_sql("
            SELECT ue.*, e.enrol 
            FROM {user_enrolments} ue
            JOIN {enrol} e ON e.id = ue.enrolid
            WHERE e.courseid = ? AND ue.userid = ?
        ", [$courseid, $user->id]);

        if (!$user_enrolment) {
            return [
                "status" => "not_enrolled",
                "message" => "O usuário não possui inscrição neste curso.",
            ];
        }

        if ($user_enrolment->status == 1) {
            return [
                "status" => "already_suspended",
                "message" => "A inscrição já está desativada.",
            ];
        }

        // 3. Executa a Suspensão
        $plugin = enrol_get_plugin($user_enrolment->enrol);
        $enrol_instance = $DB->get_record('enrol', ['id' => $user_enrolment->enrolid]);

        // O status 1 no Moodle para user_enrolments significa SUSPENSO
        $plugin->update_user_enrol($enrol_instance, $user->id, 1);

        return [
            "status" => "suspended",
            "message" => "Inscrição desativada com sucesso. Os dados do aluno foram preservados.",
            "courseid" => $courseid,
            "userid" => $user->id,
        ];
    }
}
