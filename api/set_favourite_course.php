<?php

namespace tool_painelava;

if (!defined('NO_MOODLE_COOKIES')) {
    define('NO_MOODLE_COOKIES', true);
}

require_once('../../../../config.php');
global $CFG;
require_once($CFG->dirroot . '/course/externallib.php');
require_once('../locallib.php');
require_once("servicelib.php");

class set_favourite_course_service extends \tool_painelava\service
{

    function do_call()
    {
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

        $is_favourite = ($favourite == 1 || $favourite === 'true' || $favourite === true);

        return $this->execute($courseid, $is_favourite);
    }

    function execute($courseid, $favourite)
    {
        return \core_course_external::set_favourite_courses([['id' => $courseid, 'favourite' => $favourite]]);
    }
}