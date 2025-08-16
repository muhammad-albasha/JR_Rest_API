<?php

require_once(__DIR__ . '/../init.php');

const ROUTE = 'application/jobarchive/archives/' . ARCHIVE . '/documents';

$client = new CurlClient();
$client->authenticate();

$fileToArchive = new CURLFile(FILE_STORAGE . 'myTest1.txt');
$indexFields = [
    'indexFields[0][name]' => 'field1',
    'indexFields[0][value]' => 'My REST document archived using cURL',
    'indexFields[1][name]' => 'dateField1',
    'indexFields[1][value]' => '2017-06-21T12:23:45+01:00',
    'files[0]' => $fileToArchive
];


$response = $client->post(ROUTE, $indexFields);

if (!count($response)) {
    return;
}

echo "Document-ID: " . $response['archivedocumentrevisions'][0]['revisionId'] . "\n";

$client->destroySession();
