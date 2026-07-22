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

namespace aiprovider_openaicompatible\test;

/**
 * Shared helpers for the aiprovider_openaicompatible test suite.
 *
 * On Moodle 4.5 the provider is instantiated with `new provider()` and all of its
 * configuration - both provider-level and per-action - lives in plugin config under the
 * component `aiprovider_openaicompatible`. These helpers centralise that setup.
 *
 * @package    aiprovider_openaicompatible
 * @copyright  2025   Adorsys GIS <gis-udm@adorsys.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait testcase_helper_trait {
    /**
     * Create a fresh provider instance reading the current plugin config.
     *
     * @return \aiprovider_openaicompatible\provider
     */
    protected function create_provider(): \aiprovider_openaicompatible\provider {
        return new \aiprovider_openaicompatible\provider();
    }

    /**
     * Set provider-level plugin config keys.
     *
     * @param array $config Key => value pairs stored under the aiprovider_openaicompatible component.
     */
    protected function set_provider_config(array $config): void {
        foreach ($config as $key => $value) {
            set_config($key, $value, 'aiprovider_openaicompatible');
        }
    }

    /**
     * Set per-action plugin config keys.
     *
     * Per-action settings are stored globally as `action_{actionname}_{key}` under the
     * aiprovider_openaicompatible component.
     *
     * @param string $actionname The short action name, e.g. generate_text.
     * @param array $config Key => value pairs, e.g. ['model' => 'gpt-4o'].
     */
    protected function set_action_config(string $actionname, array $config): void {
        foreach ($config as $key => $value) {
            set_config("action_{$actionname}_{$key}", $value, 'aiprovider_openaicompatible');
        }
    }
}
