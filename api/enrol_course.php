<?php

namespace tool_painelava;

if (!defined('NO_MOODLE_COOKIES')) {
    define('NO_MOODLE_COOKIES', true);
}

require_once('../../../../config.php');
require_once('../locallib.php');
require_once("servicelib.php");

class enrol_course_service extends \tool_painelava\service
{

    function do_call()
    {
        global $DB, $CFG, $USER;

        require_once($CFG->dirroot . '/course/externallib.php');
        require_once($CFG->dirroot . '/user/lib.php'); // Necessário para a função de criar usuário

        // Parâmetros obrigatórios
        $username = strtolower(\tool_painelava\aget($_GET, 'username', ''));
        $courseid = \tool_painelava\aget($_GET, 'courseid', 0);

        // Parâmetros extras do SUAP (para caso o usuário não exista)
        $firstname = \tool_painelava\aget($_GET, 'firstname', 'Aluno');
        $lastname  = \tool_painelava\aget($_GET, 'lastname', 'SUAP');
        $email     = \tool_painelava\aget($_GET, 'email', "{$username}@sememail.ifrn.edu.br");
        $campus    = \tool_painelava\aget($_GET, 'campus', '');

        if (empty($username) || empty($courseid)) {
            throw new \Exception("Username e CourseID são obrigatórios.", 400);
        }

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $context = \context_course::instance($courseid);

        $usuario_moodle = $DB->get_record('user', ['username' => $username]);

        // JIT PROVISIONING: Cria o usuário "on the fly" se não existir
        if (!$usuario_moodle) {
            $newuser = new \stdClass();
            $newuser->username   = $username;
            $newuser->password   = '!aA1' . uniqid();
            $newuser->firstname  = $firstname;
            $newuser->lastname   = $lastname;
            $newuser->email      = $email;
            $newuser->auth       = get_config('local_suap', 'default_auth') ?: 'manual'; 
            $newuser->timezone   = '99';
            $newuser->confirmed  = 1;
            $newuser->mnethostid = $CFG->mnet_localhost_id;
            $newuser->suspended  = 0;

            $newuser_id = user_create_user($newuser);

            if (!empty($campus)) {
                require_once($CFG->dirroot . '/user/profile/lib.php');
                
                $profiledata = new \stdClass();
                $profiledata->id = $newuser_id;
                $profiledata->profile_field_campus_sigla = $campus; 
                
                profile_save_data($profiledata);
            }

            $USER = $DB->get_record('user', ['id' => $newuser_id], '*', MUST_EXIST);

            $default_prefs_string = get_config('local_suap', 'default_user_preferences');
            if (!empty($default_prefs_string)) {
                $prefs_lines = preg_split('/[\r\n]+/', $default_prefs_string, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($prefs_lines as $line) {
                    $parts = explode('=', trim($line), 2);
                    if (count($parts) === 2) {
                        \set_user_preference($parts[0], $parts[1], $USER);
                    }
                }
            }
        } else {
            // Usuário já existia
            $USER = $usuario_moodle;
        }

        // 1. Busca se já existe uma inscrição (ativa ou suspensa)
        $user_enrolment = $DB->get_record_sql("
            SELECT ue.*, e.enrol 
            FROM {user_enrolments} ue
            JOIN {enrol} e ON e.id = ue.enrolid
            WHERE e.courseid = ? AND ue.userid = ?
        ", [$courseid, $USER->id]);

        // 2. Caso exista, verificamos o status
        if ($user_enrolment) {
            if ($user_enrolment->status == 0) {
                return [
                    "status" => "already_enrolled",
                    "message" => "Usuário já possui inscrição ativa.",
                    "courseid" => $courseid
                ];
            }

            // Se estiver suspensa (status 1), vamos reativar (status 0)
            $plugin = enrol_get_plugin($user_enrolment->enrol);
            $enrol_instance = $DB->get_record('enrol', ['id' => $user_enrolment->enrolid]);
            
            $plugin->update_user_enrol($enrol_instance, $USER->id, 0);

            \tool_painelava\group_helper::ensure_user_in_profile_based_group($courseid, $USER->id);

            return [
                "status" => "reactivated",
                "message" => "Sua inscrição foi reativada. Seu progresso anterior foi recuperado.",
                "courseid" => $courseid,
                "viewurl" => "{$CFG->wwwroot}/course/view.php?id={$courseid}"
            ];
        }
        
        // 3. Caso não exista (Nova Inscrição)
        $enrol = $DB->get_record('enrol', [
            'courseid' => $courseid, 
            'enrol' => 'manual', 
            'status' => 0
        ], '*', MUST_EXIST);

        $plugin = enrol_get_plugin('manual');
        $plugin->enrol_user($enrol, $USER->id, 5);

        \tool_painelava\group_helper::ensure_user_in_profile_based_group($courseid, $USER->id);

        return [
            "status" => "enrolled",
            "message" => "Inscrição realizada com sucesso.",
            "courseid" => $courseid,
            "viewurl" => "{$CFG->wwwroot}/course/view.php?id={$courseid}"
        ];
    }
}