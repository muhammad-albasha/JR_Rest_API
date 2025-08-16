<?php

require_once(__DIR__ . '/../init.php');

$client = new CurlClient();
$client->authenticate();

$response = $client->get('application/workitems/inbox');

if (count($response)) {

    echo "Number of steps: " . $response['meta']['pagination']['total'] . "\n\n";

    foreach ($response['workitems'] as $item) {
        echo "Process: " . $item['jrprocessname'] . "\n";
        echo "Step: " . $item['jrsteplabel'] . "\n";
        echo "Workflow-ID: " . $item['jrworkflowid'] . "\n\n";
    }
}

$client->destroySession();
