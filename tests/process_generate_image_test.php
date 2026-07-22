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

use aiprovider_openaicompatible\test\testcase_helper_trait;
use core_ai\aiactions\base;
use core_ai\provider;
use GuzzleHttp\Psr7\Response;

/**
 * Test Generate image provider class for OpenAI-compatible provider methods.
 *
 * @package    aiprovider_openaicompatible
 * @copyright  2025   Adorsys GIS <gis-udm@adorsys.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_openaicompatible\provider
 * @covers     \aiprovider_openaicompatible\process_generate_image
 * @covers     \aiprovider_openaicompatible\abstract_processor
 */
final class process_generate_image_test extends \advanced_testcase {
    use testcase_helper_trait;

    /** @var string A successful response in JSON format. */
    protected string $responsebodyjson;

    /** @var provider The provider that will process the action. */
    protected provider $provider;

    /** @var base The action to process. */
    protected base $action;

    /**
     * Set up the test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        // Load a response body from a file.
        $this->responsebodyjson = file_get_contents(
            self::get_fixture_path('aiprovider_openaicompatible', 'image_request_success.json'),
        );
        // Configure a valid endpoint and model for the action.
        $this->set_provider_config(['apiendpoint' => 'https://api.example.com/v1']);
        $this->set_action_config('generate_image', ['model' => 'gpt-image-1']);
        $this->create_provider_object();
        $this->create_action();
    }

    /**
     * Create the provider object.
     */
    private function create_provider_object(): void {
        $this->provider = $this->create_provider();
    }

    /**
     * Create the action object.
     * @param int $userid The user id to use in the action.
     */
    private function create_action(int $userid = 1): void {
        $this->action = new \core_ai\aiactions\generate_image(
            contextid: 1,
            userid: $userid,
            prompttext: 'This is a test prompt',
            quality: 'hd',
            aspectratio: 'square',
            numimages: 1,
            style: 'vivid',
        );
    }

    /**
     * Build a fake successful API response body containing a real image payload.
     *
     * @return string
     */
    private function get_image_response_body(): string {
        return json_encode([
            'created' => 1719140500,
            'data' => [
                (object) [
                    'revised_prompt' => 'An image that represents the concept of a \'test\'.',
                    'b64_json' => base64_encode(
                        file_get_contents(self::get_fixture_path('aiprovider_openaicompatible', 'test.jpg')),
                    ),
                ],
            ],
        ]);
    }

    /**
     * Test calculate_size.
     */
    public function test_calculate_size(): void {
        $processor = new process_generate_image($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'calculate_size');

        // DALL-E 3 sizes.
        $this->set_action_config('generate_image', ['model' => 'dall-e-3']);
        $this->create_provider_object();
        $processor = new process_generate_image($this->provider, $this->action);
        $this->assertEquals('1024x1024', $method->invoke($processor, 'square'));
        $this->assertEquals('1024x1792', $method->invoke($processor, 'portrait'));
        $this->assertEquals('1792x1024', $method->invoke($processor, 'landscape'));

        // GPT image model sizes.
        $this->set_action_config('generate_image', ['model' => 'gpt-image-1']);
        $this->create_provider_object();
        $processor = new process_generate_image($this->provider, $this->action);
        $this->assertEquals('1024x1024', $method->invoke($processor, 'square'));
        $this->assertEquals('1024x1536', $method->invoke($processor, 'portrait'));
        $this->assertEquals('1536x1024', $method->invoke($processor, 'landscape'));
    }

    /**
     * Test calculate_quality.
     */
    public function test_calculate_quality(): void {
        $processor = new process_generate_image($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'calculate_quality');

        // DALL-E models pass quality values through unchanged.
        $this->set_action_config('generate_image', ['model' => 'dall-e-3']);
        $this->create_provider_object();
        $processor = new process_generate_image($this->provider, $this->action);
        $this->assertEquals('standard', $method->invoke($processor, 'standard'));
        $this->assertEquals('hd', $method->invoke($processor, 'hd'));

        // GPT image models map quality values.
        $this->set_action_config('generate_image', ['model' => 'gpt-image-1']);
        $this->create_provider_object();
        $processor = new process_generate_image($this->provider, $this->action);
        $this->assertEquals('medium', $method->invoke($processor, 'standard'));
        $this->assertEquals('high', $method->invoke($processor, 'hd'));
    }

    /**
     * Test create_request_object for the dall-e-3 model.
     */
    public function test_create_request_object_dalle(): void {
        $this->set_action_config('generate_image', ['model' => 'dall-e-3']);
        $this->create_provider_object();

        $processor = new process_generate_image($this->provider, $this->action);

        // We're working with a private method here, so we need to use reflection.
        $method = new \ReflectionMethod($processor, 'create_request_object');
        $request = $method->invoke($processor, 1);

        $requestdata = (object) json_decode($request->getBody()->getContents());

        $this->assertEquals('This is a test prompt', $requestdata->prompt);
        $this->assertEquals('dall-e-3', $requestdata->model);
        $this->assertEquals('1', $requestdata->n);
        $this->assertEquals('hd', $requestdata->quality);
        $this->assertEquals('b64_json', $requestdata->response_format);
        $this->assertEquals('1024x1024', $requestdata->size);
        $this->assertFalse(property_exists($requestdata, 'output_format'));
    }

    /**
     * Test create_request_object for the gpt-image-1 model.
     */
    public function test_create_request_object_gptimage(): void {
        $processor = new process_generate_image($this->provider, $this->action);

        $method = new \ReflectionMethod($processor, 'create_request_object');
        $request = $method->invoke($processor, 1);

        $requestdata = (object) json_decode($request->getBody()->getContents());

        $this->assertEquals('This is a test prompt', $requestdata->prompt);
        $this->assertEquals('gpt-image-1', $requestdata->model);
        $this->assertEquals('1', $requestdata->n);
        // The 'hd' quality maps to 'high' for gpt-image-1.
        $this->assertEquals('high', $requestdata->quality);
        $this->assertEquals('1024x1024', $requestdata->size);
        $this->assertEquals('png', $requestdata->output_format);
        $this->assertFalse(property_exists($requestdata, 'response_format'));
    }

    /**
     * Test create_request_object with extra model params.
     */
    public function test_create_request_object_with_model_settings(): void {
        $this->set_action_config('generate_image', [
            'model' => 'gpt-image-1',
            'modelextraparams' => '{"background": "transparent"}',
        ]);
        $this->create_provider_object();

        $processor = new process_generate_image($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'create_request_object');
        $request = $method->invoke($processor, 1);

        $requestdata = (object) json_decode($request->getBody()->getContents());
        $this->assertEquals('transparent', $requestdata->background);
    }

    /**
     * Test the API error response handler method.
     */
    public function test_handle_api_error(): void {
        $responses = [
            500 => new Response(500, ['Content-Type' => 'application/json']),
            503 => new Response(503, ['Content-Type' => 'application/json']),
            401 => new Response(
                401,
                ['Content-Type' => 'application/json'],
                '{"error": {"message": "Invalid Authentication"}}'
            ),
            404 => new Response(
                404,
                ['Content-Type' => 'application/json'],
                '{"error": {"message": "You must be a member of an organization to use the API"}}'
            ),
            429 => new Response(
                429,
                ['Content-Type' => 'application/json'],
                '{"error": {"message": "Rate limit reached for requests"}}'
            ),
        ];

        $processor = new process_generate_image($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'handle_api_error');

        foreach ($responses as $status => $response) {
            $result = $method->invoke($processor, $response);
            $this->assertFalse($result['success']);
            $this->assertEquals($status, $result['errorcode']);
            if ($status == 500) {
                $this->assertEquals('Internal Server Error', $result['errormessage']);
            } else if ($status == 503) {
                $this->assertEquals('Service Unavailable', $result['errormessage']);
            } else {
                $this->assertStringContainsString($response->getBody()->getContents(), $result['errormessage']);
            }
        }
    }

    /**
     * Test the API success response handler method.
     */
    public function test_handle_api_success(): void {
        $response = new Response(
            200,
            ['Content-Type' => 'application/json'],
            $this->responsebodyjson,
        );

        // We're testing a private method, so we need to setup reflector magic.
        $processor = new process_generate_image($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'handle_api_success');

        $result = $method->invoke($processor, $response);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('An image that represents the concept of a \'test\'.', $result['revisedprompt']);
        $this->assertNotEmpty($result['b64json']);
    }

    /**
     * Test query_ai_api for a successful call.
     */
    public function test_query_ai_api_success(): void {
        // Mock the http client to return a successful response.
        ['mock' => $mock] = $this->get_mocked_http_client();

        $mock->append(new Response(
            200,
            ['Content-Type' => 'application/json'],
            $this->get_image_response_body(),
        ));

        $this->setAdminUser();

        $processor = new process_generate_image($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'query_ai_api');
        $result = $method->invoke($processor);

        $this->assertStringContainsString('An image that represents the concept of a \'test\'.', $result['revisedprompt']);
        $this->assertInstanceOf(\stored_file::class, $result['draftfile']);
    }

    /**
     * Test prepare_response success.
     */
    public function test_prepare_response_success(): void {
        $processor = new process_generate_image($this->provider, $this->action);

        // We're working with a private method here, so we need to use reflection.
        $method = new \ReflectionMethod($processor, 'prepare_response');

        $response = [
            'success' => true,
            'revisedprompt' => 'An image that represents the concept of a \'test\'.',
            'imageurl' => 'oaidalleapiprodscus.blob.core.windows.net',
        ];

        $result = $method->invoke($processor, $response);

        $this->assertInstanceOf(\core_ai\aiactions\responses\response_base::class, $result);
        $this->assertTrue($result->get_success());
        $this->assertEquals('generate_image', $result->get_actionname());
        $this->assertEquals($response['success'], $result->get_success());
        $this->assertEquals($response['revisedprompt'], $result->get_response_data()['revisedprompt']);
    }

    /**
     * Test prepare_response error.
     */
    public function test_prepare_response_error(): void {
        $processor = new process_generate_image($this->provider, $this->action);

        // We're working with a private method here, so we need to use reflection.
        $method = new \ReflectionMethod($processor, 'prepare_response');

        $response = [
            'success' => false,
            'errorcode' => 500,
            'errormessage' => 'Internal server error.',
        ];

        $result = $method->invoke($processor, $response);

        $this->assertInstanceOf(\core_ai\aiactions\responses\response_base::class, $result);
        $this->assertFalse($result->get_success());
        $this->assertEquals('generate_image', $result->get_actionname());
        $this->assertEquals($response['errorcode'], $result->get_errorcode());
        $this->assertEquals($response['errormessage'], $result->get_errormessage());
    }

    /**
     * Test create_file_from_response.
     */
    public function test_create_file_from_response(): void {
        // Log in user.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $processor = new process_generate_image($this->provider, $this->action);
        // We're working with a private method here, so we need to use reflection.
        $method = new \ReflectionMethod($processor, 'create_file_from_response');

        $userid = $user->id;
        $response = [
            'b64json' => base64_encode(file_get_contents(self::get_fixture_path('aiprovider_openaicompatible', 'test.jpg'))),
            'output_format' => 'jpg',
        ];
        $fileobj = $method->invoke($processor, $userid, $response);

        $this->assertMatchesRegularExpression('/^[a-f0-9]{16}\.jpg$/', $fileobj->get_filename());
    }

    /**
     * Test process.
     */
    public function test_process(): void {
        // Log in user.
        $this->setUser($this->getDataGenerator()->create_user());

        // Mock the http client to return a successful response.
        ['mock' => $mock] = $this->get_mocked_http_client();

        $mock->append(new Response(
            200,
            ['Content-Type' => 'application/json'],
            $this->get_image_response_body(),
        ));

        $processor = new process_generate_image($this->provider, $this->action);
        $result = $processor->process();

        $this->assertInstanceOf(\core_ai\aiactions\responses\response_base::class, $result);
        $this->assertTrue($result->get_success());
        $this->assertEquals('generate_image', $result->get_actionname());
        $this->assertEquals(
            'An image that represents the concept of a \'test\'.',
            $result->get_response_data()['revisedprompt'],
        );
        $this->assertInstanceOf(\stored_file::class, $result->get_response_data()['draftfile']);
    }

    /**
     * Test process method with error.
     */
    public function test_process_error(): void {
        // Log in user.
        $this->setUser($this->getDataGenerator()->create_user());

        // Mock the http client to return an error response.
        ['mock' => $mock] = $this->get_mocked_http_client();

        $mock->append(new Response(
            401,
            ['Content-Type' => 'application/json'],
            json_encode(['error' => ['message' => 'Invalid Authentication']]),
        ));

        $processor = new process_generate_image($this->provider, $this->action);
        $result = $processor->process();

        $this->assertInstanceOf(\core_ai\aiactions\responses\response_base::class, $result);
        $this->assertFalse($result->get_success());
        $this->assertEquals('generate_image', $result->get_actionname());
        $this->assertEquals(401, $result->get_errorcode());
        $this->assertEquals('Invalid Authentication', $result->get_errormessage());
    }

    /**
     * Test process method with user rate limiter.
     */
    public function test_process_with_user_rate_limiter(): void {
        // Create users.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        // Log in user1.
        $this->setUser($user1);
        // Mock clock.
        $clock = $this->mock_clock_with_frozen();

        // Set the user rate limiter.
        set_config('enableuserratelimit', 1, 'aiprovider_openaicompatible');
        set_config('userratelimit', 1, 'aiprovider_openaicompatible');

        // Mock the http client to return a successful response.
        ['mock' => $mock] = $this->get_mocked_http_client();

        // Case 1: User rate limit has not been reached.
        $this->create_provider_object();
        $this->create_action($user1->id);
        $mock->append(new Response(200, ['Content-Type' => 'application/json'], $this->get_image_response_body()));
        $processor = new process_generate_image($this->provider, $this->action);
        $result = $processor->process();
        $this->assertTrue($result->get_success());

        // Case 2: User rate limit has been reached.
        $clock->bump(HOURSECS - 10);
        $mock->append(new Response(200, ['Content-Type' => 'application/json'], $this->get_image_response_body()));
        $this->create_provider_object();
        $this->create_action($user1->id);
        $processor = new process_generate_image($this->provider, $this->action);
        $result = $processor->process();
        $this->assertEquals(429, $result->get_errorcode());
        $this->assertEquals('User rate limit exceeded', $result->get_errormessage());
        $this->assertFalse($result->get_success());

        // Case 3: User rate limit has not been reached for a different user.
        $this->setUser($user2);
        $this->create_provider_object();
        $this->create_action($user2->id);
        $mock->append(new Response(200, ['Content-Type' => 'application/json'], $this->get_image_response_body()));
        $processor = new process_generate_image($this->provider, $this->action);
        $result = $processor->process();
        $this->assertTrue($result->get_success());

        // Case 4: Time window has passed, user rate limit should be reset.
        $clock->bump(11);
        $this->setUser($user1);
        $mock->append(new Response(200, ['Content-Type' => 'application/json'], $this->get_image_response_body()));
        $this->create_provider_object();
        $this->create_action($user1->id);
        $processor = new process_generate_image($this->provider, $this->action);
        $result = $processor->process();
        $this->assertTrue($result->get_success());
    }

    /**
     * Test process method with global rate limiter.
     */
    public function test_process_with_global_rate_limiter(): void {
        // Create users.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        // Log in user1.
        $this->setUser($user1);
        // Mock clock.
        $clock = $this->mock_clock_with_frozen();

        // Set the global rate limiter.
        set_config('enableglobalratelimit', 1, 'aiprovider_openaicompatible');
        set_config('globalratelimit', 1, 'aiprovider_openaicompatible');

        // Mock the http client to return a successful response.
        ['mock' => $mock] = $this->get_mocked_http_client();

        // Case 1: Global rate limit has not been reached.
        $this->create_provider_object();
        $this->create_action($user1->id);
        $mock->append(new Response(200, ['Content-Type' => 'application/json'], $this->get_image_response_body()));
        $processor = new process_generate_image($this->provider, $this->action);
        $result = $processor->process();
        $this->assertTrue($result->get_success());

        // Case 2: Global rate limit has been reached.
        $clock->bump(HOURSECS - 10);
        $mock->append(new Response(200, ['Content-Type' => 'application/json'], $this->get_image_response_body()));
        $this->create_provider_object();
        $this->create_action($user1->id);
        $processor = new process_generate_image($this->provider, $this->action);
        $result = $processor->process();
        $this->assertEquals(429, $result->get_errorcode());
        $this->assertEquals('Global rate limit exceeded', $result->get_errormessage());
        $this->assertFalse($result->get_success());

        // Case 3: Global rate limit has been reached for a different user too.
        $this->setUser($user2);
        $this->create_provider_object();
        $this->create_action($user2->id);
        $mock->append(new Response(200, ['Content-Type' => 'application/json'], $this->get_image_response_body()));
        $processor = new process_generate_image($this->provider, $this->action);
        $result = $processor->process();
        $this->assertFalse($result->get_success());

        // Case 4: Time window has passed, global rate limit should be reset.
        $clock->bump(11);
        $this->setUser($user1);
        $mock->append(new Response(200, ['Content-Type' => 'application/json'], $this->get_image_response_body()));
        $this->create_provider_object();
        $this->create_action($user1->id);
        $processor = new process_generate_image($this->provider, $this->action);
        $result = $processor->process();
        $this->assertTrue($result->get_success());
    }
}
