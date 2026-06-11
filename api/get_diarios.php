<?php

namespace tool_painelava;

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
        
        $campos = ['turma_ano_periodo', 'disciplina_id', 'disciplina_descricao', 'disciplina_sigla', 'curso_codigo', 'curso_descricao', 'diario_id', 'sala_tipo'];
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
        $curso->sala_tipo            = isset($cf_data['sala_tipo']) ? trim($cf_data['sala_tipo']) : '';
        
        return $curso;
    }

    /**
     * Verifica se um curso do tipo 'diario' atende aos filtros de busca informados na API.
     */
    private function atende_filtros($curso, $filtros) {
        if (!$filtros['has_filters']) {
            return true;
        }

        $match_q = empty($filtros['q']) || stripos($curso->shortname . ' ' . $curso->fullname, $filtros['q']) !== false;
        $match_semestre   = empty($filtros['semestre'])   || $curso->turma_ano_periodo == $filtros['semestre'];
        $match_disciplina = empty($filtros['disciplina']) || $curso->disciplina_id == $filtros['disciplina'];
        $match_curso      = empty($filtros['curso'])      || $curso->curso_codigo == $filtros['curso'];

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
     * Busca os cursos disponíveis para autoinscrição e os devolve (com suas regras) 
     * para que o Painel (Python) faça a avaliação do perfil.
     */
    private function get_autoinscricoes($userid, $all_diarios) 
    {
        global $DB, $CFG;
        $autoinscricoes = [];

        $campo_restricao = $DB->get_record('customfield_field', ['shortname' => 'restricoes_de_autoinscricao']);
        if (!$campo_restricao) return $autoinscricoes;

        // Ocultado a checagem de empty do banco para evitar bugs com LONGTEXT
        $sql_vitrine = "SELECT c.id, c.fullname, c.shortname
                        FROM {course} c
                        JOIN {customfield_data} d ON d.instanceid = c.id
                        WHERE d.fieldid = ? AND c.visible = 1";
                        
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

        $start_total = microtime(true); // Cronômetro global da API

        $usuario_moodle = $DB->get_record('user', ['username' => strtolower($username)]);
        $userid = $usuario_moodle ? $usuario_moodle->id : null;

        $all_diarios = [];
        $agrupamentos = [];
        $enrolled_courses = [];
        $all_diarios_by_id = [];
        $cfs_missing = [];

        if ($usuario_moodle) {
            $USER = $usuario_moodle;
            
            // --- LOG 1: Todos os Diários (Inscrições do usuário) ---
            $t0 = microtime(true);
            $all_diarios = $this->get_all_diarios($USER->username);
            error_log('[PROFILER - 1] get_all_diarios: ' . round((microtime(true) - $t0) * 1000, 2) . 'ms');
            
            // --- LOG 2.1: Chamada rápida da Timeline ---
            $t1 = microtime(true);
            $my_courses = enrol_get_my_courses('id, fullname, shortname, visible, startdate, enddate, enablecompletion', $ordenacao);
            error_log('[PROFILER - 2.1] enrol_get_my_courses (Nativa): ' . round((microtime(true) - $t1) * 1000, 2) . 'ms');

            // --- LOG 2.2: O teste real (Pré-carregamento movido para o topo) ---
            $t_preload = microtime(true);
            $my_course_ids = array_column($my_courses, 'id');
            if (!empty($my_course_ids)) {
                list($ctx_sql, $ctx_params) = $DB->get_in_or_equal($my_course_ids);
                $ctx_fields = \context_helper::get_preload_record_columns_sql('ctx');
                $sql_contexts = "SELECT $ctx_fields FROM {context} ctx WHERE ctx.contextlevel = 50 AND ctx.instanceid $ctx_sql";
                if ($recordset = $DB->get_recordset_sql($sql_contexts, $ctx_params)) {
                    foreach ($recordset as $ctxrecord) {
                        \context_helper::preload_from_record($ctxrecord);
                    }
                    $recordset->close();
                }
            }
            error_log('[PROFILER - 2.2] context_preload (RAM): ' . round((microtime(true) - $t_preload) * 1000, 2) . 'ms');

            // --- LOG 2.3: Preferências de Ocultos e Favoritos ---
            $t_prefs = microtime(true);
            $fav_records = $DB->get_records('favourite', ['component' => 'core_course', 'itemtype' => 'courses', 'userid' => $USER->id], '', 'itemid, id');
            
            $hidden_prefs = $DB->get_records_select('user_preferences', "userid = ? AND name LIKE 'block_myoverview_hidden_course_%'", [$USER->id], '', 'name, value');
            $hidden_ids = [];
            if ($hidden_prefs) {
                foreach ($hidden_prefs as $pref) {
                    $id = str_replace('block_myoverview_hidden_course_', '', $pref->name);
                    $hidden_ids[$id] = true;
                }
            }
            error_log('[PROFILER - 2.3] fav_and_hidden_prefs: ' . round((microtime(true) - $t_prefs) * 1000, 2) . 'ms');

            // --- LOG 2.4: Loop de Montagem e Cálculo de Progresso ---
            $t_loop = microtime(true);
            $time = time();

            // Apagar teste
            $completioncourses = 0;
            $tprogress = 0;

            foreach ($my_courses as $c) {
                $ishidden = isset($hidden_ids[$c->id]);
                $isfav = isset($fav_records[$c->id]);
                
                $class = 'all';
                if ($ishidden) {
                    $class = 'hidden';
                } else if ($c->enddate > 0 && $c->enddate < $time) {
                    $class = 'past';
                } else if ($c->startdate > $time) {
                    $class = 'future';
                } else {
                    $class = 'inprogress';
                }

                if ($situacao !== 'all' && $situacao !== 'allincludinghidden') {
                    if ($situacao === 'favourites' && !$isfav) continue;
                    if ($situacao !== 'favourites' && $class !== $situacao) continue;
                }
                if ($situacao === 'all' && $ishidden) continue;

                $progress = null;
                $hasprogress = false;

                // 1ª Trava: É do semestre atual e o curso aceita barra de progresso?
                if ($class === 'inprogress' && $c->enablecompletion == 1) {
                                    
                    $coursecontext = \context_course::instance($c->id);
                    
                    // 2ª Trava: O usuário realmente é rastreado (Aluno)?
                    if (has_capability('moodle/course:isincompletionreports', $coursecontext, $USER)) {
                        
                        $p0 = microtime(true);
                        $completioncourses++;
                        
                        require_once($CFG->libdir . '/completionlib.php');
                        $raw_progress = \core_completion\progress::get_course_progress_percentage($c, $USER->id);
                        
                        if ($raw_progress !== null) {
                            $hasprogress = true;
                            $progress = round($raw_progress);
                        }
                        
                        $tprogress += microtime(true) - $p0;
                    }
                }

                $enrolled_courses[] = (object)[
                    'id' => $c->id,
                    'fullname' => $c->fullname,
                    'shortname' => $c->shortname,
                    'viewurl' => $CFG->wwwroot . '/course/view.php?id=' . $c->id,
                    'progress' => $progress,
                    'hasprogress' => $hasprogress,
                    'isfavourite' => $isfav,
                    'visible' => $c->visible
                ];
            }
            error_log("Cursos com completion: $completioncourses");
            error_log(
                'tempo_progress_total: ' .
                round($tprogress * 1000, 2) .
                'ms'
            );
            error_log('[PROFILER - 2.4] loop_timeline_and_completion: ' . round((microtime(true) - $t_loop) * 1000, 2) . 'ms');

            // --- LOG 3: Indexação e Custom Fields Faltantes ---
            $t3 = microtime(true);
            foreach ($all_diarios as $diario) {
                $all_diarios_by_id[$diario->id] = $diario;
            }

            $missing_ids = [];
            foreach ($enrolled_courses as $diario) {
                if (!isset($all_diarios_by_id[$diario->id])) {
                    $missing_ids[] = $diario->id;
                }
            }

            if (!empty($missing_ids)) {
                $campos_relevantes = ['turma_ano_periodo', 'disciplina_id', 'disciplina_descricao', 'disciplina_sigla', 'curso_codigo', 'curso_descricao', 'diario_id', 'sala_tipo'];
                $cfs_missing = $this->get_custom_fields_for_courses($missing_ids, $campos_relevantes);
            }
            error_log('[PROFILER - 3] custom_fields_missing: ' . round((microtime(true) - $t3) * 1000, 2) . 'ms');
        }

        $filtros_busca = [
            'semestre'    => $semestre,
            'disciplina'  => $disciplina,
            'curso'       => $curso,
            'q'           => $q,
            'has_filters' => !empty($semestre . $disciplina . $curso . $q)
        ];

        // --- LOG 4: Filtros de busca e Checagem real de Capabilities (has_capability) ---
        $t4 = microtime(true);
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

            $cf_dados = [];
            if (isset($all_diarios_by_id[$diario->id])) {
                $ad = $all_diarios_by_id[$diario->id];
                $cf_dados = [
                    'turma_ano_periodo'    => $ad->turma_ano_periodo,
                    'disciplina_id'        => $ad->disciplina_id,
                    'disciplina_descricao' => $ad->disciplina_descricao,
                    'disciplina_sigla'     => $ad->disciplina_sigla,
                    'curso_codigo'         => $ad->curso_codigo,
                    'curso_descricao'      => $ad->curso_descricao,
                    'diario_id'            => $ad->diario_id,
                    'sala_tipo'            => $ad->sala_tipo ?? '',
                ];
            } else if (isset($cfs_missing[$diario->id])) {
                $cf_dados = $cfs_missing[$diario->id];
            }

            $this->inject_custom_fields($curso_limpo, $cf_dados);

            $sala_tipo_original = !empty($cf_dados['sala_tipo']) ? strtolower(trim($cf_dados['sala_tipo'])) : 'diarios';
            $target_aba = ($sala_tipo_original === 'autoinscricoes') ? 'diarios' : $sala_tipo_original;

            if (!isset($agrupamentos[$target_aba])) {
                $agrupamentos[$target_aba] = [];
            }

            if ($target_aba === 'diarios') {
                if ($this->atende_filtros($curso_limpo, $filtros_busca)) {
                    $agrupamentos['diarios'][] = $curso_limpo;
                }
            } else {
                $agrupamentos[$target_aba][] = $curso_limpo;
            }
        }
        error_log('[PROFILER - 4] loop_agrupamentos_e_capabilities: ' . round((microtime(true) - $t4) * 1000, 2) . 'ms');

        // --- LOG 5: Vitrine SQL de Autoinscrições ---
        $t5 = microtime(true);
        $vitrine = $this->get_autoinscricoes($userid, $all_diarios);
        error_log('[PROFILER - 5] get_autoinscricoes: ' . round((microtime(true) - $t5) * 1000, 2) . 'ms');

        if ($vitrine && !isset($agrupamentos['autoinscricoes'])) {
            $agrupamentos['autoinscricoes'] = [];
        }

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

        error_log('[PROFILER - TOTAL] Tempo total da API: ' . round((microtime(true) - $start_total) * 1000, 2) . 'ms');

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
