<?php

require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/CurlClient.php');

// Enable verbose error reporting for local development/CLI runs
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// If this file is executed directly (not via require), show a quick help message
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
	echo "Dieses Projekt enthält Beispielskripte. Starte eines davon, z.B.:\n\n";
	echo "  php .\\Archive\\listArchives.php\n";
	echo "  php .\\Archive\\listArchiveInformation.php\n";
	echo "  php .\\Process\\getInboxSteps.php\n\n";
	echo "Konfiguration: passe Konstanten in config.php an (BASE_URL, USERNAME, PASSWORD etc.).\n";
}
