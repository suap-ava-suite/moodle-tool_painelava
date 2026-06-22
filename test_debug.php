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

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');

$admin = $DB->get_record('user', ['username' => 'admin']);
if (!$admin) {
    die("Admin user not found\n");
}

echo "Testing for user admin (ID: {$admin->id})\n";

global $USER;
$USER = $admin;

require_once($CFG->dirroot . '/course/externallib.php');

$classifications = ['all', 'inprogress', 'past', 'future', null, ''];

foreach ($classifications as $class) {
    try {
        $label = ($class === null) ? 'NULL' : ($class === '' ? 'EMPTY STRING' : "'$class'");
        $res = core_course_external::get_enrolled_courses_by_timeline_classification($class, 0, 0, null);
        echo "Classification $label: Success, found " . count($res['courses']) . " courses\n";
    } catch (Exception $e) {
        echo "Classification $label: Error - " . $e->getMessage() . "\n";
    }
}
