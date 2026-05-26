<?php
// This file is part of "Moodle Painel Integration"
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
 * Plugin upgrade helper functions are defined here.
 *
 * @package     tool_painelava
 * @category    upgrade
 * @copyright   2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @see         https://docs.moodle.org/dev/Data_definition_API
 * @see         https://docs.moodle.org/dev/XMLDB_creating_new_DDL_functions
 * @see         https://docs.moodle.org/dev/Upgrade_API
 */
namespace tool_painelava;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/painelava/locallib.php');

class migration_helpers {
    
    public static function save_course_custom_field($categoryid, $shortname, $name, $type = 'text', $configdata = '{"required":"0","uniquevalues":"0","displaysize":50,"maxlength":250,"ispassword":"0","link":"","locked":"0","visibility":"0"}')
    {
        return get_or_create(
            'customfield_field',
            ['shortname' => $shortname],
            ['categoryid' => $categoryid, 'name' => $name, 'type' => $type, 'configdata' => $configdata, 'timecreated' => time(), 'timemodified' => time(), 'sortorder' => get_last_sort_order('customfield_field')]
        );
    }

    public static function save_user_custom_field($categoryid, $shortname, $name, $datatype = 'text', $visible = 1, $param1value = NULL, $param2value = NULL)
    {
        return get_or_create(
            'user_info_field',
            ['shortname' => $shortname],
            ['categoryid' => $categoryid, 'name' => $name, 'description' => $name, 'descriptionformat' => 2, 'datatype' => $datatype, 'visible' => $visible, 'param1' => $param1value, 'param2' => $param2value]
        );
    }

    public static function bulk_course_custom_field()
    {
        global $DB;
        $cid = get_or_create(
            'customfield_category',
            ['name' => 'Painel AVA', 'component' => 'core_course', 'area' => 'course'],
            ['sortorder' => get_last_sort_order('customfield_category'), 'itemid' => 0, 'contextid' => 1, 'descriptionformat' => 0, 'timecreated' => time(), 'timemodified' => time()]
        )->id;

        self::save_course_custom_field($cid, 'sala_tipo', 'Tipo de sala');
        self::save_course_custom_field($cid, 'turma_autoinscricao', 'Turma aceita autoinscrição', 'checkbox');
        self::save_course_custom_field($cid, 'restricoes_de_autoinscricao', 'Restrições de autoinscrição');
    }
}


/**
 * Executes plugin migration steps for tool_painelava.
 *
 * Ensures required database structures exist (for example, the
 * tool_painelava_logging table) and creates required course custom fields.
 *
 * @param int $oldversion Previously installed plugin version.
 * @return bool True when migration steps complete successfully.
 */
function tool_painelava_migrate($oldversion)
{
    global $DB;

    $dbman = $DB->get_manager();

    $logging = new \xmldb_table("tool_painelava_logging");
    if (!$dbman->table_exists($logging)) {
        $logging->add_field("id",                   XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE,  null, null, null);
        $logging->add_field("userid",               XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);
        $logging->add_field("targetuserid",         XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);
        $logging->add_field("user_ipaddress",       XMLDB_TYPE_CHAR,    '45',       null, null, null,            null, null, null);
        $logging->add_field("targetuser_ipaddress", XMLDB_TYPE_CHAR,    '45',       null, null, null,            null, null, null);
        $logging->add_field("timecreated",          XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);

        $logging->add_key("primary",          XMLDB_KEY_PRIMARY,  ["id"],         null,       null);

        $logging->add_index('idx_users',              XMLDB_INDEX_NOTUNIQUE, ['targetuserid', 'userid']);
        $logging->add_index('idx_timecreated',        XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

        $dbman->create_table($logging);
    }

    migration_helpers::bulk_course_custom_field();

    return true;
}
