<?php

namespace tool_painelava;

// Desabilita verificação CSRF para esta API
if (!defined('NO_MOODLE_COOKIES')) {
    define('NO_MOODLE_COOKIES', true);
}

require_once('../../../../config.php');
require_once('../locallib.php');
require_once("servicelib.php");


class get_diarios_service extends \tool_painelava\service
{

    function get_cursos($all_diarios)
    {
        $result = [];
        foreach ($all_diarios as $course) {
            $curso_id = $course->curso_codigo ?? '';
            $curso_desc = $course->curso_descricao ?? '';
            
            if (!empty($curso_id)) {
                $result[$curso_id] = ['id' => $curso_id, 'label' => $curso_desc ?: $curso_id];
            }
        }
        return array_values($result);
    }

    function get_disciplinas($all_diarios)
    {
        $result = [];
        foreach ($all_diarios as $course) {
            $disciplina_id = $course->disciplina_id ?? '';
            $disciplina_desc = $course->disciplina_descricao ?? '';
            
            if (!empty($disciplina_id)) {
                $result[$disciplina_id] = ['id' => $disciplina_id, 'label' => $disciplina_desc ?: $disciplina_id];
            }
        }
        return array_values($result);
    }

    function get_semestres($all_diarios)
    {
        $result = [];
        foreach ($all_diarios as $course) {
            $semestre = $course->turma_ano_periodo ?? '';
            
            if (!empty($semestre)) {
                $label = str_replace('/', '.', $semestre);                 
                $result[$semestre] = ['id' => $semestre, 'label' => $label];
            }
        }
        return array_values($result);
    }

    function get_all_diarios($username)
    {
        global $DB;
        
        $courses = \tool_painelava\get_recordset_as_array(
            "
            SELECT      c.id, c.shortname, c.fullname
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
        
        $campos = ['turma_ano_periodo', 'disciplina_id', 'disciplina_descricao', 'disciplina_sigla', 'curso_codigo', 'curso_descricao', 'diario_id'];
        $cfs = $this->get_custom_fields_for_courses($course_ids, $campos);

        foreach ($courses as &$c) {
            $this->inject_custom_fields($c, $cfs[$c->id] ?? []);
        }

        return $courses;
    }

    /**
     * Injeta os campos customizados padronizados no objeto do curso.
     * Aceita os dados de origem tanto como Array quanto como Objeto.
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
        
        return $curso;
    }

    /**
     * Verifica se um curso do tipo 'diario' atende aos filtros de busca informados na API.
     */
    private function atende_filtros($curso, $filtros) {
        // Se a API foi chamada sem nenhum filtro, o curso passa direto
        if (!$filtros['has_filters']) {
            return true;
        }

        // Verifica a string de busca (q) usando stripos (que já é case-insensitive, dispensando o strtoupper)
        $match_q = empty($filtros['q']) || stripos($curso->shortname . ' ' . $curso->fullname, $filtros['q']) !== false;
        
        // Verifica os parâmetros exatos
        $match_semestre   = empty($filtros['semestre'])   || $curso->turma_ano_periodo == $filtros['semestre'];
        $match_disciplina = empty($filtros['disciplina']) || $curso->disciplina_id == $filtros['disciplina'];
        $match_curso      = empty($filtros['curso'])      || $curso->curso_codigo == $filtros['curso'];

        // Só retorna verdadeiro se TODAS as condições ativas forem satisfeitas
        return $match_q && $match_semestre && $match_disciplina && $match_curso;
    }

    /**
     * Busca os valores dos custom fields para uma lista de IDs de cursos.
     * Se $fields for vazio, busca TODOS os custom fields daqueles cursos.
     * Retorna um array no formato: [course_id => [shortname => value, ...]]
     */
    private function get_custom_fields_for_courses(array $course_ids, array $fields_to_fetch = []) {
        global $DB;
        
        if (empty($course_ids)) {
            return [];
        }

        // Prepara o IN() para os IDs dos cursos
        list($course_insql, $course_inparams) = $DB->get_in_or_equal($course_ids);
        $params = $course_inparams;

        // Prepara o filtro de campos (se foi especificado)
        $field_filter = "";
        if (!empty($fields_to_fetch)) {
            list($field_insql, $field_inparams) = $DB->get_in_or_equal($fields_to_fetch);
            $field_filter = "AND f.shortname $field_insql";
            $params = array_merge($params, $field_inparams);
        }

        // Faz uma única consulta robusta
        $sql = "SELECT d.id AS dataid, d.instanceid, f.shortname, d.value, d.charvalue
                FROM {customfield_data} d
                JOIN {customfield_field} f ON d.fieldid = f.id
                WHERE d.instanceid $course_insql
                $field_filter";
                  
        $records = $DB->get_records_sql($sql, $params);
        
        $results = [];
        if ($records) {
            foreach ($records as $rec) {
                // Pega o valor limpo (priorizando textos grandes e caindo para curtos)
                $val = $rec->value ?: $rec->charvalue;
                $results[$rec->instanceid][$rec->shortname] = is_string($val) ? trim($val) : $val;
            }
        }
        
        return $results;
    }


    /**
     * Busca os cursos disponíveis para autoinscrição e os devolve (com suas regras) 
     * para que o Painel (Python) faça a avaliação do perfil.
     */
    private function get_autoinscricoes($userid, $all_diarios) 
    {
        global $DB, $CFG;
        $autoinscricoes = [];

        // AGORA BUSCAMOS PELO CAMPO DE RESTRIÇÕES, E NÃO MAIS PELO SALA_TIPO
        $campo_restricao = $DB->get_record('customfield_field', ['shortname' => 'restricoes_de_autoinscricao']);
        if (!$campo_restricao) return $autoinscricoes;

        // Cursos visíveis que possuam ALGUMA COISA escrita no campo de restrições
        $sql_vitrine = "SELECT c.id, c.fullname, c.shortname
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

        // Monta os cursos da vitrine
        foreach ($cursos_vitrine as $curso_vitrine) {

            // Lê as restrições e limpa as tags HTML que o editor do Moodle costuma colocar (ex: <p>, <br>)
            $restricoes_str = $cf_vitrine[$curso_vitrine->id]['restricoes_de_autoinscricao'] ?? '';
            $texto_limpo_restricoes = trim(html_entity_decode(strip_tags($restricoes_str), ENT_QUOTES, 'UTF-8'));

            $curso_vitrine->restricoes_de_autoinscricao = $texto_limpo_restricoes;

            $this->inject_custom_fields($curso_vitrine, $cf_vitrine[$curso_vitrine->id] ?? []);

            $curso_vitrine->is_enrolled = isset($mapa_matriculados[$curso_vitrine->id]);
            $curso_vitrine->viewurl = $CFG->wwwroot . '/course/view.php?id=' . $curso_vitrine->id;
            
            $autoinscricoes[] = $curso_vitrine;
        }

        return $autoinscricoes;
    }


    function get_diarios($username, $semestre, $situacao, $ordenacao, $disciplina, $curso, $arquetipo, $q, $page, $page_size)
    {
        global $DB, $CFG, $USER;

        require_once($CFG->dirroot . '/course/externallib.php');

        // Pega o usuário do banco. Se não existir, fica nulo (Permite ver a vitrine mesmo sem conta)
        $usuario_moodle = $DB->get_record('user', ['username' => strtolower($username)]);
        $userid = $usuario_moodle ? $usuario_moodle->id : null;

        $all_diarios = [];
        $agrupamentos = [];
        $cfs_matriculados = [];
        $enrolled_courses = [];

        // SE o usuário existir no Moodle, busca matricula dele
        if ($usuario_moodle) {
            $USER = $usuario_moodle;
            
            $all_diarios = $this->get_all_diarios($USER->username);
            $enrolled_courses = \core_course_external::get_enrolled_courses_by_timeline_classification($situacao, 0, 0, $ordenacao)['courses'];

            $enrolled_ids = array_column($enrolled_courses, 'id');
            $cfs_matriculados = $this->get_custom_fields_for_courses($enrolled_ids);
        }

        // Empacota os filtros recebidos pela API uma única vez
        $filtros_busca = [
            'semestre'    => $semestre,
            'disciplina'  => $disciplina,
            'curso'       => $curso,
            'q'           => $q,
            'has_filters' => !empty($semestre . $disciplina . $curso . $q)
        ];

        // Processa as matrículas (se houver alguma)
        foreach ($enrolled_courses as $diario) {
            $coursecontext = \context_course::instance($diario->id);

            $curso_limpo = (object) [
                'id' => $diario->id,
                'fullname' => $diario->fullname,
                'shortname' => $diario->shortname,
                'viewurl' => $diario->viewurl,
                'progress' => $diario->progress ?? null,
                'hasprogress' => $diario->hasprogress ?? false,
                'isfavourite' => $diario->isfavourite ?? false,
                'visible' => $diario->visible ?? false,
                'is_enrolled' => true,
                'can_set_visibility' => has_capability('moodle/course:visibility', $coursecontext, $USER) ? 1 : 0,
            ];

            $cf_dados = $cfs_matriculados[$diario->id] ?? [];
            $this->inject_custom_fields($curso_limpo, $cf_dados);

            $sala_tipo = !empty($cf_dados['sala_tipo']) ? strtolower(trim($cf_dados['sala_tipo'])) : 'diarios';

            if (!isset($agrupamentos[$sala_tipo])) {
                $agrupamentos[$sala_tipo] = [];
            }

            // sala_tipo "diarios" passa por filtros de busca, os demais tipos entram direto sem filtros
            if ($sala_tipo === 'diarios') {
                if ($this->atende_filtros($curso_limpo, $filtros_busca)) {
                    $agrupamentos[$sala_tipo][] = $curso_limpo;
                }
            } else {
                $agrupamentos[$sala_tipo][] = $curso_limpo;
            }
        }

        // Independente de ter usuário ou não, busca a vitrine
        $vitrine = $this->get_autoinscricoes($userid, $all_diarios);

        // Garante que o agrupamento de autoinscrições exista
        if ($vitrine && !isset($agrupamentos['autoinscricoes'])) {
            $agrupamentos['autoinscricoes'] = [];
        }

        // Adiciona apenas os cursos da vitrine que o aluno AINDA NÃO está matriculado
        foreach ($vitrine as $curso_vitrine) {
            $agrupamentos['autoinscricoes'][] = $curso_vitrine;
        }

        $return_base = [
            "semestres" => $this->get_semestres($all_diarios),
            "disciplinas" => $this->get_disciplinas($all_diarios),
            "cursos" => $this->get_cursos($all_diarios),
        ]; 

        if (!isset($agrupamentos['diarios'])) {
            $agrupamentos['diarios'] = [];
        }

        return array_merge($return_base, $agrupamentos);
    }

    function do_call()
    {
        return $this->get_diarios(
            \tool_painelava\aget($_GET, 'username', null),
            \tool_painelava\aget($_GET, 'semestre', null),
            \tool_painelava\aget($_GET, 'situacao', null),
            \tool_painelava\aget($_GET, 'ordenacao', null),
            \tool_painelava\aget($_GET, 'disciplina', null),
            \tool_painelava\aget($_GET, 'curso', null),
            \tool_painelava\aget($_GET, 'arquetipo', 'student'),
            \tool_painelava\aget($_GET, 'q', null),
            \tool_painelava\aget($_GET, 'page', 1),
            \tool_painelava\aget($_GET, 'page_size', 9),
        );
    }
}