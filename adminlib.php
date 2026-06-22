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
 * Custom admin settings page for the Painel AVA integration.
 *
 * @package     tool_painelava
 * @copyright   2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_painelava_admin_settingspage extends admin_settingpage
{
    /**
     * Constructor for the setting page.
     *
     * @param bool $adminmode Mode for admin settings.
     */
    public function __construct($adminmode) {
        $pluginname = 'tool_painelava';
        parent::__construct($pluginname, get_string('pluginname', $pluginname), 'moodle/site:config', false, null);
        $this->setup($adminmode);
    }

    /**
     * Helper translation wrapper for setting strings.
     *
     * @param string $str The identifier string.
     * @param mixed $args Arguments for string substitution.
     * @param bool $lazyload Lazy loading setting.
     * @return string The translated string.
     */
    public function _($str, $args = null, $lazyload = false) {
        return get_string($str, $this->name);
    }

    /**
     * Add a setting heading.
     *
     * @param string $name The setting identifier name.
     */
    public function add_heading($name) {
        $this->add(new admin_setting_heading("{$this->name}/$name", $this->_($name), $this->_("{$name}_desc")));
    }

    /**
     * Add a config text field setting.
     *
     * @param string $name The setting name.
     * @param string $default The default value.
     */
    public function add_configtext($name, $default = '') {
        $this->add(new admin_setting_configtext("{$this->name}/$name", $this->_($name), $this->_("{$name}_desc"), $default));
    }

    /**
     * Add a config textarea setting.
     *
     * @param string $name The setting name.
     * @param string $default The default value.
     */
    public function add_configtextarea($name, $default = '') {
        $this->add(new admin_setting_configtextarea("{$this->name}/$name", $this->_($name), $this->_("{$name}_desc"), $default));
    }

    /**
     * Add a config checkbox setting.
     *
     * @param string $name The setting name.
     * @param int $default The default value.
     */
    public function add_configcheckbox($name, $default = 0) {
        $this->add(new admin_setting_configcheckbox("{$this->name}/$name", $this->_($name), $this->_("{$name}_desc"), $default));
    }

    /**
     * Set up all config items for the settings page.
     *
     * @param bool $adminmode Mode for admin settings.
     */
    public function setup($adminmode) {
        global $CFG;
        if ($adminmode) {
            $this->add_heading('auth_token_header');
            $this->add_configtext("auth_token");
            $this->add_configtext("painel_url", 'https://ava.ifrn.edu.br');
            $this->add_configtext("course_custom_field_sala_tipo", 'sala_tipo');
        }
    }
}
