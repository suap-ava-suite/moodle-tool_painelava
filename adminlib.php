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
 * @category    upgrade
 * @copyright   2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class tool_painelava_admin_settingspage extends admin_settingpage
{
    public function __construct($admin_mode) {
        $plugin_name = 'tool_painelava';
        parent::__construct($plugin_name, get_string('pluginname', $plugin_name), 'moodle/site:config', false, null);
        $this->setup($admin_mode);
    }

    function _($str, $args = null, $lazyload = false) {
        return get_string($str, $this->name);
    }

    function add_heading($name) {
        $this->add(new admin_setting_heading("{$this->name}/$name", $this->_($name), $this->_("{$name}_desc")));
    }

    function add_configtext($name, $default = '') {
        $this->add(new admin_setting_configtext("{$this->name}/$name", $this->_($name), $this->_("{$name}_desc"), $default));
    }

    function add_configtextarea($name, $default = '') {
        $this->add(new admin_setting_configtextarea("{$this->name}/$name", $this->_($name), $this->_("{$name}_desc"), $default));
    }

    function add_configcheckbox($name, $default = 0) {
        $this->add(new admin_setting_configcheckbox("{$this->name}/$name", $this->_($name), $this->_("{$name}_desc"), $default));
    }

    function setup($admin_mode) {
        global $CFG;
        if ($admin_mode) {
            $this->add_heading('auth_token_header');
            $this->add_configtext("auth_token");
            $this->add_configtext("painel_url", 'https://ava.ifrn.edu.br');
            $this->add_configtext("course_custom_field_sala_tipo", 'sala_tipo');
        }
    }
}
