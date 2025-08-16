<?php

require_once(__DIR__ . '/../init.php');

const ROUTE = 'application/jobdata/tables/' . JOBDATA_TABLE_GUID . '/datasets';

$client = new CurlClient();
$client->authenticate();

$inputData = [
    'dataset' => [
        'sname'  => 'CSI2',
        'sadress' => 'CSI str. 99a',
        'sdate' => '2017-09-03T13:32:45+01:00',
        'sphone' => '098-9877876',
        'srating' => 8
    ],
];

$client->setJson(true);
$response = $client->post(ROUTE, $inputData, 200);

if (count($response)) {
    echo "Data set ID: " . $response['datasets'][0]['jrid'] . "\n";
}

$client->destroySession();

