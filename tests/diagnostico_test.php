<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace tool_painelava;

/**
 * Testes de \tool_painelava\diagnostico.
 *
 * @package    tool_painelava
 * @copyright  2026 IFRN
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_painelava\diagnostico
 */
final class diagnostico_test extends \advanced_testcase {
    /** @var int Instante de referência fixo. */
    const AGORA = 1790000000;

    /**
     * Prepara o ambiente.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Garante o custom field de restrições de autoinscrição.
     */
    private function garantir_campo(): void {
        global $DB;
        if ($DB->record_exists('customfield_field', ['shortname' => diagnostico::CAMPO_RESTRICAO])) {
            return;
        }
        $gerador = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $categoria = $gerador->create_category(['component' => 'core_course', 'area' => 'course']);
        $gerador->create_field([
            'categoryid' => $categoria->get('id'),
            'shortname' => diagnostico::CAMPO_RESTRICAO,
            'type' => 'text',
        ]);
    }

    /**
     * Cria um curso com regra de autoinscrição.
     *
     * @param string $regra Regra.
     * @param int $visible Visibilidade.
     * @return \stdClass Curso.
     */
    private function curso_com_regra(string $regra, int $visible = 1): \stdClass {
        $this->garantir_campo();
        return $this->getDataGenerator()->create_course([
            'visible' => $visible,
            'customfield_' . diagnostico::CAMPO_RESTRICAO => $regra,
        ]);
    }

    /**
     * Testa: username vazio gera 400.
     */
    public function test_username_vazio_gera_400(): void {
        $this->expectExceptionCode(400);
        diagnostico::usuario('  ');
    }

    /**
     * Testa: usuario inexistente.
     */
    public function test_usuario_inexistente(): void {
        $curso = $this->curso_com_regra("tipo_usuario == 'Aluno'");
        $resultado = diagnostico::usuario('NaoExiste');
        $this->assertFalse($resultado['exists']);
        $this->assertSame('naoexiste', $resultado['username']);
        $this->assertSame((int) $curso->id, $resultado['autoinscricoes'][0]['courseid']);
        $this->assertNull($resultado['autoinscricoes'][0]['enrolment']);
    }

    /**
     * Testa: usuario sem inscricoes.
     */
    public function test_usuario_sem_inscricoes(): void {
        $user = $this->getDataGenerator()->create_user(['username' => 'aluno1', 'suspended' => 1]);
        $resultado = diagnostico::usuario('ALUNO1', self::AGORA);

        $this->assertTrue($resultado['exists']);
        $this->assertSame((int) $user->id, $resultado['user']['id']);
        $this->assertTrue($resultado['user']['suspended']);
        $this->assertSame(['active' => 0, 'suspended' => 0, 'total' => 0], $resultado['enrolments']);
    }

    /**
     * Testa: contagem ativa suspensa e expirada.
     */
    public function test_contagem_ativa_suspensa_e_expirada(): void {
        $gerador = $this->getDataGenerator();
        $user = $gerador->create_user(['username' => 'aluno2']);
        $c1 = $gerador->create_course();
        $c2 = $gerador->create_course();
        $c3 = $gerador->create_course();

        $gerador->enrol_user($user->id, $c1->id, 'student');
        $gerador->enrol_user($user->id, $c2->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $gerador->enrol_user($user->id, $c3->id, 'student', 'manual', 0, self::AGORA - 10);

        $resultado = diagnostico::usuario('aluno2', self::AGORA);
        $this->assertSame(['active' => 1, 'suspended' => 2, 'total' => 3], $resultado['enrolments']);
    }

    /**
     * Testa: autoinscricoes com status e ocultos.
     */
    public function test_autoinscricoes_com_status_e_ocultos(): void {
        $gerador = $this->getDataGenerator();
        $user = $gerador->create_user(['username' => 'aluno3']);
        $visivel = $this->curso_com_regra("tipo_usuario == 'Aluno'");
        $oculto = $this->curso_com_regra("tipo_usuario == 'Aluno'", 0);
        $suspenso = $this->curso_com_regra("tipo_usuario == 'Aluno'");
        $semregra = $this->curso_com_regra('   ');

        $gerador->enrol_user($user->id, $visivel->id, 'student');
        $gerador->enrol_user($user->id, $suspenso->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);

        $resultado = diagnostico::usuario('aluno3', self::AGORA);
        $porcurso = array_column($resultado['autoinscricoes'], null, 'courseid');

        $this->assertArrayNotHasKey((int) $semregra->id, $porcurso);
        $this->assertSame('active', $porcurso[(int) $visivel->id]['enrolment']['status']);
        $this->assertTrue($porcurso[(int) $visivel->id]['visible']);
        $this->assertFalse($porcurso[(int) $oculto->id]['visible']);
        $this->assertNull($porcurso[(int) $oculto->id]['enrolment']);
        $this->assertSame('suspended', $porcurso[(int) $suspenso->id]['enrolment']['status']);
        $this->assertTrue($porcurso[(int) $suspenso->id]['enrolment']['user_enrolment_suspended']);
        $this->assertSame("tipo_usuario == 'Aluno'", $porcurso[(int) $visivel->id]['restricoes_de_autoinscricao']);
    }

    /**
     * Testa: instancia de inscricao desabilitada.
     */
    public function test_instancia_de_inscricao_desabilitada(): void {
        global $DB;
        $gerador = $this->getDataGenerator();
        $user = $gerador->create_user(['username' => 'aluno4']);
        $curso = $gerador->create_course();
        $gerador->enrol_user($user->id, $curso->id, 'student');
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['courseid' => $curso->id, 'enrol' => 'manual']);

        $resultado = diagnostico::usuario('aluno4', self::AGORA);
        $this->assertSame(['active' => 0, 'suspended' => 1, 'total' => 1], $resultado['enrolments']);
    }
}
