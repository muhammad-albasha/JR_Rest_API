<?php
require_once(__DIR__ . '/../init.php');

const ROUTE = 'application/jobdata/tables/' . JOBDATA_TABLE_GUID . '/datasets';

$client = new CurlClient();
$client->authenticate();


$response = $client->get(ROUTE);

if (count($response)) {
    foreach ($response['datasets'] as $dataSet) {
        foreach ($dataSet as $columnName => $columnValue) {
            echo $columnName . ": " . $columnValue . "\n";
        }
        echo "\n";
    }
}

$client->destroySession();
