<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace tool_painelava\v2;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../servicelib.php');

class get_notificacoes_service extends \tool_painelava\service
{
    public function do_call() {
        global $DB;
        $username = strtolower($_GET['username'] ?? '');
        $user = $DB->get_record('user', ['username' => $username]);
        if (!$user) {
            return ['result' => [], 'unreadcount' => 0];
        }
        return [
            'result' => [],
            'unreadcount' => 0,
        ];
    }
}
