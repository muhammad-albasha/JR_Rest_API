<?php

require_once(__DIR__ . '/../init.php');

$client = new CurlClient();

$client->authenticate();

$client->delete('application/jobarchive/archives/' . ARCHIVE . '/documents/1');

$client->destroySession();
