<?php

require_once(__DIR__ . '/../init.php');

const ROUTE = 'application/jobarchive/archives/' . ARCHIVE . '/indexdata';

$client = new CurlClient();
$client->authenticate();

$indexFields = [
    'revisions' => [
        [
            'revisionId' => '32',
            'indexFields'  => [
                [
                    'name' => 'dateField1',
                    'value' => '2017-10-02T12:23:45+01:00',
                ]
            ]
        ],
        [
            'revisionId' => '33',
            'indexFields'  => [
                [
                    'name' => 'dateField1',
                    'value' => '2017-10-02T12:23:45+01:00',
                ]
            ]
        ],
    ]
];


$client->setJson(true);
$response = $client->patch(ROUTE, $indexFields);
$client->setJson(false);

if (!count($response)) {
    return;
}

echo "Response: " . var_export($response, true) . "\n";

$client->destroySession();
