<?php

echo "start\n";

require_once(__DIR__ . '/../init.php');

$client = new CurlClient();

$client->setDebugMode(true);

$client->authenticate();

$archives = $client->get('application/users/' . USERNAME . '/archives');

// Diagnostics: show raw response structure
echo "\nRaw response:\n";
var_export($archives);
echo "\n\n";

foreach ($archives['users']['archives'] as $archive) {
    echo "Name: " . $archive['name'] . "\n";
    echo "GUID: " . $archive['guid'] . "\n";
    echo "Table: " . $archive['table'] . "\n";
}

$client->destroySession();
