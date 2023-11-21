<?php

/**
 * Class MetricPosterTest
 * 
 * @package MetricPoster
 */

declare(strict_types=1);

namespace MetricPoster;

require_once dirname( dirname( __FILE__ ) ) . '/functions.php';

// use brain monkey mock function.
use Brain\Monkey;

/**
 * MetricPosterTest
 */


class MetricPosterTestFunctions extends \WP_UnitTestCase
{

    function test_get_week_start_end()
    {
        $week_start_end = get_week_start_end(1, 2021);
        $this->assertEquals('01-03-2021', $week_start_end['week_start']);
        $this->assertEquals('2021-01-03 00:00:00', $week_start_end['week_start_system']);
        $this->assertEquals('03', $week_start_end['week_start_day']);
        $this->assertEquals('January', $week_start_end['week_start_month']);
        $this->assertEquals('01-09-2021', $week_start_end['week_end']);
        $this->assertEquals('2021-01-09 23:59:59', $week_start_end['week_end_system']);
        $this->assertEquals('2021-01-09', $week_start_end['jp_week_end']);
        $this->assertEquals('09', $week_start_end['week_end_day']);
        $this->assertEquals('January', $week_start_end['week_end_month']);
    }

    function test_dom_string_replace()
    {
        // load new dom document without <!DOCTYPE html> tag.
        $dom = new \DOMDocument();
        $dom->loadHTML('<html><body><p>test</p></body></html>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR);

        dom_string_replace($dom, 'test', 'test2');
        $this->assertEquals('<html><body><p>test2</p></body></html>', trim($dom->saveHTML()));
    }

    function test_get_prev_week_number()
    {
        // assert return value is > 0 and < 53.
        $this->assertGreaterThan(0, get_prev_week_number());
        $this->assertLessThan(53, get_prev_week_number());
    }

    function test_getPrevKey()
    {
        $hash = array(
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        );

        $this->assertEquals('key2', getPrevKey('key3', $hash));
        $this->assertEquals('key1', getPrevKey('key2', $hash));
        $this->assertEquals(false, getPrevKey('key1', $hash));
    }

    function test_number_format_short()
    {
        $this->assertEquals('1.0K', number_format_short(1000));
        $this->assertEquals('2.0K', number_format_short(2000));
        $this->assertEquals('1.1K', number_format_short(1100));
        $this->assertEquals('150.0K', number_format_short(150000));
        $this->assertEquals('1.5M', number_format_short(1500000));
        $this->assertEquals('1.5B', number_format_short(1500000000));
    }

    function test_get_correct_year()
    {
        $this->assertEquals('2022', get_correct_year( 52, 2023));
    }
}
