<?php

function get_week_start_end($week, $year)
{
	$dto = new DateTime();

	// $dayOfWeek = 0, Sunday - Saturday.
	$dto->setISODate($year, $week, 0);
	$ret['week_start'] = $dto->format('m-d-Y');
	$ret['week_start_system'] = $dto->format('Y-m-d 00:00:00');
	$ret['week_start_day'] = $dto->format('d');
	$ret['week_start_month'] = $dto->format('F');
	$dto->modify('+6 days');
	$ret['week_end'] = $dto->format('m-d-Y');
	$ret['week_end_system'] = $dto->format('Y-m-d 23:59:59');
	$ret['week_end_day'] = $dto->format('d');
	$ret['week_end_month'] = $dto->format('F');
	return $ret;
}

function dom_string_replace(DOMDocument &$dom, string $match, mixed $new_value): void
{
	$xpath = new DOMXPath($dom);
	$nodes = $xpath->query("//*[not(self::script or self::style)]/text()|//@*|//comment()");

	foreach ($nodes as $node) {
		if ($node instanceof DOMText) {
			$node->nodeValue = str_replace($match, $new_value, $node->nodeValue);
		} elseif ($node instanceof DOMAttr) {
			$node->value = str_replace($match, $new_value, $node->value);
		} elseif ($node instanceof DOMComment) {
			$node->nodeValue = str_replace($match, $new_value, $node->nodeValue);
		}
	}
}

// get prev week number.
function get_prev_week_number()
{
	$week_number = date('W');
	return $week_number === 1 ? 52 : $week_number - 1;
}

function getPrevKey($key, $hash = array()) {
    $keys = array_keys($hash);
    $found_index = array_search($key, $keys);
    if ($found_index === false || $found_index === 0)
        return false;
    return $keys[$found_index-1];
}