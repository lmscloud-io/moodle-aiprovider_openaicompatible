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

use core\http_client;
use core_ai\process_base;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Base class for processors.
 *
 * @package    aiprovider_openaicompatible
 * @copyright  2025   Adorsys GIS <gis-udm@adorsys.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class abstract_processor extends process_base {
    /**
     * Get the short action name (e.g. generate_text).
     *
     * @return string
     */
    protected function get_action_name(): string {
        $class = get_class($this->action);
        return substr($class, strrpos($class, '\\') + 1);
    }

    /**
     * Read a per-action setting from plugin config.
     *
     * @param string $key
     * @return string
     */
    private function action_config(string $key): string {
        $value = get_config('aiprovider_openaicompatible', "action_{$this->get_action_name()}_{$key}");
        return $value === false ? '' : (string) $value;
    }

    /**
     * Get the full endpoint URI to send this action's request to.
     *
     * The per-action setting is the full URL and is used verbatim. The provider-level setting is
     * a base URL, to which the action's path is appended. Matches the 5.1 branch, so a site can
     * be configured the same way on both.
     *
     * @return UriInterface
     */
    protected function get_endpoint(): UriInterface {
        $endpoint = $this->action_config('endpoint');
        if ($endpoint === '') {
            $endpoint = rtrim($this->provider->get_api_endpoint(), '/');
            if ($endpoint !== '') {
                $endpoint .= $this->action instanceof \core_ai\aiactions\generate_image
                    ? '/images/generations'
                    : '/chat/completions';
            }
        }
        return new Uri($endpoint);
    }

    /**
     * Get the name of the model to use. Configured per action; there is no provider-level default.
     *
     * @return string
     */
    protected function get_model(): string {
        return $this->action_config('model');
    }

    /**
     * Get the system instructions.
     *
     * @return string
     */
    protected function get_system_instruction(): string {
        $configured = $this->action_config('systeminstruction');
        return $configured !== '' ? $configured : $this->action::get_system_instruction();
    }

    /**
     * Get the decoded extra request parameters configured for this action.
     *
     * @return array
     */
    protected function get_extra_params(): array {
        $raw = $this->action_config('modelextraparams');
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Create the request object to send to the API.
     *
     * @param string $userid The user id.
     * @return RequestInterface
     */
    abstract protected function create_request_object(string $userid): RequestInterface;

    /**
     * Handle a successful response from the external AI api.
     *
     * @param ResponseInterface $response
     * @return array
     */
    abstract protected function handle_api_success(ResponseInterface $response): array;

    #[\Override]
    protected function query_ai_api(): array {
        $request = $this->create_request_object(
            userid: $this->provider->generate_userid($this->action->get_configuration('userid')),
        );
        $request = $this->provider->add_authentication_headers($request);

        $client = \core\di::get(http_client::class);
        try {
            $response = $client->send($request, [
                'base_uri' => $this->get_endpoint(),
                RequestOptions::HTTP_ERRORS => false,
            ]);
        } catch (RequestException $e) {
            return [
                'success' => false,
                'errorcode' => $e->getCode() ?: 500,
                'errormessage' => $e->getMessage(),
            ];
        }

        $status = $response->getStatusCode();
        if ($status === 200) {
            return $this->handle_api_success($response);
        }
        return $this->handle_api_error($response);
    }

    /**
     * Handle an error from the external AI api.
     *
     * @param ResponseInterface $response
     * @return array
     */
    protected function handle_api_error(ResponseInterface $response): array {
        $status = $response->getStatusCode();
        $errormessage = $response->getReasonPhrase();

        if ($status < 500 || $status >= 600) {
            $bodyobj = json_decode($response->getBody()->getContents());
            if ($bodyobj && isset($bodyobj->error->message)) {
                $errormessage = $bodyobj->error->message;
            }
        }

        return [
            'success' => false,
            'errorcode' => $status,
            'errormessage' => $errormessage,
        ];
    }
}
