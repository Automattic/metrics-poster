<?php

// use strict type
declare(strict_types=1);

namespace MetricPoster;

// use guzzle
use GuzzleHttp\Client;


class Jetpack_Metrics {


    private $jp_blogid;
    private $jp_apikey;

    // constructor gets jetpack blogid and apikey from env variable.
    public function __construct($jp_blogid)
    {
        $this->jp_blogid = $jp_blogid;

        if ( \vip_get_env_var( 'JP_APIKEY', '' ) || $_ENV['JP_APIKEY'] ) {
			$this->jp_apikey = \vip_get_env_var( 'JP_APIKEY', $_ENV['JP_APIKEY'] ?? '' );
		} else {
			throw new InvalidArgumentException( 'Jetpack API key not defined' );
		} 

		// check if key is set
		if (empty($this->jp_apikey)) {
			throw new InvalidArgumentException('Jetpack API key not defined');
		}
    }

    // fetch with guzzle for https://stats.wordpress.com/csv.php?api_key=1234567&table=views&end=2023-10-07&days=7&blog_id=178044738&format=json
    public function get_stats($days = 7, $end = null, $table = 'views')
    {
        $client = new Client([
            'base_uri' => 'https://stats.wordpress.com/',
            'timeout'  => 2.0,
        ]);

        $end = $end ?? date('Y-m-d');
        $response = $client->request('GET', 'csv.php', [
            'query' => [
                'api_key' => $this->jp_apikey,
                'table' => $table,
                // 'end' => $end, // disabling due to bug in jetpack api.
                'period' => 'week',
                // 'days' => $days,
                'days' => 3,
                'blog_id' => $this->jp_blogid,
                'format' => 'json'
            ]
        ]);

        // convert json response to array.
        $json = json_decode($response->getBody()->getContents(), true);
        
        // foreach obj in array, sum the property "views"
        $sum = 0;

        // foreach ($json as $obj) { $sum += $obj['views']; }

        // convert to date stamp.
        $end = strtotime($end);

        foreach ($json as $obj) { 
            // if $obj['date'] is closest to $end date, then return it.
            $obj_date = strtotime($obj['date']);
            if ($obj_date <= $end) {
                $sum = $obj['views'];
            }            
        }

        // get 2nd obj['views'] in array.
        // $sum = $json[1]['views'];

        // format number like 2100000 to 2.1M
        $sum = \number_format_short($sum);
        
        return $sum;
    }

}