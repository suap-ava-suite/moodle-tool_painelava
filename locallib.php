<?php
// This file is part of "Moodle Painel AVA Integration"
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
 * @copyright   2025 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_painelava;


/**
 * Get the next sort order value for a given table.
 *
 * @param string $tablename The name of the database table.
 * @return int The next sort order value.
 * @throws \coding_exception If the table name is invalid.
 */
function get_last_sort_order($tablename) {
    global $DB;

    if (!\is_string($tablename) || !\preg_match('/^[a-z][a-z0-9_]*$/i', $tablename)) {
        throw new \coding_exception('Invalid table name provided to get_last_sort_order().');
    }

    $lastsortorderrecord = $DB->get_record_sql('SELECT coalesce(max(sortorder), 0) + 1 as sortorder from {' . $tablename . '}');
    return $lastsortorderrecord->sortorder;
}


/**
 * Gets a record matching the keys, or creates it with keys and values.
 *
 * @param string $tablename The name of the database table.
 * @param array $keys The search keys to locate the record.
 * @param array $values The default values to use if creating a new record.
 * @return \stdClass The existing or newly created record.
 * @throws \coding_exception If the table name is invalid.
 */
function get_or_create($tablename, $keys, $values) {
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


/**
 * Helper function to retrieve a configuration value for this tool.
 *
 * @param string $name The name of the configuration setting.
 * @return string|false The configuration value, or false if not found.
 */
function config($name) {
    return get_config('tool_painelava', $name);
}


/**
 * Safe array get function to retrieve a value by key.
 *
 * @param array $array The source array.
 * @param string|int $key The key to look up.
 * @param mixed $default The default value to return if the key is not found.
 * @return mixed The array value, or the default value.
 */
function aget($array, $key, $default = null) {
    return \array_key_exists($key, $array) ? $array[$key] : $default;
}


/**
 * Retrieves a recordset and returns it as an array, ensuring the recordset is closed.
 *
 * @param string $sql The SQL query to execute.
 * @param array|null $params Query parameters.
 * @return array The array of retrieved records.
 */
function get_recordset_as_array($sql, $params) {
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
