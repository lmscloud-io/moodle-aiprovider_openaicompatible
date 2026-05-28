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
use core_ai\ai_image;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class process image generation.
 *
 * @package    aiprovider_openaicompatible
 * @copyright  2025   Adorsys GIS <gis-udm@adorsys.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_generate_image extends abstract_processor {
    /** @var int Number of images to generate. dall-e-3 only supports 1. */
    private int $numberimages = 1;

    /** @var string Response format: url or b64_json. */
    private string $responseformat = 'url';

    #[\Override]
    protected function query_ai_api(): array {
        $response = parent::query_ai_api();

        if ($response['success']) {
            $fileobj = $this->url_to_file(
                $this->action->get_configuration('userid'),
                $response['sourceurl'],
            );
            $response['draftfile'] = $fileobj;
        }

        return $response;
    }

    /**
     * Convert aspect ratio to OpenAI image size.
     *
     * @param string $ratio
     * @return string
     */
    private function calculate_size(string $ratio): string {
        return match ($ratio) {
            'square' => '1024x1024',
            'landscape' => '1792x1024',
            'portrait' => '1024x1792',
            default => throw new \coding_exception('Invalid aspect ratio: ' . $ratio),
        };
    }

    #[\Override]
    protected function create_request_object(string $userid): RequestInterface {
        $requestobj = new \stdClass();
        $requestobj->model = $this->get_model();
        $requestobj->user = $userid;
        $requestobj->prompt = $this->action->get_configuration('prompttext');
        $requestobj->n = $this->numberimages;
        $requestobj->quality = $this->action->get_configuration('quality');
        $requestobj->response_format = $this->responseformat;
        $requestobj->size = $this->calculate_size($this->action->get_configuration('aspectratio'));
        $requestobj->style = $this->action->get_configuration('style');

        foreach ($this->get_extra_params() as $key => $value) {
            $requestobj->{$key} = $value;
        }

        return new Request(
            method: 'POST',
            uri: 'images/generations',
            body: json_encode($requestobj),
            headers: [
                'Content-Type' => 'application/json',
            ],
        );
    }

    #[\Override]
    protected function handle_api_success(ResponseInterface $response): array {
        $bodyobj = json_decode($response->getBody()->getContents());

        return [
            'success' => true,
            'sourceurl' => $bodyobj->data[0]->url,
            'revisedprompt' => $bodyobj->data[0]->revised_prompt ?? '',
            'model' => $this->get_model(),
            'errormessage' => '',
        ];
    }

    /**
     * Convert the URL for the image to a file in the user's draft area.
     *
     * @param int $userid The user id.
     * @param string $url The URL to the image.
     * @return \stored_file
     */
    private function url_to_file(int $userid, string $url): \stored_file {
        global $CFG;

        require_once("{$CFG->libdir}/filelib.php");

        $parsedurl = parse_url($url, PHP_URL_PATH);
        $filename = basename($parsedurl);

        $client = \core\di::get(http_client::class);

        $tempdst = make_request_directory() . DIRECTORY_SEPARATOR . $filename;
        $client->get($url, [
            'sink' => $tempdst,
            'timeout' => $CFG->repositorygetfiletimeout,
        ]);

        $image = new ai_image($tempdst);
        $image->add_watermark()->save();

        $fileinfo = new \stdClass();
        $fileinfo->contextid = \context_user::instance($userid)->id;
        $fileinfo->filearea = 'draft';
        $fileinfo->component = 'user';
        $fileinfo->itemid = file_get_unused_draft_itemid();
        $fileinfo->filepath = '/';
        $fileinfo->filename = $filename;

        $fs = get_file_storage();
        return $fs->create_file_from_string($fileinfo, file_get_contents($tempdst));
    }
}
