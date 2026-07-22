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
    /** @var int Number of images to generate. */
    private int $numberimages = 1;

    #[\Override]
    protected function query_ai_api(): array {
        $response = parent::query_ai_api();

        if ($response['success']) {
            $fileobj = $this->create_file_from_response(
                $this->action->get_configuration('userid'),
                $response,
            );
            $response['draftfile'] = $fileobj;
        }

        return $response;
    }

    /**
     * Convert aspect ratio to an image size compatible with the configured model.
     *
     * dall-e-3 uses the 1792 wide/tall sizes; gpt-image-1 and anything else uses the 1536 sizes.
     *
     * @param string $ratio
     * @return string
     */
    private function calculate_size(string $ratio): string {
        if ($this->get_model() === 'dall-e-3') {
            return match ($ratio) {
                'square' => '1024x1024',
                'landscape' => '1792x1024',
                'portrait' => '1024x1792',
                default => throw new \coding_exception('Invalid aspect ratio: ' . $ratio),
            };
        }

        return match ($ratio) {
            'square' => '1024x1024',
            'landscape' => '1536x1024',
            'portrait' => '1024x1536',
            default => throw new \coding_exception('Invalid aspect ratio: ' . $ratio),
        };
    }

    /**
     * Convert the quality setting to the value expected by the configured model.
     *
     * dall-e-3 takes Moodle's own values; gpt-image-1 and anything else expects medium/high.
     *
     * @param string $quality
     * @return string
     */
    private function calculate_quality(string $quality): string {
        if ($this->get_model() === 'dall-e-3') {
            return match ($quality) {
                'standard' => 'standard',
                'hd' => 'hd',
                default => 'standard',
            };
        }

        return match ($quality) {
            'standard' => 'medium',
            'hd' => 'high',
            default => throw new \coding_exception('Invalid quality: ' . $quality),
        };
    }

    #[\Override]
    protected function create_request_object(string $userid): RequestInterface {
        $model = $this->get_model();

        $requestobj = new \stdClass();
        $requestobj->model = $model;
        $requestobj->user = $userid;
        $requestobj->prompt = $this->action->get_configuration('prompttext');
        $requestobj->n = $this->numberimages;
        $requestobj->quality = $this->calculate_quality($this->action->get_configuration('quality'));
        $requestobj->size = $this->calculate_size($this->action->get_configuration('aspectratio'));

        // Model specific response parameters. gpt-image-1 rejects response_format and always
        // returns base64; dall-e-3 has no output_format and must be asked for base64 explicitly.
        if ($model === 'gpt-image-1') {
            $requestobj->output_format = 'png';
        } else if ($model === 'dall-e-3') {
            $requestobj->response_format = 'b64_json';
        }

        foreach ($this->get_extra_params() as $key => $value) {
            $requestobj->{$key} = $value;
        }

        return new Request(
            method: 'POST',
            uri: '',
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
            'b64json' => $bodyobj->data[0]->b64_json ?? null,
            'sourceurl' => $bodyobj->data[0]->url ?? null,
            'output_format' => $bodyobj->output_format ?? 'png',
            'revisedprompt' => $bodyobj->data[0]->revised_prompt ?? '',
            'model' => $this->get_model(),
            'errormessage' => '',
        ];
    }

    /**
     * Convert image data from the API response into a file in the user's draft area.
     *
     * Handles both inline base64 (b64_json) and remote URL responses.
     *
     * @param int $userid The user id.
     * @param array $response Response from the AI provider.
     * @return \stored_file
     */
    private function create_file_from_response(int $userid, array $response): \stored_file {
        global $CFG;

        require_once("{$CFG->libdir}/filelib.php");

        if (!empty($response['b64json'])) {
            // Preferred path: inline base64, no secondary HTTP call needed.
            $b64json = $response['b64json'];
            $outputformat = $response['output_format'] ?? 'png';
            $filename = substr(hash('sha512', $b64json), 0, 16) . '.' . $outputformat;
            $tempdst = make_request_directory() . DIRECTORY_SEPARATOR . $filename;
            file_put_contents($tempdst, base64_decode($b64json));
        } else if (!empty($response['sourceurl'])) {
            // Fallback: download from the remote URL.
            $url = $response['sourceurl'];
            $filename = basename(parse_url($url, PHP_URL_PATH));
            $tempdst = make_request_directory() . DIRECTORY_SEPARATOR . $filename;
            $client = \core\di::get(http_client::class);
            $client->get($url, [
                'sink' => $tempdst,
                'timeout' => $CFG->repositorygetfiletimeout,
            ]);
        } else {
            throw new \moodle_exception('No image data returned from the AI API.');
        }

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
