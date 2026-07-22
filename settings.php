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
 * Plugin administration pages are defined here.
 *
 * @package     aiprovider_openaicompatible
 * @copyright   2025   Adorsys GIS <gis-udm@adorsys.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_ai\admin\admin_settingspage_provider;

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingspage_provider(
        'aiprovider_openaicompatible',
        new lang_string('pluginname', 'aiprovider_openaicompatible'),
        'moodle/site:config',
        true,
    );

    $settings->add(new admin_setting_heading(
        'aiprovider_openaicompatible/general',
        new lang_string('settings', 'core'),
        '',
    ));

    $settings->add(new admin_setting_configtext(
        'aiprovider_openaicompatible/apiendpoint',
        new lang_string('apiendpoint', 'aiprovider_openaicompatible'),
        new lang_string('apiendpoint_desc', 'aiprovider_openaicompatible'),
        'https://api.openai.com/v1',
        PARAM_URL,
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'aiprovider_openaicompatible/apikey',
        new lang_string('apikey', 'aiprovider_openaicompatible'),
        new lang_string('apikey_desc', 'aiprovider_openaicompatible'),
        '',
    ));

    $settings->add(new admin_setting_configtext(
        'aiprovider_openaicompatible/orgid',
        new lang_string('orgid', 'aiprovider_openaicompatible'),
        new lang_string('orgid_desc', 'aiprovider_openaicompatible'),
        '',
        PARAM_TEXT,
    ));

    $settings->add(new admin_setting_configcheckbox(
        'aiprovider_openaicompatible/enableglobalratelimit',
        new lang_string('enableglobalratelimit', 'aiprovider_openaicompatible'),
        new lang_string('enableglobalratelimit_desc', 'aiprovider_openaicompatible'),
        0,
    ));

    $settings->add(new admin_setting_configtext(
        'aiprovider_openaicompatible/globalratelimit',
        new lang_string('globalratelimit', 'aiprovider_openaicompatible'),
        new lang_string('globalratelimit_desc', 'aiprovider_openaicompatible'),
        100,
        PARAM_INT,
    ));
    $settings->hide_if(
        'aiprovider_openaicompatible/globalratelimit',
        'aiprovider_openaicompatible/enableglobalratelimit',
        'eq',
        0,
    );

    $settings->add(new admin_setting_configcheckbox(
        'aiprovider_openaicompatible/enableuserratelimit',
        new lang_string('enableuserratelimit', 'aiprovider_openaicompatible'),
        new lang_string('enableuserratelimit_desc', 'aiprovider_openaicompatible'),
        0,
    ));

    $settings->add(new admin_setting_configtext(
        'aiprovider_openaicompatible/userratelimit',
        new lang_string('userratelimit', 'aiprovider_openaicompatible'),
        new lang_string('userratelimit_desc', 'aiprovider_openaicompatible'),
        10,
        PARAM_INT,
    ));
    $settings->hide_if(
        'aiprovider_openaicompatible/userratelimit',
        'aiprovider_openaicompatible/enableuserratelimit',
        'eq',
        0,
    );
}
