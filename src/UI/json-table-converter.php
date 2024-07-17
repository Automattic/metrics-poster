<?php

// strict types
declare(strict_types=1);

// namespace
namespace MetricPoster;

use MetricPoster\JsonToGutenbergTable;

// check if form is submitted.
if (isset($_POST['submit'])) {
    $json_data = $_POST['json_data'];

    // remove slashes.
    $json_data = stripslashes($json_data);

    // decode json data.
    $json_data = json_decode($json_data, true);

    // check if json data is valid.
    if (json_last_error() === JSON_ERROR_NONE) {
        
        try {
            // create table.
            // create table from json data.
            $table = new JsonToGutenbergTable($json_data['data'], $json_data['headers']);

            $caption = $json_data['caption'] ?? '';
            $table->addCaption($caption);
            $table_html = $table->getTableHtml();
        } catch (\Exception $e) {
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

<?php if (isset($table_html)) : ?>
    <div class="table-output">
        <h2>Table</h2>
        <!-- output html code with copy button -->
        <div class="output-wrapper">
            <button id="copy-button">Copy</button>
            <pre id="table-html"><?php echo htmlspecialchars($table_html); ?></pre>
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

<?php if (isset($error_message)) : ?>
    <div class="error toast">
        <h2>Error</h2>
        <p><?php echo $error_message; ?></p>
    </div>
<?php endif; ?>

<form method="post">
    <label for="json_data">Paste JSON data here:</label>
    <textarea name="json_data" id="json_data" cols="30" rows="10"><?php echo $_POST['json_data'] ?? ''; ?></textarea>

    <div class="example-wrapper">
        <p>example: </p>
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
