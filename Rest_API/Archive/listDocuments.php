<?php

require_once(__DIR__ . '/../init.php');

$client = new CurlClient();

$client->authenticate();
$filter = '?where[intField1][gt]=100&where[intField1][lt]=300';

$documents = $client->get('application/jobarchive/archives/' . ARCHIVE . '/index' . $filter);

foreach ($documents['archivedocuments'] as $document) {
    echo "Revision ID: " . $document['revisionId'] . "\n";
    echo "Index data: " . var_export($document['indexFields'], true) . "\n";
    if (isset($document['keywordFields'])) {
        echo "Keywords: " . var_export($document['keywordFields'], true) . "\n";
    }
}

$client->destroySession();
