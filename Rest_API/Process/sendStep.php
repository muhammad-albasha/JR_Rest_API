<?php

require_once(__DIR__ . '/../init.php');

const ROUTE = 'application/steps/4c057f28ebc1740d5334bff12ddfe8870000000048'; // change Workflow-ID before executing this file

$client = new CurlClient();
$client->authenticate();


$inputData = [
    'processName' => PROCESS,
    'processVersion' => 1,
    'stepNo' => 1,
    'action' => 'send',
    'workflowId' => '4c057f28ebc1740d5334bff12ddfe8870000000048',
    'dialogType' => 'desktop',
    'dialog' => [
        'fields' => [
            [
                'name' => 'int1',
                'value' => 1000,
            ]
        ],
    ],
];

$client->setJson(true);
$response = $client->put(ROUTE, $inputData);

if (count($response)) {
    echo "Process-ID: " . $response['steps'][0]['processId'] . "\n";
}

$client->destroySession();
