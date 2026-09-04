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
 * Painel AVA Integration API v2
 *
 * @package     tool_painelava
 * @category    api
 * @copyright   2026 IFRN
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_painelava\v2;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function exception_handler($exception) {
    $errorcode = $exception->getCode() ?: 500;
    http_response_code($errorcode);
    die(json_encode(["error" => ["message" => $exception->getMessage(), "code" => $errorcode]]));
}

try {
    if (!defined('NO_MOODLE_COOKIES')) {
        define('NO_MOODLE_COOKIES', true);
    }

    require_once('../../../../../config.php');
    header('Content-Type: application/json; charset=utf-8');

    set_exception_handler('\tool_painelava\v2\exception_handler');

    $whitelist = [
        'get_notificacoes',
        'patch_notificacao',
        'get_conversas',
        'patch_conversa',
        'get_salas',
        'token_refresh',
        'token_revoke',
    ];

    $params = explode('&', $_SERVER["QUERY_STRING"] ?? '');
    $servicename = $params[0] ?? '';

    if (!in_array($servicename, $whitelist)) {
        throw new \Exception("Serviço v2 não existe", 404);
    }

    require_once(__DIR__ . "/{$servicename}.php");

    $serviceclass = "\\tool_painelava\\v2\\{$servicename}_service";
    $service = new $serviceclass();
    $service->call();
} catch (\Throwable $e) {
    exception_handler($e);
}
