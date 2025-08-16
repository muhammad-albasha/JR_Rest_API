<?php

require_once(__DIR__ . '/../init.php');

const ROUTE = 'application/jobdata/tables/' . JOBDATA_TABLE_GUID . '/datasets/5';  // change dataset ID before execution

$client = new CurlClient();
$client->authenticate();

$inputData = [
    'dataset' => [
        'sphone' => '098-987-7876',
        'srating' => 9
    ],
];

$client->setJson(true);
$response = $client->put(ROUTE, $inputData);

if (count($response)) {
    foreach ($response['datasets'][0] as $columnName => $columnValue) {
        echo $columnName . ": " . $columnValue . "\n";
    }
}

$client->destroySession();

