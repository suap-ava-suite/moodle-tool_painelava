<?php
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
