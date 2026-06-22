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

namespace tool_painelava;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState
if (!defined('NO_MOODLE_COOKIES')) {
    define('NO_MOODLE_COOKIES', true);
}

require_once('../../../../config.php');
global $CFG;
require_once($CFG->dirroot . '/course/externallib.php');
require_once('../locallib.php');
require_once("servicelib.php");

/**
 * Service to set course favourite status for a user.
 *
 * @package    tool_painelava
 * @copyright  2024 IFRN
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_favourite_course_service extends \tool_painelava\service
{
    /**
     * Executes the service call to toggle course favourite status.
     *
     * @return array Status indicating if call was successful.
     * @throws \Exception If validation fails.
     */
    public function do_call() {
        global $DB;

        $username  = \tool_painelava\aget($_GET, 'username', '');
        $courseid  = \tool_painelava\aget($_GET, 'courseid', 0);
        $favourite = \tool_painelava\aget($_GET, 'favourite', 0);

        $username = trim((string)$username);
        $username = \core_text::strtolower($username);

        // Validação de formato para reduzir abuso e entradas inesperadas.
        // Aceita letras, números e caracteres comuns de usernames Moodle.
        if ($username === '' || strlen($username) > 100 || !preg_match('/^[a-z0-9._@+-]+$/', $username)) {
            return ['error' => ['message' => "Requisição inválida", 'code' => 400]];
        }

        $user = $DB->get_record('user', ['username' => $username]);

        if (!$user) {
             return ['error' => ['message' => "Requisição inválida", 'code' => 400]];
        }

        \core\session\manager::set_user($user);

        $isfavourite = ($favourite == 1 || $favourite === 'true' || $favourite === true);

        return $this->execute($courseid, $isfavourite);
    }

    /**
     * Toggles course favourite status.
     *
     * @param int $courseid The course ID.
     * @param bool $favourite Whether the course is a favourite.
     * @return array Status indicating if call was successful.
     */
    public function execute($courseid, $favourite) {
        return \core_course_external::set_favourite_courses([['id' => $courseid, 'favourite' => $favourite]]);
    }
}
