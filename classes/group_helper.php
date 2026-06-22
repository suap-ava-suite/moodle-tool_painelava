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

/**
 * Helper class for group operations.
 *
 * @package    tool_painelava
 * @copyright  2024 IFRN
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
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
    public static function ensure_user_in_profile_based_group(
        int $courseid,
        int $userid,
        string $fieldshortname = 'campus_sigla'
    ): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/group/lib.php');
        require_once($CFG->dirroot . '/user/profile/lib.php');
        require_once($CFG->dirroot . '/customfield/lib.php');

        // 1. Verifica se o curso aceita autoinscrição via campo customizado (API nativa).
        // Premissa: 'turma_autoinscricao' é um Moodle Course Custom Field.
        $sql = "SELECT cdata.value
        FROM {customfield_data} cdata
        JOIN {customfield_field} cfield ON cdata.fieldid = cfield.id
        WHERE cdata.instanceid = :courseid AND cfield.shortname = :shortname";

        $isautoinscricao = $DB->get_field_sql($sql, [
            'courseid' => $courseid,
            'shortname' => 'turma_autoinscricao',
        ]);

        if (!(bool)$isautoinscricao) {
            return;
        }

        // 2. Busca o valor do campo de perfil customizado do usuário.
        $profiledata = profile_user_record($userid);
        $campusraw = isset($profiledata->{$fieldshortname}) ? $profiledata->{$fieldshortname} : '';

        // 3. Normaliza o nome do grupo e aplica fallback.
        $groupname = trim($campusraw);
        $groupname = strtoupper($groupname);

        if (empty($groupname)) {
            $groupname = 'SEM_CAMPUS';
        }

        // 4. Busca o grupo no curso. groups_get_group_by_name() retorna ID ou falso.
        $groupid = groups_get_group_by_name($courseid, $groupname);

        // 5. Cria o grupo se não existir.
        if (!$groupid) {
            $group = new \stdClass();
            $group->courseid = $courseid;
            $group->name = $groupname;

            try {
                $groupid = groups_create_group($group);
            } catch (\Exception $e) {
                // Mitigação para condição de corrida (ex: requisições concorrentes criando o mesmo grupo).
                $groupid = groups_get_group_by_name($courseid, $groupname);
                if (!$groupid) {
                    // Se realmente não foi criado e deu erro, re-lança para log.
                    throw $e;
                }
            }
        }

        // 6. Adiciona o usuário ao grupo, se já não for membro.
        if ($groupid && !groups_is_member($groupid, $userid)) {
            groups_add_member($groupid, $userid);
        }
    }
}
