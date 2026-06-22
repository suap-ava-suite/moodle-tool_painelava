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
 * Scheduled task: synchronise course panel data.
 *
 * @package    tool_painelava
 * @copyright  2024 IFRN
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_painelava\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task that refreshes cached course panel data.
 *
 * @package    tool_painelava
 * @copyright  2024 IFRN
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_courses extends \core\task\scheduled_task {
    /**
     * Return a localised name for this task.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_sync_courses', 'tool_painelava');
    }

    /**
     * Execute the task.
     */
    public function execute(): void {
        // Placeholder for any future cache-refresh or pre-computation logic.
        mtrace('tool_painelava: sync_courses task executed successfully.');
    }
}
