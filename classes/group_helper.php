<?php
namespace tool_painelava;

defined('MOODLE_INTERNAL') || die();

class group_helper {

    /**
     * Garante que o usuário está em um grupo baseado no valor de um campo de perfil,
     * mas apenas em cursos configurados com autoinscrição.
     * 
     * @param int $courseid ID do curso
     * @param int $userid ID do usuário
     * @param string $fieldshortname Nome curto do campo de perfil (default 'Campus_sigla')
     * @return void
     */
    public static function ensure_user_in_profile_based_group(int $courseid, int $userid, string $fieldshortname = 'Campus_sigla'): void {
        global $CFG, $DB;
        
        require_once($CFG->dirroot . '/group/lib.php');
        require_once($CFG->dirroot . '/user/profile/lib.php');
        require_once($CFG->dirroot . '/customfield/lib.php');

        // 1. Verifica se o curso aceita autoinscrição via campo customizado (API nativa)
        // Premissa: 'turma_autoinscricao' é um Moodle Course Custom Field.
        $handler = \core_course\customfield\course_handler::create();
        $customfields = $handler->export_instance_data_object($courseid);
        
        $is_autoinscricao = !empty($customfields->turma_autoinscricao) ? (bool) $customfields->turma_autoinscricao : false;
        
        if (!$is_autoinscricao) {
            return; // Sai silenciosamente: regra não se aplica a este curso.
        }

        // 2. Busca o valor do campo de perfil customizado do usuário
        $profile_data = profile_user_record($userid);
        $campus_raw = isset($profile_data->{$fieldshortname}) ? $profile_data->{$fieldshortname} : '';

        // 3. Normaliza o nome do grupo e aplica fallback
        $groupname = trim($campus_raw);
        $groupname = strtoupper($groupname);
        
        if (empty($groupname)) {
            $groupname = 'SEM_CAMPUS';
        }

        // 4. Busca o grupo no curso. groups_get_group_by_name() retorna ID ou falso.
        $groupid = groups_get_group_by_name($courseid, $groupname);
        
        // 5. Cria o grupo se não existir
        if (!$groupid) {
            $group = new \stdClass();
            $group->courseid = $courseid;
            $group->name = $groupname;
            
            try {
                $groupid = groups_create_group($group);
            } catch (\Exception $e) {
                // Mitigação para condição de corrida (ex: requisições concorrentes criando o mesmo grupo)
                $groupid = groups_get_group_by_name($courseid, $groupname);
                if (!$groupid) {
                    // Se realmente não foi criado e deu erro, re-lança para log
                    throw $e; 
                }
            }
        }

        // 6. Adiciona o usuário ao grupo, se já não for membro
        if ($groupid && !groups_is_member($groupid, $userid)) {
            groups_add_member($groupid, $userid);
        }
    }
}
