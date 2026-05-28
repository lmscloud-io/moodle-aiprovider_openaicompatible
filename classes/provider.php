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

namespace aiprovider_openaicompatible;

use core_ai\aiactions;
use core_ai\rate_limiter;
use Psr\Http\Message\RequestInterface;

/**
 * Class provider.
 *
 * @package    aiprovider_openaicompatible
 * @copyright  2025   Adorsys GIS <gis-udm@adorsys.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider extends \core_ai\provider {
    /** @var string The API key. */
    private string $apikey;

    /** @var string The organisation ID that goes with the key. */
    private string $orgid;

    /** @var string The API endpoint (base URL). */
    private string $apiendpoint;

    /** @var string The model name. */
    private string $model;

    /** @var bool Is global rate limiting for the API enabled. */
    private bool $enableglobalratelimit;

    /** @var int The global rate limit. */
    private int $globalratelimit;

    /** @var bool Is user rate limiting for the API enabled. */
    private bool $enableuserratelimit;

    /** @var int The user rate limit. */
    private int $userratelimit;

    /**
     * Class constructor.
     */
    public function __construct() {
        $this->apikey = get_config('aiprovider_openaicompatible', 'apikey') ?: '';
        $this->orgid = get_config('aiprovider_openaicompatible', 'orgid') ?: '';
        $this->apiendpoint = get_config('aiprovider_openaicompatible', 'apiendpoint') ?: '';
        $this->model = get_config('aiprovider_openaicompatible', 'model') ?: '';
        $this->enableglobalratelimit = (bool) get_config('aiprovider_openaicompatible', 'enableglobalratelimit');
        $this->globalratelimit = (int) get_config('aiprovider_openaicompatible', 'globalratelimit');
        $this->enableuserratelimit = (bool) get_config('aiprovider_openaicompatible', 'enableuserratelimit');
        $this->userratelimit = (int) get_config('aiprovider_openaicompatible', 'userratelimit');
    }

    /**
     * Get the configured API endpoint.
     *
     * @return string
     */
    public function get_api_endpoint(): string {
        return $this->apiendpoint;
    }

    /**
     * Get the configured model name.
     *
     * @return string
     */
    public function get_api_model(): string {
        return $this->model;
    }

    /**
     * Get the list of actions that this provider supports.
     *
     * @return array An array of action class names.
     */
    public function get_action_list(): array {
        return [
            \core_ai\aiactions\generate_text::class,
            \core_ai\aiactions\generate_image::class,
            \core_ai\aiactions\summarise_text::class,
        ];
    }

    /**
     * Generate a user id.
     *
     * @param string $userid The user id.
     * @return string The generated user id.
     */
    public function generate_userid(string $userid): string {
        global $CFG;
        return hash('sha256', $CFG->siteidentifier . $userid);
    }

    /**
     * Add authentication headers to the request.
     *
     * @param RequestInterface $request
     * @return RequestInterface
     */
    public function add_authentication_headers(RequestInterface $request): RequestInterface {
        $request = $request->withAddedHeader('Authorization', "Bearer {$this->apikey}");
        if (!empty($this->orgid)) {
            $request = $request->withAddedHeader('OpenAI-Organization', $this->orgid);
        }
        return $request;
    }

    #[\Override]
    public function is_request_allowed(aiactions\base $action): array|bool {
        $ratelimiter = \core\di::get(rate_limiter::class);
        $component = \core\component::get_component_from_classname(get_class($this));

        if ($this->enableuserratelimit) {
            if (!$ratelimiter->check_user_rate_limit(
                component: $component,
                ratelimit: $this->userratelimit,
                userid: $action->get_configuration('userid')
            )) {
                return [
                    'success' => false,
                    'errorcode' => 429,
                    'errormessage' => 'User rate limit exceeded',
                ];
            }
        }

        if ($this->enableglobalratelimit) {
            if (!$ratelimiter->check_global_rate_limit(
                component: $component,
                ratelimit: $this->globalratelimit
            )) {
                return [
                    'success' => false,
                    'errorcode' => 429,
                    'errormessage' => 'Global rate limit exceeded',
                ];
            }
        }

        return true;
    }

    /**
     * Get any action settings for this provider.
     *
     * @param string $action The action class name.
     * @param \admin_root $ADMIN The admin root object.
     * @param string $section The section name.
     * @param bool $hassiteconfig Whether the current user has moodle/site:config capability.
     * @return array An array of settings.
     */
    public function get_action_settings(
        string $action,
        \admin_root $ADMIN,
        string $section,
        bool $hassiteconfig
    ): array {
        $actionname = substr($action, (strrpos($action, '\\') + 1));
        $settings = [];

        if ($actionname === 'generate_text' || $actionname === 'summarise_text') {
            $settings[] = new \admin_setting_configtext(
                "aiprovider_openaicompatible/action_{$actionname}_model",
                new \lang_string("action:{$actionname}:model", 'aiprovider_openaicompatible'),
                new \lang_string("action:{$actionname}:model_desc", 'aiprovider_openaicompatible'),
                'gpt-4o',
                PARAM_TEXT,
            );
            $settings[] = new \admin_setting_configtext(
                "aiprovider_openaicompatible/action_{$actionname}_endpoint",
                new \lang_string("action:{$actionname}:endpoint", 'aiprovider_openaicompatible'),
                new \lang_string("action:{$actionname}:endpoint_desc", 'aiprovider_openaicompatible'),
                'https://api.openai.com/v1',
                PARAM_URL,
            );
            $settings[] = new \admin_setting_configtextarea(
                "aiprovider_openaicompatible/action_{$actionname}_systeminstruction",
                new \lang_string("action:{$actionname}:systeminstruction", 'aiprovider_openaicompatible'),
                new \lang_string("action:{$actionname}:systeminstruction_desc", 'aiprovider_openaicompatible'),
                $action::get_system_instruction(),
                PARAM_TEXT,
            );
            $settings[] = new \admin_setting_configtextarea(
                "aiprovider_openaicompatible/action_{$actionname}_modelextraparams",
                new \lang_string('modelextraparams', 'aiprovider_openaicompatible'),
                new \lang_string('modelextraparams_desc', 'aiprovider_openaicompatible'),
                '',
                PARAM_RAW,
            );
        } else if ($actionname === 'generate_image') {
            $settings[] = new \admin_setting_configtext(
                "aiprovider_openaicompatible/action_{$actionname}_model",
                new \lang_string("action:{$actionname}:model", 'aiprovider_openaicompatible'),
                new \lang_string("action:{$actionname}:model_desc", 'aiprovider_openaicompatible'),
                'dall-e-3',
                PARAM_TEXT,
            );
            $settings[] = new \admin_setting_configtext(
                "aiprovider_openaicompatible/action_{$actionname}_endpoint",
                new \lang_string("action:{$actionname}:endpoint", 'aiprovider_openaicompatible'),
                new \lang_string("action:{$actionname}:endpoint_desc", 'aiprovider_openaicompatible'),
                'https://api.openai.com/v1',
                PARAM_URL,
            );
            $settings[] = new \admin_setting_configtextarea(
                "aiprovider_openaicompatible/action_{$actionname}_modelextraparams",
                new \lang_string('modelextraparams', 'aiprovider_openaicompatible'),
                new \lang_string('modelextraparams_desc', 'aiprovider_openaicompatible'),
                '',
                PARAM_RAW,
            );
        }

        return $settings;
    }

    /**
     * Check this provider has the minimal configuration to work.
     *
     * @return bool
     */
    public function is_provider_configured(): bool {
        return !empty($this->apikey);
    }
}
