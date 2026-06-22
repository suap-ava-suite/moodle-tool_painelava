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

/**
 * Uninstall hook for tool_painelava.
 *
 * @package    tool_painelava
 * @copyright  2024 IFRN
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Called just before the plugin tables and config are removed.
 */
function xmldb_tool_painelava_uninstall(): void {
    // Remove all plugin configuration values from the config_plugins table.
    // Although Moodle purges config_plugins entries automatically when a plugin
    // is uninstalled, explicitly calling unset_all_config_for_plugin here
    // guarantees removal even in environments that use custom uninstall flows
    // or partial upgrade states.
    unset_all_config_for_plugin('tool_painelava');
}
