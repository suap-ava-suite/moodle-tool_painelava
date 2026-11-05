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
 * Diagnóstico de usuário para o Painel AVA.
 *
 * Informa se a conta existe, se está suspensa, os acessos, a contagem de
 * inscrições e o status da inscrição em cada curso com autoinscrição.
 * Não altera nenhum dado.
 *
 * @package    tool_painelava
 * @copyright  2026 IFRN
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class diagnostico {
    /** Nome do custom field de curso com as regras de autoinscrição. */
    const CAMPO_RESTRICAO = 'restricoes_de_autoinscricao';

    /**
     * Monta o diagnóstico de um usuário.
     *
     * @param string $username Username (case-insensitive).
     * @param int|null $agora Timestamp de referência (para testes).
     * @return array Diagnóstico serializável em JSON.
     * @throws \Exception 400 se o username não for informado.
     */
    public static function usuario(string $username, ?int $agora = null): array {
        global $DB;

        $username = \core_text::strtolower(trim($username));
        if ($username === '') {
            throw new \Exception('Username é obrigatório.', 400);
        }
        $agora = $agora ?? time();

        $user = $DB->get_record(
            'user',
            ['username' => $username],
            'id, username, auth, suspended, deleted, firstaccess, lastaccess, lastlogin, currentlogin'
        );
        if (!$user) {
            return ['exists' => false, 'username' => $username, 'autoinscricoes' => self::autoinscricoes([])];
        }

        $inscricoes = self::inscricoes_por_curso((int) $user->id, $agora);

        $ativas = 0;
        $suspensas = 0;
        foreach ($inscricoes as $inscricao) {
            if ($inscricao['status'] === 'active') {
                $ativas++;
            } else {
                $suspensas++;
            }
        }

        return [
            'exists' => true,
            'user' => [
                'id' => (int) $user->id,
                'username' => $user->username,
                'auth' => $user->auth,
                'suspended' => (bool) $user->suspended,
                'deleted' => (bool) $user->deleted,
                'firstaccess' => (int) $user->firstaccess,
                'lastaccess' => (int) $user->lastaccess,
                'lastlogin' => (int) $user->lastlogin,
                'currentlogin' => (int) $user->currentlogin,
            ],
            'enrolments' => [
                'active' => $ativas,
                'suspended' => $suspensas,
                'total' => $ativas + $suspensas,
            ],
            'autoinscricoes' => self::autoinscricoes($inscricoes),
        ];
    }

    /**
     * Inscrições do usuário consolidadas por curso.
     *
     * Um curso pode ter mais de um método de inscrição; prevalece a inscrição
     * efetivamente ativa, se houver alguma.
     *
     * @param int $userid Id do usuário.
     * @param int $agora Timestamp de referência.
     * @return array Mapa courseid => dados da inscrição.
     */
    private static function inscricoes_por_curso(int $userid, int $agora): array {
        global $DB;

        $sql = "SELECT ue.id, e.courseid, e.enrol, e.status AS enrolstatus,
                       ue.status AS uestatus, ue.timestart, ue.timeend
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.userid = ?
              ORDER BY e.courseid, ue.id";
        $registros = $DB->get_records_sql($sql, [$userid]);

        $porcurso = [];
        foreach ($registros as $registro) {
            $instanciaativa = (int) $registro->enrolstatus === ENROL_INSTANCE_ENABLED;
            $inscricaoativa = (int) $registro->uestatus === ENROL_USER_ACTIVE;
            $expirada = !empty($registro->timeend) && (int) $registro->timeend < $agora;
            $futura = !empty($registro->timestart) && (int) $registro->timestart > $agora;
            $ativa = $instanciaativa && $inscricaoativa && !$expirada && !$futura;

            $dados = [
                'status' => $ativa ? 'active' : 'suspended',
                'enrol' => $registro->enrol,
                'timestart' => (int) $registro->timestart,
                'timeend' => (int) $registro->timeend,
                'user_enrolment_suspended' => !$inscricaoativa,
                'enrol_instance_enabled' => $instanciaativa,
                'expired' => $expirada,
                'not_started' => $futura,
            ];

            $courseid = (int) $registro->courseid;
            if (!isset($porcurso[$courseid]) || ($ativa && $porcurso[$courseid]['status'] !== 'active')) {
                $porcurso[$courseid] = $dados;
            }
        }

        return $porcurso;
    }

    /**
     * Cursos com regra de autoinscrição (inclusive ocultos) e a inscrição do usuário em cada um.
     *
     * @param array $inscricoes Mapa courseid => inscrição (de inscricoes_por_curso).
     * @return array Lista de cursos.
     */
    private static function autoinscricoes(array $inscricoes): array {
        global $DB;

        $campo = $DB->get_record('customfield_field', ['shortname' => self::CAMPO_RESTRICAO], 'id');
        if (!$campo) {
            return [];
        }

        $sql = "SELECT c.id, c.fullname, c.shortname, c.visible, d.charvalue, d.value
                  FROM {course} c
                  JOIN {customfield_data} d ON d.instanceid = c.id
                 WHERE d.fieldid = ?
              ORDER BY c.id";
        $cursos = $DB->get_records_sql($sql, [$campo->id]);

        $lista = [];
        foreach ($cursos as $curso) {
            $regra = trim((string) ($curso->charvalue ?: $curso->value));
            if ($regra === '') {
                continue;
            }
            $courseid = (int) $curso->id;
            $lista[] = [
                'courseid' => $courseid,
                'fullname' => $curso->fullname,
                'shortname' => $curso->shortname,
                'visible' => (bool) $curso->visible,
                'restricoes_de_autoinscricao' => $regra,
                'enrolment' => $inscricoes[$courseid] ?? null,
            ];
        }

        return $lista;
    }
}
