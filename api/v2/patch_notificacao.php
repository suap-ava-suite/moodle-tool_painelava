<?php
namespace tool_painelava\v2;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../servicelib.php');

class patch_notificacao_service extends \tool_painelava\service
{
    public function do_call() {
        return [
            [
                'error' => false,
                'data' => [
                    'notificationid' => 0,
                    'warnings' => []
                ]
            ]
        ];
    }
}
