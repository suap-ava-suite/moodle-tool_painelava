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

// Desabilita verificação CSRF para esta API
if (!defined('NO_MOODLE_COOKIES')) {
    define('NO_MOODLE_COOKIES', true);
}

require_once('../../../../config.php');
global $CFG;
require_once($CFG->dirroot . '/course/externallib.php');
require_once('../locallib.php');
require_once("servicelib.php");

class set_visible_course_service extends \tool_painelava\service
{
    function do_call() {
        global $DB, $USER;

        $username  = \tool_painelava\aget($_GET, 'username', '');
        $courseid  = \tool_painelava\aget($_GET, 'courseid', 0);
        $visible   = \tool_painelava\aget($_GET, 'visible', 0);

        $USER = $DB->get_record('user', ['username' => strtolower($username)]);

        if (!$USER) {
             return ['error' => ['message' => "Usuário não encontrado", 'code' => 404]];
        }

        $coursecontext = \context_course::instance($courseid);
        if (!has_capability('moodle/course:visibility', $coursecontext, $USER)) {
            throw new \Exception('Sem permissão de alterar a visibilidade deste curso.', 403);
        }

        $course = $DB->get_record('course', ['id' => $courseid]);

        return $this->execute($course, $visible);
    }

    function execute($course, $visible) {
        global $DB;

        $course->visible = $visible;
        $DB->update_record('course', $course);
        return ["error" => false];
    }
}
