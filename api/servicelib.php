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

/**
 * Base service class for API operations.
 *
 * @package    tool_painelava
 * @copyright  2020 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class service {
    /**
     * Authenticates the request via token header.
     *
     * @return void
     * @throws \Exception if authentication fails or is missing.
     */
    public function authenticate() {
        $syncupautotoken = config('auth_token');

        $headers = getallheaders();
        $authenticationkey = array_key_exists('Authentication', $headers) ? "Authentication" : "authentication";
        if (!array_key_exists($authenticationkey, $headers)) {
            throw new \Exception("Bad Request - Authentication not informed", 400);
        }

        if ("Token $syncupautotoken" != $headers[$authenticationkey]) {
            throw new \Exception("Unauthorized", 401);
        }
    }

    /**
     * Entry point to handle and execute the service call.
     *
     * @return void
     */
    public function call() {
        $this->authenticate();
        echo json_encode($this->do_call());
    }

    /**
     * Inner execution method for the service logic.
     *
     * @return mixed Service response data.
     * @throws \Exception if not overridden.
     */
    public function do_call() {
        throw new \Exception("Não implementado", 501);
    }
}
