<?php

require_once(__DIR__ . '/../init.php');

// Usage (PowerShell examples):
// php .\Process\startAndSend.php --process=MyProcess --version=1 --step=1
// Or configure PROCESS/PROCESS_VERSION in config.php and simply run:
// php .\Process\startAndSend.php

function parseArgs(array $argv): array {
    $out = [];
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--')) {
            $kv = explode('=', substr($arg, 2), 2);
            $key = $kv[0];
            $val = $kv[1] ?? '1';
            $out[$key] = $val;
        }
    }
    return $out;
}

$args = parseArgs($_SERVER['argv'] ?? []);
$processName = $args['process'] ?? (defined('PROCESS') ? PROCESS : '');
$processVersion = isset($args['version']) ? (int)$args['version'] : (defined('PROCESS_VERSION') ? PROCESS_VERSION : 1);
$stepNo = isset($args['step']) ? (int)$args['step'] : 1;

if (!$processName) {
    fwrite(STDERR, "Bitte Prozessnamen angeben: --process=NAME oder PROCESS in config.php setzen.\n");
    exit(1);
}

$client = new CurlClient();
$client->authenticate();

// 1) Start process (incident)
$startRoute = 'application/incidents/' . $processName;
$startData = [
    'step' => (string)$stepNo,
    'initiator' => 'REST',
    'summary' => 'started via startAndSend.php',
    // Optional: add process table fields if your start step requires them
    // 'processtable[fields][0][name]' => 'TEXTFIELD1',
    // 'processtable[fields][0][value]' => 'Some value',
];

$startResp = $client->post($startRoute, $startData, 200);
if (!count($startResp)) {
    fwrite(STDERR, "Start fehlgeschlagen.\n");
    $client->destroySession();
    exit(2);
}

$incident = $startResp['incidents'][0] ?? [];
$workflowId = $incident['workflowId'] ?? ($incident['jrworkflowid'] ?? null);
$incidentNo = $incident['incidentnumber'] ?? null;

echo "Incident: " . ($incidentNo ?? '-') . "\n";
if (!$workflowId) {
    fwrite(STDERR, "Keine Workflow-ID im Start-Response gefunden.\n");
    $client->destroySession();
    exit(3);
}

echo "Workflow-ID: $workflowId\n";

// 2) Send step
$sendRoute = 'application/steps/' . $workflowId;
$sendData = [
    'processName' => $processName,
    'processVersion' => $processVersion,
    'stepNo' => $stepNo,
    'action' => 'send',
    'workflowId' => $workflowId,
    'dialogType' => 'desktop',
    // Optional: provide dialog field values if required by your step
    // 'dialog' => [
    //     'fields' => [
    //         ['name' => 'int1', 'value' => 1000],
    //     ],
    // ],
];

$client->setJson(true);
$sendResp = $client->put($sendRoute, $sendData);
$client->setJson(false);

if (!count($sendResp)) {
    fwrite(STDERR, "Step-Senden fehlgeschlagen.\n");
    $client->destroySession();
    exit(4);
}

echo "Step gesendet.\n";

$client->destroySession();
