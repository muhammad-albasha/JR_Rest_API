<?php

require_once(__DIR__ . '/../init.php');

$client = new CurlClient();

$client->authenticate();

$response = $client->get('application/users/' . USERNAME . '/archives/' . ARCHIVE);
$archiveDetails = $response['users']['archives'][0];

$requiredFields = [];

foreach ($archiveDetails['indexFieldDefinitions'] as $fieldDefinition) {
    if ($fieldDefinition['required']) {
        array_push($requiredFields, $fieldDefinition['name']);
    }
}
echo "Pflichtfelder: " . implode(',', $requiredFields) . "\n";

$client->destroySession();
