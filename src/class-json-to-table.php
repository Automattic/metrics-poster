<?php

declare(strict_types=1);

namespace MetricPoster;

/**
 * Class JsonToGutenbergTable
 * 
 * Converts JSON data to a Gutenberg table.
 * 
 * @package MetricPoster
 * @version 1.0.0
 * @since 1.0.0
 * @link https://github.com/Automattic/metrics-poster/blob/main/src/class-json-to-table.php
 * 
 * @example
 * 
 * $table_data = '[{"Name":"John Doe","Age":30,"Country":"USA"},{"Name":"Jane Doe","Age":25,"Country":"Canada"}]';
 * $headers = ['Name', 'Age', 'Country'];
 * $table_type = 'table';
 * $table_cell_styles_cb = function($value, $key) { return if ($key === 'Age') { return 'color: red;'; } };
 * $caption_text = 'Table Caption';
 * 
 * $table = new JsonToGutenbergTable($table_data, $headers, $table_type, $table_cell_styles_cb, $caption_text);
 * echo $table->getTableHtml();
 * 
 * OR get a DOMDocument object
 * 
 * $table = new JsonToGutenbergTable($table_data, $headers, $table_type, $table_cell_styles_cb, $caption_text);
 * $dom = $table->getTableDomDocument();
 * 
 * Then import the DOMDocument object into another DOMDocument object.
 * 
 * $dom = new \DOMDocument();
 * $dom->loadHTML($table->getTableHtml());
 * 
 * 
 */
class JsonToGutenbergTable {
	// properties
	private $table_data;
	private $headers = array();
	private $table_html;
	private $cell_inline_styles_cb;
	private $has_comments = false;
	private $table_type;

	/**
	 * Constructor
	 *
	 * @param mixed    $table_data JSON string or Array data. 
	 * @param array    $headers Table headers.
	 * @param string   $table_type Table type, default table or stripes.
	 * @param callable $cell_inline_styles_cb  Callback function to add inline styles to cells.
     * @param string   $caption_text Caption text.
	 */
	public function __construct( $table_data, array $headers = array(), string $table_type = 'table', callable $cell_inline_styles_cb = null, string $caption_text = '' ) {
		$this->table_data = $table_data;

		// check if json is type string, else type array.
		if ( is_string( $table_data ) ) {
			// decode json
			$this->table_data = json_decode( $this->table_data, true );
		}

		// set headers
		if ( count( $headers ) > 0 ) {
			$this->headers = $headers;
		}
		
		// DomDocument table element.
		$this->table_html = new \DOMDocument();

		$this->cell_inline_styles_cb = $cell_inline_styles_cb;

		$this->table_type = $table_type;

		$this->createTable();

        // add caption to table even if empty. See notes in addCaption method.
        $this->addCaption( $caption_text );
	}

	/**
	 * Execute cell inline style callback.
	 *
	 * @param string $value Cell value.
	 * @param string $key Cell key.
	 * @return string
	 */
	public function executeCellInlineStyleCallback( $value, $key ) {
		// return call_user_func($this->cell_inline_styles_cb, $value, $key );
		if ( is_callable( $this->cell_inline_styles_cb ) ) {
			return call_user_func( $this->cell_inline_styles_cb, $value, $key );
		} else {
			throw new \Exception( 'Callback is not callable' );
		}
	}

	/**
	 * Create a table from json data.
	 *
	 * @return void
	 */
	public function createTable(): void {

		$data = $this->table_data;

		// create table
		$table = $this->table_html->createElement( 'table' );
		$tbody = $this->table_html->createElement( 'tbody' );
		$thead = $this->table_html->createElement( 'thead' );
		$tr    = $this->table_html->createElement( 'tr' );

		// check if headers are set.
		if ( count( $this->headers ) > 0 ) {
			$tr = $this->table_html->createElement( 'tr' );
			foreach ( $this->headers as $header ) {
				$th = $this->table_html->createElement( 'th', $header );
				$tr->appendChild( $th );
			}
			$thead->appendChild( $tr );
			$table->appendChild( $thead );
		} else {
			// create table header
			foreach ( $data[0] as $key => $value ) {
				$th = $this->table_html->createElement( 'th', $key );
				$tr->appendChild( $th );
			}
			$thead->appendChild( $tr );
			$table->appendChild( $thead );
		}

		// create table body
		foreach ( $data as $row ) {
			$tr = $this->table_html->createElement( 'tr' );
			foreach ( $row as $key => $val ) {
				$value = $val;
				$slug  = $key;

				// check if value is an array, then extract value.
				if ( is_array( $val ) ) {
					$value = $val['value'] ?? '';
					$slug  = $val['slug'] ?? '';
				}

				$td = $this->table_html->createElement( 'td', "{$value}" );

				// add inline style to cell.
				if ( is_callable( $this->cell_inline_styles_cb ) ) {
					$inline_style = $this->executeCellInlineStyleCallback( $value, $slug );
					$td->setAttribute( 'style', $inline_style );
				}

				$tr->appendChild( $td );
			}
			$tbody->appendChild( $tr );
		}
		$table->appendChild( $tbody );

		// append table to document
		$this->table_html->appendChild( $table );
	}

	/**
	 * Add Gutenberg editor comments to table.
	 *
	 * @return void
	 */
	public function addTableComments(): void {
		// TODO: figure out why this is not working when passed to createComment.
		$stripes_string = ' wp:table {"hasFixedLayout":false,"className":"is-style-stripes"} ';

		if ( $this->table_type === 'stripes' ) {
			$table_comment = $stripes_string;
		} else {
			$table_comment = ' wp:table ';
		}

		// create opening comment and append to dom.
		$comment = $this->table_html->createComment( $table_comment );
		$this->table_html->insertBefore( $comment, $this->table_html->firstChild );

		// create closing comment and append to dom.
		$comment = $this->table_html->createComment( ' /wp:table ' );
		$this->table_html->appendChild( $comment );
	}

	/**
	 * Add caption to table.
	 *
	 * @param string $caption_text Caption text.
	 * @return void
	 */
	public function addCaption( string $caption_text = '' ): void {

        // Add figure element to table here even if there is no caption.
        // TODO: Handle this better.
		$figure = $this->table_html->createElement( 'figure' );
		$figure->setAttribute( 'class', 'wp-block-table' );
		$this->table_html->appendChild( $figure );

		// move table to figure.
		$table = $this->table_html->getElementsByTagName( 'table' )[0];
		$figure->appendChild( $table );

		if ( ! empty( $caption_text ) ) {
			$figcaption = $this->table_html->createElement( 'figcaption', $caption_text );
			$figure->appendChild( $figcaption );
		}
	}

	/**
	 * Get table html.
	 *
	 * @return \DOMDocument
	 */
	public function getTableDomDocument(): \DOMDocument {
		
		if ( ! $this->has_comments ) {
			$this->addTableComments();
			$this->has_comments = true;
		}

		$this->table_html->saveHTML();
		return $this->table_html;
	}

	/**
	 * Get table html.
	 *
	 * @return string
	 */
	public function getTableHtml(): string {
		
		if ( ! $this->has_comments ) {
			$this->addTableComments();
			$this->has_comments = true;
		}

		// return preg_replace('~<(?:!DOCTYPE|/?(?:html|body))[^>]*>\s*~i', '', $this->table_html->saveHTML());
		return $this->table_html->saveHTML();
	}
}
