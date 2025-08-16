<?php

require_once(__DIR__ . '/../init.php');

$client = new CurlClient();

$client->authenticate();

$client->download('application/jobarchive/archives/' . ARCHIVE . '/documents/28/files');

$client->destroySession();
