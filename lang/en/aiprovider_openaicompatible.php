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

/**
 * Strings for component aiprovider_openaicompatible, language 'en'.
 *
 * @package    aiprovider_openaicompatible
 * @copyright  2025   Adorsys GIS <gis-udm@adorsys.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['action:generate_image:endpoint'] = 'API endpoint';
$string['action:generate_image:endpoint_desc'] = 'The full URL of the image generation API, used as entered. Leave empty to use the provider-level API endpoint with /images/generations appended.';
$string['action:generate_image:model'] = 'AI model';
$string['action:generate_image:model_desc'] = 'The model used to generate images, for example gpt-image-1 or dall-e-3.';
$string['action:generate_text:endpoint'] = 'API endpoint';
$string['action:generate_text:endpoint_desc'] = 'The full URL of the chat completions API, used as entered. Leave empty to use the provider-level API endpoint with /chat/completions appended.';
$string['action:generate_text:model'] = 'AI model';
$string['action:generate_text:model_desc'] = 'The model used to generate the text response, for example gpt-4o.';
$string['action:generate_text:systeminstruction'] = 'System instruction';
$string['action:generate_text:systeminstruction_desc'] = 'This instruction is sent to the AI model along with the user\'s prompt. Editing this instruction is not recommended unless absolutely required.';
$string['action:summarise_text:endpoint'] = 'API endpoint';
$string['action:summarise_text:endpoint_desc'] = 'The full URL of the chat completions API, used as entered. Leave empty to use the provider-level API endpoint with /chat/completions appended.';
$string['action:summarise_text:model'] = 'AI model';
$string['action:summarise_text:model_desc'] = 'The model used to summarise the provided text, for example gpt-4o.';
$string['action:summarise_text:systeminstruction'] = 'System instruction';
$string['action:summarise_text:systeminstruction_desc'] = 'The instruction sent to the AI model when summarising text.';
$string['apiendpoint'] = 'API endpoint';
$string['apiendpoint_desc'] = 'The base URL of the OpenAI-compatible API (e.g. https://api.openai.com/v1), without the action path. Each action appends its own path, or can override this with a full URL.';
$string['apikey'] = 'API key';
$string['apikey_desc'] = 'The API key used to authenticate with the OpenAI-compatible API.';
$string['enableglobalratelimit'] = 'Set site-wide rate limit';
$string['enableglobalratelimit_desc'] = 'Limit the number of requests that the provider can receive across the entire site every hour.';
$string['enableuserratelimit'] = 'Set user rate limit';
$string['enableuserratelimit_desc'] = 'Limit the number of requests each user can make to the provider every hour.';
$string['globalratelimit'] = 'Maximum number of site-wide requests';
$string['globalratelimit_desc'] = 'The number of site-wide requests allowed per hour.';
$string['modelextraparams'] = 'Extra parameters';
$string['modelextraparams_desc'] = 'Additional request parameters as JSON, merged into every request. Example:
<pre>
{
    "temperature": 0.5,
    "max_completion_tokens": 100
}
</pre>';
$string['orgid'] = 'Organization ID';
$string['orgid_desc'] = 'Optional organization ID sent with the request.';
$string['pluginname'] = 'OpenAI Compatible API provider';
$string['privacy:metadata'] = 'The OpenAI compatible API provider plugin does not store any personal data.';
$string['privacy:metadata:aiprovider_openaicompatible:externalpurpose'] = 'This information is sent to the external API in order for a response to be generated. The provider\'s account settings may change how data is stored and retained. No user data is explicitly stored in Moodle by this plugin.';
$string['privacy:metadata:aiprovider_openaicompatible:model'] = 'The model used to generate the response.';
$string['privacy:metadata:aiprovider_openaicompatible:numberimages'] = 'When generating images the number of images used in the response.';
$string['privacy:metadata:aiprovider_openaicompatible:prompttext'] = 'The user entered text prompt used to generate the response.';
$string['privacy:metadata:aiprovider_openaicompatible:responseformat'] = 'The format of the response. When generating images.';
$string['userratelimit'] = 'Maximum number of requests per user';
$string['userratelimit_desc'] = 'The number of requests allowed per hour, per user.';
