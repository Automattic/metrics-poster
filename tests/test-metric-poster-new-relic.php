<?php

/**
 * Class MetricPosterTest
 * 
 * @package MetricPoster
 */

declare(strict_types=1);

namespace MetricPoster;

use MetricPoster\NewRelicGQL;
use MetricPoster\AppModel;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

// use brain monkey mock function.
use Brain\Monkey;

/**
 * MetricPosterTest
 */


class MetricPosterTestNewRelic extends \WP_UnitTestCase
{

    private $app_info;
    private $nr_metrics;

    public function setUp(): void
    {
        parent::setUp();

        // mock function vip_get_env_var with brain monkey.
        Monkey\Functions\when('vip_get_env_var')->justReturn('123456');

        // mock $body = $response->getBody()->getContents() response.
        $mock_body = '{"data":{"actor":{"account":{"id":123456},"applications":{"summary":{"nrql":{"results":[{"count":0,"facet":{"error_type":"error"},"rate":0,"sum":0,"timeslice":1609459200},{"count":0,"facet":{"error_type":"warning"},"rate":0,"sum":0,"timeslice":1609459200}]},"since":1609459200,"timeseries":[{"results":[{"count":0,"facet":{"error_type":"error"},"rate":0,"sum":0,"timeslice":1609459200},{"count":0,"facet":{"error_type":"warning"},"rate":0,"sum":0,"timeslice":1609459200}]}]}}}},"errors":[]}';

        // Create a mock and queue two responses.
        $mock = new MockHandler([
            new Response(200, ['X-Foo' => 'Bar'], $mock_body)
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $this->app_info = new AppModel('test_app', 'test_app_id', 'test_nr_id', 'test_nr_browser_guid', 'test_nr_app_guid', 'test_jp_blogid', 'test_app_template_file');
        $this->nr_metrics = new NewRelicGQL($this->app_info, 1, 2023, 123456, 'error_count,warning_count', true);

        $this->nr_metrics->set_client($client);
    }

    public function test_get_results()
    {
        $this->nr_metrics->get_results();
        $this->assertIsArray($this->nr_metrics->get_results());
    }
}
