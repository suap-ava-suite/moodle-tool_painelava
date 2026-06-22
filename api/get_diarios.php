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

namespace tool_painelava;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState
if (!defined('NO_MOODLE_COOKIES')) {
    define('NO_MOODLE_COOKIES', true);
}

require_once('../../../../config.php');
require_once('../locallib.php');
require_once("servicelib.php");


/**
 * Service to get all daily courses for a user, filtered and grouped.
 *
 * @package    tool_painelava
 * @copyright  2024 IFRN
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_diarios_service extends \tool_painelava\service
{
    /**
     * Extracts courses code and label information from daily course records.
     *
     * @param array $alldiarios All daily courses.
     * @return array Cursos list with id and label.
     */
    public function get_cursos($alldiarios) {
        $result = [];
        foreach ($alldiarios as $course) {
            $cursoid = $course->curso_codigo ?? '';
            $cursodesc = $course->curso_descricao ?? '';

            if (!empty($cursoid)) {
                $result[$cursoid] = ['id' => $cursoid, 'label' => $cursodesc ?: $cursoid];
            }
        }
        return array_values($result);
    }

    /**
     * Extracts disciplines code and label information from daily course records.
     *
     * @param array $alldiarios All daily courses.
     * @return array Disciplinas list with id and label.
     */
    public function get_disciplinas($alldiarios) {
        $result = [];
        foreach ($alldiarios as $course) {
            $disciplinaid = $course->disciplina_id ?? '';
            $disciplinadesc = $course->disciplina_descricao ?? '';

            if (!empty($disciplinaid)) {
                $result[$disciplinaid] = ['id' => $disciplinaid, 'label' => $disciplinadesc ?: $disciplinaid];
            }
        }
        return array_values($result);
    }

    /**
     * Extracts semestres information from daily course records.
     *
     * @param array $alldiarios All daily courses.
     * @return array Semestres list with id and label.
     */
    public function get_semestres($alldiarios) {
        $result = [];
        foreach ($alldiarios as $course) {
            $semestre = $course->turma_ano_periodo ?? '';

            if (!empty($semestre)) {
                $label = str_replace('/', '.', $semestre);
                $result[$semestre] = ['id' => $semestre, 'label' => $label];
            }
        }
        return array_values($result);
    }

    /**
     * Fetch all daily courses for a specific username.
     *
     * @param string $username Moodle username.
     * @return array Courses list.
     */
    public function get_all_diarios($username) {
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

        if (empty($courses)) {
            return [];
        }

        $courseids = array_column($courses, 'id');

        $campos = [
            'turma_ano_periodo',
            'disciplina_id',
            'disciplina_descricao',
            'disciplina_sigla',
            'curso_codigo',
            'curso_descricao',
            'diario_id',
            'sala_tipo',
        ];
        $cfs = $this->get_custom_fields_for_courses($courseids, $campos);

        foreach ($courses as &$c) {
            $this->inject_custom_fields($c, $cfs[$c->id] ?? []);
        }

        return $courses;
    }

    /**
     * Injeta os campos customizados padronizados no objeto do curso.
     * Aceita os dados de origem tanto como Array quanto como Objeto.
     */
    private function inject_custom_fields($curso, $cfdata) {
        $cfdata = (array) $cfdata;

        $curso->turma_ano_periodo    = isset($cfdata['turma_ano_periodo']) ? trim($cfdata['turma_ano_periodo']) : '';
        $curso->disciplina_id        = isset($cfdata['disciplina_id']) ? trim($cfdata['disciplina_id']) : '';
        $curso->disciplina_descricao = isset($cfdata['disciplina_descricao']) ? trim($cfdata['disciplina_descricao']) : '';
        $curso->disciplina_sigla     = isset($cfdata['disciplina_sigla']) ? trim($cfdata['disciplina_sigla']) : '';
        $curso->curso_codigo         = isset($cfdata['curso_codigo']) ? trim($cfdata['curso_codigo']) : '';
        $curso->curso_descricao      = isset($cfdata['curso_descricao']) ? trim($cfdata['curso_descricao']) : '';
        $curso->diario_id            = isset($cfdata['diario_id']) ? trim($cfdata['diario_id']) : null;
        $curso->sala_tipo            = isset($cfdata['sala_tipo']) ? trim($cfdata['sala_tipo']) : '';

        return $curso;
    }

    /**
     * Verifica se um curso do tipo 'diario' atende aos filtros de busca informados na API.
     */
    private function atende_filtros($curso, $filtros) {
        if (!$filtros['has_filters']) {
            return true;
        }

        $matchq = empty($filtros['q']) || stripos($curso->shortname . ' ' . $curso->fullname, $filtros['q']) !== false;
        $matchsemestre   = empty($filtros['semestre'])   || $curso->turma_ano_periodo == $filtros['semestre'];
        $matchdisciplina = empty($filtros['disciplina']) || $curso->disciplina_id == $filtros['disciplina'];
        $matchcurso      = empty($filtros['curso'])      || $curso->curso_codigo == $filtros['curso'];

        return $matchq && $matchsemestre && $matchdisciplina && $matchcurso;
    }

    /**
     * Busca os valores dos custom fields para uma lista de IDs de cursos.
     * Se $fields for vazio, busca TODOS os custom fields daqueles cursos.
     * Retorna um array no formato: [course_id => [shortname => value, ...]]
     */
    private function get_custom_fields_for_courses(array $courseids, array $fieldstofetch = []) {
        global $DB;

        if (empty($courseids)) {
            return [];
        }

        [$courseinsql, $courseinparams] = $DB->get_in_or_equal($courseids);
        $params = $courseinparams;

        $fieldfilter = "";
        if (!empty($fieldstofetch)) {
            [$fieldinsql, $fieldinparams] = $DB->get_in_or_equal($fieldstofetch);
            $fieldfilter = "AND f.shortname $fieldinsql";
            $params = array_merge($params, $fieldinparams);
        }

        $sql = "SELECT d.id AS dataid, d.instanceid, f.shortname, d.value, d.charvalue
                FROM {customfield_data} d
                JOIN {customfield_field} f ON d.fieldid = f.id
                WHERE d.instanceid $courseinsql
                $fieldfilter";

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
    private function get_autoinscricoes($userid, $alldiarios) {
        global $DB, $CFG;
        $autoinscricoes = [];

        $camporestricao = $DB->get_record('customfield_field', ['shortname' => 'restricoes_de_autoinscricao']);
        if (!$camporestricao) {
            return $autoinscricoes;
        }

        $sqlvitrine = "SELECT c.id, c.fullname, c.shortname
                        FROM {course} c
                        JOIN {customfield_data} d ON d.instanceid = c.id
                        WHERE d.fieldid = ? AND c.visible = 1
                          AND (d.charvalue != '' OR d.value IS NOT NULL AND d.value != '')";

        $cursosvitrine = $DB->get_records_sql($sqlvitrine, [$camporestricao->id]);
        if (empty($cursosvitrine)) {
            return $autoinscricoes;
        }

        $vitrineids = array_column($cursosvitrine, 'id');
        $camposvitrine = [
            'restricoes_de_autoinscricao',
            'turma_ano_periodo',
            'disciplina_id',
            'disciplina_descricao',
            'disciplina_sigla',
            'curso_codigo',
            'curso_descricao',
            'diario_id',
        ];

        $cfvitrine = $this->get_custom_fields_for_courses($vitrineids, $camposvitrine);

        $mapamatriculados = [];
        foreach ($alldiarios as $diarioaluno) {
            $mapamatriculados[$diarioaluno->id] = true;
        }

        foreach ($cursosvitrine as $cursovitrine) {
            $restricoesstr = trim($cfvitrine[$cursovitrine->id]['restricoes_de_autoinscricao'] ?? '');

            if (empty($restricoesstr)) {
                continue;
            }

            $cursovitrine->restricoes_de_autoinscricao = $restricoesstr;
            $this->inject_custom_fields($cursovitrine, $cfvitrine[$cursovitrine->id] ?? []);

            $cursovitrine->is_enrolled = isset($mapamatriculados[$cursovitrine->id]);
            $cursovitrine->viewurl = $CFG->wwwroot . '/course/view.php?id=' . $cursovitrine->id;

            $autoinscricoes[] = $cursovitrine;
        }

        return $autoinscricoes;
    }


    /**
     * Main method to get user's daily courses, groupings and available self-enrolments.
     *
     * @param string $username Username.
     * @param string|null $semestre Semestre filter.
     * @param string $situacao Situacao filter.
     * @param string $ordenacao Sort field.
     * @param string|null $disciplina Disciplina ID filter.
     * @param string|null $curso Curso ID filter.
     * @param string $arquetipo Arquetipo filter.
     * @param string|null $q Query string search filter.
     * @param int $page Page number.
     * @param int $pagesize Page size.
     * @return array Unified list of semestres, disciplinas, cursos, and grouped daily courses.
     */
    public function get_diarios(
        $username,
        $semestre,
        $situacao,
        $ordenacao,
        $disciplina,
        $curso,
        $arquetipo,
        $q,
        $page,
        $pagesize
    ) {
        global $DB, $USER;

        $usuariomoodle = $DB->get_record('user', ['username' => strtolower($username)]);
        $userid = $usuariomoodle ? $usuariomoodle->id : null;

        $alldiarios = [];
        $agrupamentos = [];
        $enrolledcourses = [];

        if ($usuariomoodle) {
            $USER = $usuariomoodle;

            $alldiarios = $this->get_all_diarios($usuariomoodle->username);

            $enrolledcourses = $this->build_timeline_with_progress($usuariomoodle, $ordenacao, $situacao, $alldiarios);
        }

        $filtrosbusca = [
            'semestre'    => $semestre,
            'disciplina'  => $disciplina,
            'curso'       => $curso,
            'q'           => $q,
            'has_filters' => !empty($semestre . $disciplina . $curso . $q),
        ];
        $agrupamentos = $this->process_and_group_courses($usuariomoodle, $enrolledcourses, $alldiarios, $filtrosbusca);

        $vitrine = $this->get_autoinscricoes($userid, $alldiarios);
        if ($vitrine) {
            if (!isset($agrupamentos['autoinscricoes'])) {
                $agrupamentos['autoinscricoes'] = [];
            }
            foreach ($vitrine as $cursovitrine) {
                $agrupamentos['autoinscricoes'][] = $cursovitrine;
            }
        }

        $returnbase = [
            "semestres"   => $this->get_semestres($alldiarios),
            "disciplinas" => $this->get_disciplinas($alldiarios),
            "cursos"      => $this->get_cursos($alldiarios),
        ];

        if (!isset($agrupamentos['diarios'])) {
            $agrupamentos['diarios'] = [];
        }

        return array_merge($returnbase, $agrupamentos);
    }

    /**
     * Extrai e monta a timeline de cursos calculando o progresso apenas se o usuário for aluno.
     */
    private function build_timeline_with_progress($usuario, $ordenacao, $situacao, $alldiarios) {
        global $DB, $CFG;
        $enrolledcourses = [];

        $mycourses = enrol_get_my_courses(
            'id, fullname, shortname, visible, startdate, enddate, enablecompletion, cacherev',
            $ordenacao
        );

        // Pré-carrega contextos na RAM para evitar N+1.
        $mycourseids = array_column($mycourses, 'id');
        $this->preload_course_contexts($mycourseids);

        // Indexa os diários já carregados por ID para busca instantânea.
        $alldiariosbyid = [];
        foreach ($alldiarios as $d) {
            $alldiariosbyid[$d->id] = $d;
        }

        $favrecords = $DB->get_records(
            'favourite',
            ['component' => 'core_course', 'itemtype' => 'courses', 'userid' => $usuario->id],
            '',
            'itemid, id'
        );
        $hiddenprefs = $DB->get_records_select(
            'user_preferences',
            "userid = ? AND name LIKE 'block_myoverview_hidden_course_%'",
            [$usuario->id],
            '',
            'name, value'
        );
        $hiddenids = [];
        if ($hiddenprefs) {
            foreach ($hiddenprefs as $pref) {
                $id = str_replace('block_myoverview_hidden_course_', '', $pref->name);
                $hiddenids[$id] = true;
            }
        }

        $time = time();
        foreach ($mycourses as $c) {
            $ishidden = isset($hiddenids[$c->id]);
            $isfav = isset($favrecords[$c->id]);

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
                if ($situacao === 'favourites' && !$isfav) {
                    continue;
                }
                if ($situacao !== 'favourites' && $class !== $situacao) {
                    continue;
                }
            }
            if ($situacao === 'all' && $ishidden) {
                continue;
            }

            $enrolledcourses[] = (object)[
                'id'          => $c->id,
                'fullname'    => $c->fullname,
                'shortname'   => $c->shortname,
                'viewurl'     => $CFG->wwwroot . '/course/view.php?id=' . $c->id,
                'progress'    => null,
                'hasprogress' => false,
                'isfavourite' => $isfav,
                'visible'     => $c->visible,
                '_course'     => $c,
                '_class'      => $class,
            ];
        }

        return $enrolledcourses;
    }

    /**
     * Abastece a memória RAM com os contextos dos cursos informados de uma só vez (Evita N+1).
     */
    private function preload_course_contexts(array $courseids) {
        global $DB;
        if (empty($courseids)) {
            return;
        }

        [$ctxsql, $ctxparams] = $DB->get_in_or_equal($courseids);
        $ctxfields = \context_helper::get_preload_record_columns_sql('ctx');
        $sqlcontexts = "SELECT $ctxfields FROM {context} ctx WHERE ctx.contextlevel = 50 AND ctx.instanceid $ctxsql";

        if ($recordset = $DB->get_recordset_sql($sqlcontexts, $ctxparams)) {
            foreach ($recordset as $ctxrecord) {
                \context_helper::preload_from_record($ctxrecord);
            }
            $recordset->close();
        }
    }

    /**
     * Varre as matrículas da timeline, indexa metadados ausentes e faz a distribuição nas abas correspondentes.
     */
    private function process_and_group_courses($usuario, array $enrolledcourses, array $alldiarios, array $filtrosbusca) {
        $agrupamentos = [];
        if (empty($enrolledcourses)) {
            return $agrupamentos;
        }

        // Indexa os diários por ID para busca rápida em memória.
        $alldiariosbyid = [];
        foreach ($alldiarios as $diario) {
            $alldiariosbyid[$diario->id] = $diario;
        }

        // Verifica se algum curso vindo da timeline precisa ter seus custom fields carregados.
        $missingids = [];
        foreach ($enrolledcourses as $diario) {
            if (!isset($alldiariosbyid[$diario->id])) {
                $missingids[] = $diario->id;
            }
        }

        $cfsmissing = [];
        if (!empty($missingids)) {
            $camposrelevantes = [
                'turma_ano_periodo',
                'disciplina_id',
                'disciplina_descricao',
                'disciplina_sigla',
                'curso_codigo',
                'curso_descricao',
                'diario_id',
                'sala_tipo',
            ];
            $cfsmissing = $this->get_custom_fields_for_courses($missingids, $camposrelevantes);
        }

        // Distribuição estruturada nas abas (diarios, coordenacoes, projetos...).
        foreach ($enrolledcourses as $diario) {
            $coursecontext = \context_course::instance($diario->id);

            $cursolimpo = (object) [
                'id'                 => $diario->id,
                'fullname'           => $diario->fullname,
                'shortname'          => $diario->shortname,
                'viewurl'            => $diario->viewurl,
                'progress'           => null,
                'hasprogress'        => false,
                'isfavourite'        => $diario->isfavourite,
                'visible'            => $diario->visible,
                'is_enrolled'        => true,
                'can_set_visibility' => has_capability('moodle/course:visibility', $coursecontext, $usuario) ? 1 : 0,
            ];

            $cfdados = [];
            if (isset($alldiariosbyid[$diario->id])) {
                $ad = $alldiariosbyid[$diario->id];
                $cfdados = [
                    'turma_ano_periodo'    => $ad->turma_ano_periodo,
                    'disciplina_id'        => $ad->disciplina_id,
                    'disciplina_descricao' => $ad->disciplina_descricao,
                    'disciplina_sigla'     => $ad->disciplina_sigla,
                    'curso_codigo'         => $ad->curso_codigo,
                    'curso_descricao'      => $ad->curso_descricao,
                    'diario_id'            => $ad->diario_id,
                    'sala_tipo'            => $ad->sala_tipo ?? '',
                ];
            } else if (isset($cfsmissing[$diario->id])) {
                $cfdados = $cfsmissing[$diario->id];
            }

            $this->inject_custom_fields($cursolimpo, $cfdados);

            $salatipooriginal = !empty($cfdados['sala_tipo']) ? strtolower(trim($cfdados['sala_tipo'])) : 'diarios';
            $targetaba = ($salatipooriginal === 'autoinscricoes') ? 'diarios' : $salatipooriginal;

            if (!isset($agrupamentos[$targetaba])) {
                $agrupamentos[$targetaba] = [];
            }

            if ($targetaba === 'diarios') {
                if ($this->atende_filtros($cursolimpo, $filtrosbusca)) {
                    $agrupamentos['diarios'][] = $cursolimpo;
                }
            } else {
                $agrupamentos[$targetaba][] = $cursolimpo;
            }
        }

        return $agrupamentos;
    }

    /**
     * Executes the service call to fetch user daily courses.
     *
     * @return array Service output.
     */
    public function do_call() {
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
