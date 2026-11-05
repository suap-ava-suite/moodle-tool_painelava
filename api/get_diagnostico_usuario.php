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
 * Diagnóstico de usuário (somente leitura) para o Painel AVA.
 *
 * @package    tool_painelava
 * @copyright  2026 IFRN
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
 * Service que devolve o diagnóstico de um usuário.
 *
 * @package    tool_painelava
 * @copyright  2026 IFRN
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_diagnostico_usuario_service extends \tool_painelava\service {
    /**
     * Executa o diagnóstico.
     *
     * @return array Diagnóstico do usuário.
     * @throws \Exception 400 se o username não for informado.
     */
    public function do_call() {
        $username = (string) \tool_painelava\aget($_GET, 'username', '');
        return \tool_painelava\diagnostico::usuario($username);
    }
}
