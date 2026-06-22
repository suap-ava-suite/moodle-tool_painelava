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
 * Service to get progress information for courses of a specific user.
 *
 * @package    tool_painelava
 * @copyright  2024 IFRN
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_progresso_service extends \tool_painelava\service
{
    /**
     * Get course progress percentage for the specified user and courses.
     *
     * @param string $username The username.
     * @param string|null $courseidsstr Comma-separated list of course IDs.
     * @return array Course progress details.
     */
    public function get_progresso($username, $courseidsstr = null) {
        $starttotal = microtime(true);
        global $DB, $CFG;

        $usuariomoodle = $DB->get_record('user', ['username' => strtolower($username)]);
        if (!$usuariomoodle) {
            return [];
        }

        $userid = $usuariomoodle->id;

        $coursefilter = "";
        $params = [$userid];

        if (!empty($courseidsstr)) {
            $courseids = array_map('intval', explode(',', $courseidsstr));
            $courseids = array_filter($courseids);
            if (!empty($courseids)) {
                [$insql, $inparams] = $DB->get_in_or_equal($courseids);
                $coursefilter = "AND c.id $insql";
                $params = array_merge($params, $inparams);
            }
        }

        $sql = "
            SELECT c.id, c.enablecompletion
            FROM {user} u
            INNER JOIN {user_enrolments} ue ON (ue.userid = u.id)
            INNER JOIN {enrol} e ON (e.id = ue.enrolid)
            INNER JOIN {course} c ON (c.id = e.courseid)
            WHERE u.id = ? AND ue.status = 0 AND e.status = 0
            $coursefilter
        ";

        $courses = $DB->get_records_sql($sql, $params);

        $result = [];

        if ($courses) {
            require_once($CFG->libdir . '/completionlib.php');

            foreach ($courses as $c) {
                $progress = null;
                $hasprogress = false;

                if ($c->enablecompletion == COMPLETION_ENABLED) {
                    $rawprogress = \core_completion\progress::get_course_progress_percentage($c, $userid);
                    $hasprogress = !is_null($rawprogress);
                    if ($hasprogress) {
                        $progress = (int) round($rawprogress);
                    }
                }

                $result[$c->id] = [
                    'id' => $c->id,
                    'progress' => $progress,
                    'hasprogress' => $hasprogress,
                ];
            }
        }

        // phpcs:ignore moodle.PHP.ForbiddenFunctions.FoundWithAlternative
        error_log(
            '[PROFILER - TOTAL] Tempo total da API (get_progresso): '
            . round((microtime(true) - $starttotal) * 1000, 2) . 'ms'
        );

        return array_values($result);
    }

    /**
     * Executes the service call to fetch course progress.
     *
     * @return array Course progress list.
     */
    public function do_call() {
        return $this->get_progresso(
            \tool_painelava\aget($_GET, 'username', null),
            \tool_painelava\aget($_GET, 'courseids', null)
        );
    }
}
