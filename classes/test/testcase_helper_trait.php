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
 * Trait for test cases.
 *
 * @package    aiprovider_openaicompatible
 * @copyright  2025   Adorsys GIS <gis-udm@adorsys.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait testcase_helper_trait {
    /**
     * Create the provider object.
     *
     * @param string $actionclass The action class to use.
     * @param array $actionconfig The action configuration to use.
     */
    public function create_provider(
        string $actionclass,
        array $actionconfig = [],
    ): \core_ai\provider {
        $manager = \core\di::get(\core_ai\manager::class);
        $config = [
            'apikey' => '123',
            'enableuserratelimit' => true,
            'userratelimit' => 1,
            'enableglobalratelimit' => true,
            'globalratelimit' => 1,
        ];
        $defaultactionconfig = [
            $actionclass => [
                'settings' => [
                    'model' => 'gpt-4o',
                    'endpoint' => "https://api.openai.com/v1/chat/completions",
                ],
            ],
        ];
        foreach ($actionconfig as $key => $value) {
            $defaultactionconfig[$actionclass]['settings'][$key] = $value;
        }
        $provider = $manager->create_provider_instance(
            classname: '\aiprovider_openaicompatible\provider',
            name: 'dummy',
            config: $config,
            actionconfig: $defaultactionconfig,
        );

        return $provider;
    }

    /**
     * Expected error message when a user rate limit is reached.
     *
     * The wording is produced by core and changed in Moodle 5.1 (localised strings); Moodle 5.0
     * returned fixed English text. Resolve the correct value for the running version.
     *
     * @return string
     */
    public function get_user_ratelimit_message(): string {
        global $CFG;
        if ($CFG->branch < 501) {
            return 'User rate limit exceeded';
        }
        return 'You have reached the maximum number of AI requests you can make in an hour. Try again later.';
    }

    /**
     * Expected error message when the global (site-wide) rate limit is reached.
     *
     * @return string
     */
    public function get_global_ratelimit_message(): string {
        global $CFG;
        if ($CFG->branch < 501) {
            return 'Global rate limit exceeded';
        }
        return 'The AI service has reached the maximum number of site-wide requests per hour. Try again later.';
    }
}
