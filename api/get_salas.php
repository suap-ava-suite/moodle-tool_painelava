<?php

namespace tool_painelava;

if (!defined('NO_MOODLE_COOKIES')) {
    define('NO_MOODLE_COOKIES', true);
}

require_once('../../../../config.php');
require_once('../locallib.php');
require_once("servicelib.php");


class get_salas_service extends \tool_painelava\service
{

    function get_all_diarios($username)
    {
        global $DB;
        
        $courses = \tool_painelava\get_recordset_as_array(
            "
            SELECT      c.id, c.shortname, c.fullname, c.visible, c.startdate, c.enddate
            FROM        {user} u
                            INNER JOIN {user_enrolments} ue ON (ue.userid = u.id)
                            INNER JOIN {enrol} e ON (e.id = ue.enrolid)
                            INNER JOIN {course} c ON (c.id = e.courseid)
            WHERE u.username = ? AND ue.status = 0 AND e.status = 0
            ",
            [strtolower($username)]
        );

        if (empty($courses)) return [];

        $course_ids = array_column($courses, 'id');
        
        $campos = ['turma_ano_periodo', 'disciplina_id', 'disciplina_descricao', 'disciplina_sigla', 'curso_codigo', 'curso_descricao', 'diario_id', 'sala_tipo'];
        $cfs = $this->get_custom_fields_for_courses($course_ids, $campos);

        foreach ($courses as &$c) {
            $this->inject_custom_fields($c, $cfs[$c->id] ?? []);
        }

        return $courses;
    }

    /**
     * Injeta os campos customizados padronizados no objeto do curso.
     */
    private function inject_custom_fields($curso, $cf_data) {
        $cf_data = (array) $cf_data;

        $curso->turma_ano_periodo    = isset($cf_data['turma_ano_periodo']) ? trim($cf_data['turma_ano_periodo']) : '';
        $curso->disciplina_id        = isset($cf_data['disciplina_id']) ? trim($cf_data['disciplina_id']) : '';
        $curso->disciplina_descricao = isset($cf_data['disciplina_descricao']) ? trim($cf_data['disciplina_descricao']) : '';
        $curso->disciplina_sigla     = isset($cf_data['disciplina_sigla']) ? trim($cf_data['disciplina_sigla']) : '';
        $curso->curso_codigo         = isset($cf_data['curso_codigo']) ? trim($cf_data['curso_codigo']) : '';
        $curso->curso_descricao      = isset($cf_data['curso_descricao']) ? trim($cf_data['curso_descricao']) : '';
        $curso->diario_id            = isset($cf_data['diario_id']) ? trim($cf_data['diario_id']) : null;
        $curso->sala_tipo            = isset($cf_data['sala_tipo']) ? trim($cf_data['sala_tipo']) : '';
        
        return $curso;
    }

    /**
     * Busca os valores dos custom fields para uma lista de IDs de cursos.
     */
    private function get_custom_fields_for_courses(array $course_ids, array $fields_to_fetch = []) {
        global $DB;
        
        if (empty($course_ids)) {
            return [];
        }

        list($course_insql, $course_inparams) = $DB->get_in_or_equal($course_ids);
        $params = $course_inparams;

        $field_filter = "";
        if (!empty($fields_to_fetch)) {
            list($field_insql, $field_inparams) = $DB->get_in_or_equal($fields_to_fetch);
            $field_filter = "AND f.shortname $field_insql";
            $params = array_merge($params, $field_inparams);
        }

        $sql = "SELECT d.id AS dataid, d.instanceid, f.shortname, d.value, d.charvalue
                FROM {customfield_data} d
                JOIN {customfield_field} f ON d.fieldid = f.id
                WHERE d.instanceid $course_insql
                $field_filter";
                  
        $records = $DB->get_records_sql($sql, $params);
        
        $results = [];
        if ($records) {
            foreach ($records as $rec) {
                $val = $rec->value ?: $rec->charvalue;
                $results[$rec->instanceid][$rec->shortname] = is_string($val) ? trim($val) : $val;
            }
        }
        
        return $results;
    }

    /**
     * Busca os cursos disponíveis para autoinscrição
     */
    private function get_autoinscricoes($all_diarios) 
    {
        global $DB, $CFG;
        $autoinscricoes = [];

        $campo_restricao = $DB->get_record('customfield_field', ['shortname' => 'restricoes_de_autoinscricao']);
        if (!$campo_restricao) return $autoinscricoes;

        $sql_vitrine = "SELECT c.id, c.fullname, c.shortname, c.visible
                        FROM {course} c
                        JOIN {customfield_data} d ON d.instanceid = c.id
                        WHERE d.fieldid = ? AND c.visible = 1
                          AND (d.charvalue != '' OR d.value IS NOT NULL AND d.value != '')";
                        
        $cursos_vitrine = $DB->get_records_sql($sql_vitrine, [$campo_restricao->id]);
        if (empty($cursos_vitrine)) return $autoinscricoes;
            
        $vitrine_ids = array_column($cursos_vitrine, 'id');
        $campos_vitrine = ['restricoes_de_autoinscricao', 'turma_ano_periodo', 'disciplina_id', 'disciplina_descricao', 'disciplina_sigla', 'curso_codigo', 'curso_descricao', 'diario_id'];
        
        $cf_vitrine = $this->get_custom_fields_for_courses($vitrine_ids, $campos_vitrine);

        $mapa_matriculados = [];
        foreach ($all_diarios as $diario_aluno) {
            $mapa_matriculados[$diario_aluno->id] = true;
        }

        foreach ($cursos_vitrine as $curso_vitrine) {
            $restricoes_str = trim($cf_vitrine[$curso_vitrine->id]['restricoes_de_autoinscricao'] ?? '');

            if (empty($restricoes_str)) {
                continue;
            }

            $curso_vitrine->restricoes_de_autoinscricao = $restricoes_str;
            $this->inject_custom_fields($curso_vitrine, $cf_vitrine[$curso_vitrine->id] ?? []);

            $curso_vitrine->is_enrolled = isset($mapa_matriculados[$curso_vitrine->id]);
            $curso_vitrine->viewurl = $CFG->wwwroot . '/course/view.php?id=' . $curso_vitrine->id;
            
            $autoinscricoes[] = $curso_vitrine;
        }

        return $autoinscricoes;
    }

    /**
     * Varre as matrículas, indexa metadados e faz a distribuição nas abas correspondentes.
     * Sem cálculo de progresso ou aplicação de filtros (busca, semestre, etc)
     */
    private function process_and_group_courses($all_diarios)
    {
        global $CFG;
        $agrupamentos = [];
        if (empty($all_diarios)) {
            return $agrupamentos;
        }

        foreach ($all_diarios as $diario) {
            $curso_limpo = clone $diario;
            $curso_limpo->viewurl = $CFG->wwwroot . '/course/view.php?id=' . $diario->id;
            $curso_limpo->is_enrolled = true;
            // Sem cálculo de progresso e verificação de hidden/favourites.
            $curso_limpo->progress = null;
            $curso_limpo->hasprogress = false;

            $sala_tipo_original = !empty($diario->sala_tipo) ? strtolower(trim($diario->sala_tipo)) : 'diarios';
            $target_aba = ($sala_tipo_original === 'autoinscricoes') ? 'diarios' : $sala_tipo_original;

            if (!isset($agrupamentos[$target_aba])) {
                $agrupamentos[$target_aba] = [];
            }

            $agrupamentos[$target_aba][] = $curso_limpo;
        }

        return $agrupamentos;
    }

    function get_salas($username)
    {
        $start_total = microtime(true);

        global $DB, $USER;

        $usuario_moodle = $DB->get_record('user', ['username' => strtolower($username)]);

        $all_diarios = [];
        $agrupamentos = [];

        if ($usuario_moodle) {
            $USER = $usuario_moodle;

            $all_diarios = $this->get_all_diarios($usuario_moodle->username);
            $agrupamentos = $this->process_and_group_courses($all_diarios);
            
            $vitrine = $this->get_autoinscricoes($all_diarios);
            if ($vitrine) {
                if (!isset($agrupamentos['autoinscricoes'])) {
                    $agrupamentos['autoinscricoes'] = [];
                }
                foreach ($vitrine as $curso_vitrine) {
                    $agrupamentos['autoinscricoes'][] = $curso_vitrine;
                }
            }
        }

        if (!isset($agrupamentos['diarios'])) {
            $agrupamentos['diarios'] = [];
        }

        error_log('[PROFILER - TOTAL] Tempo total da API (get_salas): ' . round((microtime(true) - $start_total) * 1000, 2) . 'ms');
        return $agrupamentos;
    }

    function do_call()
    {
        return $this->get_salas(
            \tool_painelava\aget($_GET, 'username', null)
        );
    }
}
