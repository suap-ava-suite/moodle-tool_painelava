<?php
// This file is part of "Moodle Painel AVA Integration"
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
 * @category    integration
 * @copyright   2025 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_painelava;


function get_last_sort_order($tablename)
{
    global $DB;

    if (!\is_string($tablename) || !\preg_match('/^[a-z][a-z0-9_]*$/i', $tablename)) {
        throw new \coding_exception('Invalid table name provided to get_last_sort_order().');
    }

    $l = $DB->get_record_sql('SELECT coalesce(max(sortorder), 0) + 1 as sortorder from {' . $tablename . '}');
    return $l->sortorder;
}


function get_or_create($tablename, $keys, $values)
{
    global $DB;

    if (!\is_string($tablename) || !\preg_match('/^[a-z][a-z0-9_]*$/i', $tablename)) {
        throw new \coding_exception('Invalid table name provided to get_or_create().');
    }

    $record = $DB->get_record($tablename, $keys);
    if (!$record) {
        $record = (object)array_merge($keys, $values);
        $record->id = $DB->insert_record($tablename, $record);
    }
    return $record;
}


function config($name)
{
    return get_config('tool_painelava', $name);
}


function aget($array, $key, $default = null)
{
    return \array_key_exists($key, $array) ? $array[$key] : $default;
}


function get_recordset_as_array($sql, $params)
{
    global $DB;

    $result = [];
    $recordset = $DB->get_recordset_sql($sql, $params);
    try {
        foreach ($recordset as $disciplina) {
            $result[] = $disciplina;
        }
    } finally {
        $recordset->close();
    }
    return $result;
}
