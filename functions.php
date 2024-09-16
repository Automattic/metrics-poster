<?php

function get_week_start_end( $week, $year ) {
	$dto = new DateTime();

	// $dayOfWeek = 0, Sunday - Saturday.
	$dto->setISODate( $year, $week, 0 );
	$ret['week_start']        = $dto->format( 'm-d-Y' );
	$ret['week_start_system'] = $dto->format( 'Y-m-d 00:00:00' );
	$ret['week_start_day']    = $dto->format( 'd' );
	$ret['week_start_month']  = $dto->format( 'F' );
	$dto->modify( '+6 days' );
	$ret['week_end']        = $dto->format( 'm-d-Y' );
	$ret['week_end_system'] = $dto->format( 'Y-m-d 23:59:59' );
	$ret['jp_week_end']     = $dto->format( 'Y-m-d' );
	$ret['week_end_day']    = $dto->format( 'd' );
	$ret['week_end_month']  = $dto->format( 'F' );
	return $ret;
}

function dom_string_replace( DOMDocument &$dom, string $match, mixed $new_value ): void {
	$xpath = new DOMXPath( $dom );
	$nodes = $xpath->query( '//*[not(self::script or self::style)]/text()|//@*|//comment()' );

	foreach ( $nodes as $node ) {
		if ( $node instanceof DOMText ) {
			$node->nodeValue = str_replace( $match, $new_value, $node->nodeValue );
		} elseif ( $node instanceof DOMAttr ) {
			$node->value = str_replace( $match, $new_value, $node->value );
		} elseif ( $node instanceof DOMComment ) {
			$node->nodeValue = str_replace( $match, $new_value, $node->nodeValue );
		}
	}
}

// get prev week number.
function get_prev_week_number( $week_number = null ) {
	$week_number = $week_number ?? (int) date( 'W' );
	return $week_number <= 1 ? 52 : $week_number - 1;
}

function getPrevKey( $current_key, $arr = array() ) {

	$prev_key = false;

	// if key is 1, return previous key as 52.
	if ( $current_key === 1 ) {
		$prev_key = 52;
		return $prev_key;
	}


	// if key is numeric and greater than 1, return previous key.
	// where 1 is January and 52 is December.
	if ( is_numeric( $current_key ) && $current_key > 1 ) {
		$prev_key = $current_key - 1;
	}

	// check if key exists in array.
	if ( array_key_exists( $prev_key, $arr ) ) {
		return $prev_key;
	} else {
		// if key does not exist in array, return previous key.
		$keys = array_keys( $arr );
		foreach ( $keys as $k ) {
			if ( $k === $current_key ) {
				return $prev_key;
			}
			$prev_key = $k;
		}
	}

	return $prev_key;
}

function number_format_short( $number, $precision = 1 ) {
	$precision = $precision ?? 1;
	if ( $number < 1000 ) {
		// Anything less than a million
		$number_format_short = number_format( $number );
	} elseif ( $number < 1000000 ) {
		// Anything less than a billion
		$number_format_short = number_format( $number / 1000, $precision ) . 'K';
	} elseif ( $number < 1000000000 ) {
		// Anything less than a trillion
		$number_format_short = number_format( $number / 1000000, $precision ) . 'M';
	} elseif ( $number < 1000000000000 ) {
		// Anything less than a trillion
		$number_format_short = number_format( $number / 1000000000, $precision ) . 'B';
	} else {
		// At least a trillion
		$number_format_short = number_format( $number / 1000000000000, $precision ) . 'T';
	}

	return $number_format_short;
}

function get_correct_year( int $week = null, $year = null ) {
	$week = $week ?? get_prev_week_number();
	$year = $year ?? date( 'Y' );
	if ( $week === 52 ) {
		$year = $year - 1;
	}
	return $year;
}

function convert_back_to_original_value( $val ): float {
	// extract unit from $val and $previous_column. case insensitive.
	$val_unit = preg_match( '/[a-zA-Z]+/', $val, $matches ) ? $matches[0] : '';
	$val_unit = strtolower( $val_unit );

	// convert to float.
	$val = str_replace( array( 's', 'ms', 'm', 'k', 'b', 't' ), '', strtolower( $val ) );

	// convert number string with comma to float.
	$val = str_replace( ',', '', $val );
	$val = (float) $val;

	// based on unit, try to convert value to float.
	switch ( $val_unit ) {
		case 'm':
			$val = (float) $val * 1000000;
			break;
		case 'k':
			$val = (float) $val * 1000;
			break;
		case 'b':
			$val = (float) $val * 1000000000;
			break;
		case 't':
			$val = (float) $val * 1000000000000;
			break;
		default:
			$val = (float) $val;
			break;
	}

	return $val;
}

function get_cwv_metric( $metric ) {
	// switch case for each metric value to include time unit.
	switch ( $metric ) {
		case 'cumulativeLayoutShift':
			$unit = 's';
			break;
		case 'firstContentfulPaint':
			$unit = 's';
			break;
		case 'firstInputDelay':
			$unit = 'ms';
			break;
		case 'largestContentfulPaint':
			$unit = 's';
			break;
		case 'firstPaint':
			$unit = 's';
			break;
		case 'interactionToNextPaint':
			$unit = 's';
			break;
		default:
			$unit = '';
			break;
	}

	return $unit;
}
