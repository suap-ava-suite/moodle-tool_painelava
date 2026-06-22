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

class get_progresso_service extends \tool_painelava\service
{
    function get_progresso($username, $courseids_str = null) {
        $start_total = microtime(true);
        global $DB, $CFG;

        $usuario_moodle = $DB->get_record('user', ['username' => strtolower($username)]);
        if (!$usuario_moodle) {
            return [];
        }

        $userid = $usuario_moodle->id;

        $course_filter = "";
        $params = [$userid];

        if (!empty($courseids_str)) {
            $courseids = array_map('intval', explode(',', $courseids_str));
            $courseids = array_filter($courseids);
            if (!empty($courseids)) {
                [$insql, $inparams] = $DB->get_in_or_equal($courseids);
                $course_filter = "AND c.id $insql";
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
            $course_filter
        ";

        $courses = $DB->get_records_sql($sql, $params);

        $result = [];

        if ($courses) {
            require_once($CFG->libdir . '/completionlib.php');

            foreach ($courses as $c) {
                $progress = null;
                $hasprogress = false;

                if ($c->enablecompletion == COMPLETION_ENABLED) {
                    $raw_progress = \core_completion\progress::get_course_progress_percentage($c, $userid);
                    $hasprogress = !is_null($raw_progress);
                    if ($hasprogress) {
                        $progress = (int) round($raw_progress);
                    }
                }

                $result[$c->id] = [
                    'id' => $c->id,
                    'progress' => $progress,
                    'hasprogress' => $hasprogress,
                ];
            }
        }

        error_log('[PROFILER - TOTAL] Tempo total da API (get_progresso): ' . round((microtime(true) - $start_total) * 1000, 2) . 'ms');

        return array_values($result);
    }

    function do_call() {
        return $this->get_progresso(
            \tool_painelava\aget($_GET, 'username', null),
            \tool_painelava\aget($_GET, 'courseids', null)
        );
    }
}
