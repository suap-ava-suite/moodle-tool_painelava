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
 * Painel AVA Integration
 *
 * This module provides extensive analytics on a platform of choice
 * Currently support Google Analytics and Piwik
 *
 * @package     tool_painelava
 * @category    upgrade
 * @copyright   2020 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_painelava;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Exception handler to output exceptions in JSON format.
 *
 * @param \Throwable $exception The exception to handle.
 * @return void
 */
function exception_handler($exception) {
    /*
        200 – 208, 226,
        300 – 305, 307, 308
        400 – 417, 422 – 424, 426, 428 – 429, 431
        500 – 508, 510 – 511
    */
    $errorcode = $exception->getCode() ?: 500;
    http_response_code($errorcode);
    die(json_encode(["error" => ["message" => $exception->getMessage(), "code" => $errorcode]]));
}

try {
    // Desabilita verificação CSRF para esta API.
    if (!defined('NO_MOODLE_COOKIES')) {
        define('NO_MOODLE_COOKIES', true);
    }

    require_once('../../../../config.php');
    header('Content-Type: application/json; charset=utf-8');
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    set_exception_handler('\tool_painelava\exception_handler');

    $whitelist = [
        'get_diarios',
        'get_progresso',
        'get_atualizacoes_counts',
        'get_course_info',

        'set_favourite_course',
        'set_visible_course',
        'set_user_preference',

        'sync_user_preference',
        'sync_up_enrolments',
        'sync_down_grades',
        'enrol_course',
        'suspend_enrol',
    ];
    $params = explode('&', $_SERVER["QUERY_STRING"]);
    $servicename = $params[0];

    if (!in_array($servicename, $whitelist)) {
        throw new \Exception("Serviço não existe", 404);
    }

    require_once(__DIR__ . "/{$servicename}.php");

    $serviceclass = "\\tool_painelava\\{$servicename}_service";

    $service = new $serviceclass();
    $service->call();
} catch (\Throwable $e) {
    exception_handler($e);
}
