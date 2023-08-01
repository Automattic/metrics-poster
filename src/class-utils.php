<?php

declare(strict_types=1);

namespace MetricPoster;

use GuzzleHttp\Client;

class Utils
{
	public $week;
	public $nr_metrics;

	public function __construct(int $week, $nr_metrics)
	{
		print "In Utils constructor\n";
		$this->week = $week;
		$this->nr_metrics = $nr_metrics;
	}

	public function zapier_webhook_trigger(): void {
		$webhook_url = $_ENV['ZAPIER_WEBHOOK_URL'] ?? null;

		if (!$webhook_url) {
			exit('Missing Zapier webhook URL');
		}


		$webhook_data = array(
			'app' => $_ENV['APP_ID'] ?? 'test app',
			'week' => $this->week,
		);

		if( !isset($this->nr_metrics) ) {
			exit('No metrics found');
		}else {
			if( is_array($this->nr_metrics) ) {
				$webhook_data['data'] = json_encode($this->nr_metrics);
			}
		}

		// use GuzzleHttp to send the webhook.
		$client = new Client();
		$response = $client->request('POST', $webhook_url, [
			'json' => $webhook_data,
		]);

		// catch errors.
		if ($response->getStatusCode() !== 200) {
			exit("Error: {$response->getStatusCode()} {$response->getReasonPhrase()} {$response->getBody()}");
		}

	}

	
}
