<?php

require_once(__DIR__ . '/../init.php');

const ROUTE = 'application/incidents/' . PROCESS;

$client = new CurlClient();
$client->authenticate();

$file1 = new CURLFile(FILE_STORAGE . 'myTest1.txt');
$file2 = new CURLFile(FILE_STORAGE . 'hello.txt');
$file3 = new CURLFile(FILE_STORAGE . 'whitepaper.pdf');

$inputData = [
    'step' => '1',
    'initiator' => 'REST',
    'summary' => 'process initiated with REST API',
    'processtable[fields][0][name]' => 'TEXTFIELD1',
    'processtable[fields][0][value]' => 'some value from rest',
    'processtable[fields][1][name]' => 'DATEFIELD1',
    'processtable[fields][1][value]' => '2017-08-29T11:00:00+01:00',
    'processtable[fields][2][name]' => 'DECIMALFIELD1',
    'processtable[fields][2][value]' => '12345.78',
    'processtable[fields][3][name]' => 'UPLOADEDFILE',
    'processtable[fields][3][value]' => $file1,
    'subtables[0][name]' => 'SRESTALLFIELDTYPES',
    'subtables[0][rows][0][fields][0][name]' => 'INTFIELD1',
    'subtables[0][rows][0][fields][0][value]' => 100,
    'subtables[0][rows][0][fields][1][name]' => 'UPLOADEDFILE',
    'subtables[0][rows][0][fields][1][value]' => $file2,
    'subtables[0][rows][1][fields][0][name]' => 'INTFIELD1',
    'subtables[0][rows][1][fields][0][value]' => 100,
    'subtables[0][rows][1][fields][1][name]' => 'UPLOADEDFILE',
    'subtables[0][rows][1][fields][1][value]' => $file3,
];


$response = $client->post(ROUTE, $inputData, 200);

if (count($response)) {
    echo "Incident: " . $response['incidents'][0]['incidentnumber'] . "\n";
    echo "Workflow-ID: " . $response['incidents'][0]['workflowId'] . "\n";
}

$client->destroySession();
