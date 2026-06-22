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

defined('MOODLE_INTERNAL') || die();

require_once('../locallib.php');
require_once("servicelib.php");

/**
 * Service to get unread conversation and notification counts.
 *
 * @package    tool_painelava
 * @copyright  2024 IFRN
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_atualizacoes_counts_service extends \tool_painelava\service
{
    /**
     * Executes the service call to fetch updates count.
     *
     * @return array The updates counts or error details.
     */
    public function do_call() {
        global $DB, $USER;
        $username = strtolower($_GET['username']);
        $USER = $DB->get_record('user', ['username' => $username]);
        if ($USER) {
            return $this->get_atualizacoes_counts($USER->id);
        } else {
            return [
                'error' => ['message' => "Usuário '{$username}' não existe", 'code' => 404],
                'unread_conversations_count' => 0,
                'unread_popup_notification_count' => 0,
            ];
        }
    }

    /**
     * Retrieves unread conversations and popup notifications counts.
     *
     * @param int $useridto The target user ID.
     * @return array Updates counts.
     */
    public function get_atualizacoes_counts($useridto) {
        return [
            "unread_conversations_count" => \core_message_external::get_unread_conversations_count($useridto),
            "unread_popup_notification_count" => \message_popup_external::get_unread_popup_notification_count($useridto),
        ];
    }
}
