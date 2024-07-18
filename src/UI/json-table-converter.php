<?php

// strict types
declare(strict_types=1);

// namespace
namespace MetricPoster;

use MetricPoster\JsonToGutenbergTable;

// check if form is submitted.
if ( isset( $_POST['submit'] ) ) {
	$json_data = $_POST['json_data'];

	// remove slashes.
	$json_data = stripslashes( $json_data );

	// decode json data.
	$json_data = json_decode( $json_data, true );

	// check if json data is valid.
	if ( json_last_error() === JSON_ERROR_NONE ) {
		try {
			// create table from json data.
			$caption    = $json_data['caption'] ?? '';
			$table      = new JsonToGutenbergTable( $json_data['data'], $json_data['headers'], 'table', null, $caption );
			$table_html = $table->getTableHtml();
		} catch ( \Exception $e ) {
			$error_message = $e->getMessage();
		}   
	} else {
		$error_message = json_last_error_msg();
	}
}

?>

<style>
	#json_data {
		width: 100%;
		height: 200px;
	}

	/* emulate dark code editor textarea */
	#json_data {
		background-color: #1e1e1e;
		color: #d4d4d4;
		font-family: monospace;
		font-size: 1rem;
		padding: 10px;
	}

	.table-output {
		margin-top: 20px;
	}

	.error {
		background-color: #f8d7da;
		color: #721c24;
		padding: 10px;
		margin-top: 20px;
	}

	.toast {
		background-color: #f8d7da;
		color: #721c24;
		padding: 10px;
		margin-top: 20px;
	}

	.toast h2 {
		margin-top: 0;
	}

	.toast p {
		margin-bottom: 0;
	}

	.output-wrapper {
		position: relative;
	}

	#copy-button {
		position: absolute;
		top: 0;
		right: 0;
	}

	#table-html {
		font-family: monospace;
		font-size: 1rem;
		padding: 10px;
		border: 1px solid #ccc;
		white-space: pre-wrap;
	}

	pre {
		white-space: pre-wrap;
	}

	table {
		border-collapse: collapse;
		width: 100%;
	}

	.example-code {
		background-color: #f8f9fa;
		padding: 10px;
		border: 1px solid #ccc;
		font-size: 0.85rem;
		width: 100%;
	}

	.example-wrapper {
		margin-top: 20px;
	}

	.example-wrapper p {
		margin-bottom: 0;
	}

	#submit-json__btn {
		margin-top: 20px;
		padding: 10px;
		background-color: #0073aa;
		color: #fff;
		border: none;
		cursor: pointer;
		border-radius: 4px;
	}
	
</style>

<?php if ( isset( $table_html ) ) : ?>
	<div class="table-output">
		<h2>Rendered Gutenberg Table</h2>
		<?php echo $table_html; ?>

		<h2>HTML Output</h2>
		<!-- output html code with copy button -->
		<div class="output-wrapper">
			<button id="copy-button">Copy</button>
			<pre id="table-html"><?php echo htmlspecialchars( $table_html ); ?></pre>
		</div>

		<script>
			document.getElementById('copy-button').addEventListener('click', function() {
				var tableHtml = document.getElementById('table-html');

				// copy tableHTML.innerText to clipboard.
				navigator.clipboard.writeText(tableHtml.innerText).then(function() {
					alert('Table HTML copied to clipboard');
				}, function(err) {
					console.error('Failed to copy: ', err);
				});
			});
		</script>
	</div>
<?php endif; ?>

<?php if ( isset( $error_message ) ) : ?>
	<div class="error toast">
		<h2>Error</h2>
		<p><?php echo $error_message; ?></p>
	</div>
<?php endif; ?>

<form method="post">
	<label for="json_data">Paste JSON data here:</label>
	<textarea name="json_data" id="json_data" cols="30" rows="10"><?php echo $_POST['json_data'] ?? ''; ?></textarea>

	<div class="example-wrapper">
		<p>example json structure: </p>
		<pre class="example-code">
{ 
  "headers": [ "Metric", "Week 2", "Week 3"],
  "data": [ [ "LCP", "1.2", "1.3"], ["CLS", "0.5", "0.7"] ],
  "caption": "Metrics for the week"
}
		</pre>
	</div>

	<input id="submit-json__btn" type="submit" name="submit" value="Submit">

</form>

<hr>

<h2>How to use JsonToGutenbergTable class in php</h2>

<p>JsonToGutenbergTable class is used to convert JSON data to a Gutenberg table.</p>

<h3>Example</h3>

<p>Here is an example of how to use the JsonToGutenbergTable class:</p>

<pre>
<code>
$table_data = '[{"Name":"John Doe","Age":30,"Country":"USA"},{"Name":"Jane Doe","Age":25,"Country":"Canada"}]';
$headers = ['Name', 'Age', 'Country'];
$table_type = 'table';
$table_cell_styles_cb = function($value, $key) { return if ($key === 'Age') { return 'color: red;'; } };
$caption_text = 'Table Caption';

$table = new JsonToGutenbergTable($table_data, $headers, $table_type, $table_cell_styles_cb, $caption_text);
echo $table->getTableHtml();
</code>

</pre>

<p>OR get a DOMDocument object</p>

<pre>
<code>
$table = new JsonToGutenbergTable($table_data, $headers, $table_type, $table_cell_styles_cb, $caption_text);
$dom = $table->getTableDomDocument();
</code>
</pre>

<p>Then import the DOMDocument object into another DOMDocument object.</p>

<pre>
<code>
$dom = new \DOMDocument();
$dom->loadHTML($table->getTableHtml());

foreach ( $table->childNodes as $child ) {
    $importedNode = $dom->importNode( $child, true );
    $content_body->appendChild( $importedNode );
}

</code>
</pre>

<!-- thanks and feel free to steal this and use or contribute -->
<p>Thanks for using this tool. Feel free to steal this code and use it in your projects. If you have any suggestions or improvements, please feel free to contribute to the <a href="https://github.com/Automattic/metrics-poster/blob/main/src/class-json-to-table.php" target="_blank">source code</a>.</p>
