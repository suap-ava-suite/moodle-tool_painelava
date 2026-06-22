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
 * API script to sync user preferences to external painel.
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
require_once("../locallib.php");

// A autenticação feita via token.
$syncupautotoken = config('auth_token');
$painelurl = config('painel_url');

// Força saída JSON limpa.
header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) {
    ob_end_clean();
}

/**
 * Outputs consistent JSON response.
 *
 * @param mixed $data The data to encode as JSON.
 * @param int $status HTTP status code.
 * @return void
 */
function output_json($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Captura todos os erros como exceção (para não poluir o JSON).
set_error_handler(function ($severity, $message, $file, $line) {
    throw new \ErrorException($message, 500, $severity, $file, $line);
});

try {
    global $USER;

    // Parâmetros via GET.
    $category = required_param('category', PARAM_RAW);
    $key      = required_param('key', PARAM_RAW);
    $value    = required_param('value', PARAM_RAW);

    $username = $USER->username;

    $url = $painelurl . '/api/v1/set_user_preference/'
         . '?username=' . urlencode($username)
         . '&category=' . urlencode($category)
         . '&key=' . urlencode($key)
         . '&value=' . urlencode($value);

    $curl = new \curl();
    $options = [
        'CURLOPT_RETURNTRANSFER' => true,
        'CURLOPT_TIMEOUT' => 10,
        'CURLOPT_HTTPHEADER' => ["Authorization: Token $syncupautotoken"],
        'CURLOPT_FAILONERROR' => true,
    ];

    $response = $curl->get($url, [], $options);
    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        output_json([
            'status' => 'erro',
            'mensagem' => 'Resposta inválida do painel Django',
            'resposta' => $response,
        ], 500);
    }

    output_json([
        'status' => 'ok',
        'data' => $data,
    ]);
} catch (\Exception $e) {
    output_json([
        'status' => 'erro',
        'mensagem' => $e->getMessage(),
    ], $e->getCode() ?: 500);
}
