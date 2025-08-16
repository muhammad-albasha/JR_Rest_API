<?php

require_once(__DIR__ . '/../init.php');

// Simple watcher: polls a folder and uploads any new .pdf/.png/.jpg as an archive document
// with index fields Aktiv = 1 and DocuID = RevisionId. Stores a state file to avoid re-upload.

const WATCH_DIR = FILE_STORAGE;               // by default use FILE_STORAGE; change if needed
const STATE_FILE = __DIR__ . '/.watch_state.json';
const SLEEP_SECONDS = 5;                      // polling interval
if (!defined('DEST_ARCHIVE_DIR')) {
    define('DEST_ARCHIVE_DIR', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'Bearbeitete_Rechnung'); // move processed files here
}

function loadState()
{
    if (!file_exists(STATE_FILE)) return [];
    $json = file_get_contents(STATE_FILE);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function saveState(array $state)
{
    file_put_contents(STATE_FILE, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function listCandidateFiles(string $dir): array
{
    if (!is_dir($dir)) return [];
    $files = scandir($dir);
    $out = [];
    foreach ($files as $f) {
        if ($f === '.' || $f === '..') continue;
        $path = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $f;
    if (!is_file($path)) continue;
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    // Allow typical e-invoice containers as well (xml, cii, zip)
    if (in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'tif', 'tiff', 'xml', 'cii', 'zip'])) {
            $out[] = $path;
        }
    }
    sort($out);
    return $out;
}

function generateDocuId(string $filepath): string
{
    // Use a deterministic but unique-ish id (time + hash of path)
    return date('YmdHis') . '_' . substr(sha1($filepath . '|' . microtime(true)), 0, 8);
}

function detectEinvoiceType(string $filepath): string
{
    $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

    // Helper closures to read head/tail safely
    $readHead = function(string $path, int $len = 131072) {
        return @file_get_contents($path, false, null, 0, $len) ?: '';
    };
    $readTail = function(string $path, int $len = 131072) {
        $size = @filesize($path);
        if ($size === false) return '';
        $start = max(0, $size - $len);
        $fh = @fopen($path, 'rb');
        if (!$fh) return '';
        if ($start > 0) @fseek($fh, $start);
        $data = @fread($fh, $len) ?: '';
        @fclose($fh);
        return $data;
    };

    // PDF: try detect ZUGFeRD/Factur-X embedded XML
    if ($ext === 'pdf') {
        $head = strtolower($readHead($filepath, 262144));
        $tail = strtolower($readTail($filepath, 1048576)); // last ~1MB
        $blob = $head . "\n" . $tail;

        $hasEmbedded = (strpos($blob, '/embeddedfiles') !== false) || (strpos($blob, '/afrelationship') !== false);
        $hasZugferd = (strpos($blob, 'zugferd') !== false) || (strpos($blob, 'urn:ferd') !== false);
        $hasFacturx = (strpos($blob, 'factur-x') !== false) || (strpos($blob, 'facturx') !== false);
        $hasCii = (strpos($blob, 'crossindustryinvoice') !== false) || (strpos($blob, 'rsm:crossindustryinvoice') !== false);

        if ($hasEmbedded && ($hasZugferd || $hasFacturx || $hasCii)) {
            if ($hasFacturx) return 'Factur-X';
            if ($hasZugferd) return 'ZUGFeRD';
            // If embedded but only CII markers are visible, assume Factur-X container
            return 'Factur-X';
        }
        return 'PDF';
    }

    // ZIP: inspect inner XML files for type
    if ($ext === 'zip' && class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($filepath) === true) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (!$stat) continue;
                $name = strtolower($stat['name'] ?? '');
                if (substr($name, -4) === '.xml' || substr($name, -4) === '.cii') {
                    $stream = $zip->getStream($stat['name']);
                    if ($stream) {
                        $xmlHead = stream_get_contents($stream, 262144) ?: '';
                        fclose($stream);
                        $type = detectXmlInvoiceType($xmlHead);
                        if ($type !== 'XML') { $zip->close(); return $type; }
                    }
                }
            }
            $zip->close();
        }
        return 'ZIP';
    }

    // XML/CII files
    if (in_array($ext, ['xml', 'cii'])) {
        $head = $readHead($filepath, 262144);
        return detectXmlInvoiceType($head);
    }

    return strtoupper($ext);
}

function detectXmlInvoiceType(string $xmlHead): string
{
    $h = strtolower($xmlHead);

    // XRechnung marker (KoSIT URN or the word xrechnung in CustomizationID/Profile)
    $isUbl = (strpos($h, 'urn:oasis:names:specification:ubl:schema:xsd:invoice-2') !== false) ||
             (strpos($h, 'commonbasiccomponents-2') !== false) ||
             (strpos($h, '<invoice ') !== false && strpos($h, ':invoice') !== false && strpos($h, 'ubl') !== false);
    $hasXrech = (strpos($h, 'xrechnung') !== false) || (strpos($h, 'kosit') !== false);
    if ($isUbl && $hasXrech) return 'XRechnung';
    if ($isUbl) return 'UBL';

    // UN/CEFACT CII markers
    $isCii = (strpos($h, 'crossindustryinvoice') !== false) ||
             (strpos($h, 'rsm:crossindustryinvoice') !== false) ||
             (strpos($h, 'un/cefact') !== false) ||
             (strpos($h, 'qdt:') !== false && strpos($h, 'udt:') !== false && strpos($h, 'ram:') !== false);
    if ($isCii) return 'CII'; // aka UN/CEFACT CII

    // Generic XML fallback
    return 'XML';
}

function uploadInvoice(CurlClient $client, string $filepath, string $docuId)
{
    // Build multipart form according to archiveDocument.php example
    $file = new CURLFile($filepath);
    $dataType = detectEinvoiceType($filepath);
    $indexFields = [
        // Map your archive index field names here; adjust to actual field names in your archive
        'indexFields[0][name]' => 'Aktiv',
        'indexFields[0][value]' => '1',
        'indexFields[1][name]' => ARCHIVE_FIELD_DATA_TYPE,
        'indexFields[1][value]' => $dataType,
        'files[0]' => $file,
    ];

    $route = 'application/jobarchive/archives/' . ARCHIVE . '/documents';
    $response = $client->post($route, $indexFields);
    if (!count($response)) {
        echo "Upload failed for $filepath\n";
        return false;
    }
    $revId = $response['archivedocumentrevisions'][0]['revisionId'] ?? null;
    echo "Uploaded $filepath as revision $revId\n";

    // Set DocuID = RevisionId via PATCH
    if ($revId) {
        $patchRoute = 'application/jobarchive/archives/' . ARCHIVE . '/indexdata';
        $payload = [
            'revisions' => [
                [
                    'revisionId' => (string)$revId,
                    'indexFields' => [
                        [ 'name' => 'DocuID', 'value' => (string)$revId ],
                    ],
                ],
            ],
        ];
        $client->setJson(true);
        $patchResp = $client->patch($patchRoute, $payload);
        $client->setJson(false);
        if (count($patchResp)) {
            echo "DocuID gesetzt auf RevisionId $revId\n";
        } else {
            echo "DocuID-Update fehlgeschlagen (Revision $revId)\n";
        }
    }

    return ['revisionId' => $revId];
}

/**
 * Moves a file to the archive directory. Creates directory if missing, ensures unique filename.
 * Returns new full path on success, or null on failure.
 */
function moveFileToArchive(string $sourcePath, string $destDir = DEST_ARCHIVE_DIR): ?string
{
    if (!file_exists($sourcePath) || !is_file($sourcePath)) {
        return null;
    }

    // Ensure destination directory exists
    if (!is_dir($destDir)) {
        if (!@mkdir($destDir, 0777, true) && !is_dir($destDir)) {
            echo "Archiv-Verzeichnis kann nicht erstellt werden: $destDir\n";
            return null;
        }
    }

    $baseName = basename($sourcePath);
    $targetPath = rtrim($destDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $baseName;

    // Ensure unique filename
    if (file_exists($targetPath)) {
        $pi = pathinfo($baseName);
        $name = $pi['filename'] ?? 'file';
        $ext = isset($pi['extension']) && $pi['extension'] !== '' ? '.' . $pi['extension'] : '';
        $i = 1;
        do {
            $candidate = rtrim($destDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name . " ($i)" . $ext;
            $i++;
        } while (file_exists($candidate));
        $targetPath = $candidate;
    }

    // Try rename; if it fails (e.g., cross-device), fall back to copy+unlink
    if (@rename($sourcePath, $targetPath)) {
        return $targetPath;
    }
    if (@copy($sourcePath, $targetPath)) {
        @unlink($sourcePath);
        return $targetPath;
    }

    echo "Datei konnte nicht verschoben werden: $sourcePath -> $targetPath\n";
    return null;
}

/**
 * Startet den angegebenen Prozess und sendet den angegebenen Schritt direkt.
 * Gibt Basisinfos zurück (incidentNo, workflowId, sendOk). Wirft keine Exception,
 * sondern loggt Fehler und gibt leere Werte zurück.
 */
function startAndSendProcess(CurlClient $client, string $processName, int $processVersion, int $stepNo): array
{
    // 1) Start process (incident)
    $startRoute = 'application/incidents/' . $processName;
    $startData = [
        'step' => (string)$stepNo,
        'initiator' => 'REST',
        'summary' => 'auto-started by watchInvoices.php',
    ];

    $startResp = $client->post($startRoute, $startData, 200);
    if (!count($startResp)) {
        echo "Prozess-Start fehlgeschlagen (process=$processName, version=$processVersion, step=$stepNo).\n";
        return ['incidentNo' => null, 'workflowId' => null, 'sendOk' => false];
    }

    $incident = $startResp['incidents'][0] ?? [];
    $workflowId = $incident['workflowId'] ?? ($incident['jrworkflowid'] ?? null);
    $incidentNo = $incident['incidentnumber'] ?? null;

    echo "Incident gestartet: " . ($incidentNo ?? '-') . " | Workflow-ID: " . ($workflowId ?? '-') . "\n";
    if (!$workflowId) {
        echo "Keine Workflow-ID im Start-Response gefunden.\n";
        return ['incidentNo' => $incidentNo, 'workflowId' => null, 'sendOk' => false];
    }

    // 2) Send step
    $sendRoute = 'application/steps/' . $workflowId;
    $sendData = [
        'processName' => $processName,
        'processVersion' => $processVersion,
        'stepNo' => $stepNo,
        'action' => 'send',
        'workflowId' => $workflowId,
        'dialogType' => 'desktop',
    ];

    $client->setJson(true);
    $sendResp = $client->put($sendRoute, $sendData);
    $client->setJson(false);

    $sendOk = (bool)count($sendResp);
    echo $sendOk ? "Step gesendet.\n" : "Step-Senden fehlgeschlagen.\n";

    return [
        'incidentNo' => $incidentNo,
        'workflowId' => $workflowId,
        'sendOk' => $sendOk,
    ];
}

// Main loop
$state = loadState();
if (!isset($state['processed'])) $state['processed'] = [];

while (true) {
    $files = listCandidateFiles(WATCH_DIR);
    foreach ($files as $path) {
        $processedEntry = $state['processed'][$path] ?? null;

        // Neuer Upload-Fall
        if ($processedEntry === null) {
            try {
                $client = new CurlClient();
                $client->authenticate();

                $docuId = generateDocuId($path);
                $uploaded = uploadInvoice($client, $path, $docuId);

                $processInfo = ['incidentNo' => null, 'workflowId' => null, 'sendOk' => false];
                if ($uploaded) {
                    // Direkt danach Prozess RECHNUNGEN v1 Step 10 starten und senden
                    $processInfo = startAndSendProcess($client, 'RECHNUNGEN', 1, 10);

                    // Nach erfolgreichem Start: WorkflowId/IncidentNumber ins Archiv schreiben
                    if (!empty($processInfo['workflowId']) || !empty($processInfo['incidentNo'])) {
                        $patchRoute = 'application/jobarchive/archives/' . ARCHIVE . '/indexdata';
                        $indexFields = [];
                        if (!empty($processInfo['workflowId'])) {
                            $indexFields[] = [ 'name' => ARCHIVE_FIELD_WORKFLOW_ID, 'value' => (string)$processInfo['workflowId'] ];
                        }
                        if (!empty($processInfo['incidentNo'])) {
                            $indexFields[] = [ 'name' => ARCHIVE_FIELD_INCIDENT_NO, 'value' => (string)$processInfo['incidentNo'] ];
                        }
                        if (!empty($indexFields)) {
                            $payload = [
                                'revisions' => [
                                    [
                                        'revisionId' => (string)($uploaded['revisionId'] ?? ''),
                                        'indexFields' => $indexFields,
                                    ],
                                ],
                            ];
                            $client->setJson(true);
                            $resp = $client->patch($patchRoute, $payload);
                            $client->setJson(false);
                            if (count($resp)) {
                                echo "Workflow/Incident in Indexdaten gespeichert.\n";
                            } else {
                                echo "Konnte Workflow/Incident nicht in Indexdaten speichern.\n";
                            }
                        }
                    }
                }

                $client->destroySession();

        if ($uploaded) {
                    // Nach erfolgreichem Senden verschieben wir die Datei ins Archiv
                    $movedPath = null;
                    if (!empty($processInfo['sendOk']) && !empty($processInfo['workflowId'])) {
                        $movedPath = moveFileToArchive($path);
                        if ($movedPath) {
                            echo "Datei verschoben nach: $movedPath\n";
                        }
                    }

                    $state['processed'][$path] = [
            // DocuID = RevisionId
            'docuId' => $uploaded['revisionId'] ?? null,
                        'uploadedAt' => date(DATE_ATOM),
                        'processStarted' => (bool)$processInfo['sendOk'] && !empty($processInfo['workflowId']),
                        'incidentNumber' => $processInfo['incidentNo'] ?? null,
            'workflowId' => $processInfo['workflowId'] ?? null,
            'revisionId' => $uploaded['revisionId'] ?? null,
                        'movedTo' => $movedPath,
                        'movedAt' => $movedPath ? date(DATE_ATOM) : null,
                    ];
                    saveState($state);
                }
            } catch (Throwable $e) {
                // log, keep going
                echo "Error processing $path: " . $e->getMessage() . "\n";
            }
            continue;
        }

        // Bereits hochgeladen, aber Prozess evtl. noch nicht gestartet => Retry nur Prozessstart
        if (empty($processedEntry['processStarted'])) {
            try {
                $client = new CurlClient();
                $client->authenticate();

                $processInfo = startAndSendProcess($client, 'RECHNUNGEN', 1, 10);

                $client->destroySession();

                $state['processed'][$path]['processStarted'] = (bool)$processInfo['sendOk'] && !empty($processInfo['workflowId']);
                $state['processed'][$path]['incidentNumber'] = $processInfo['incidentNo'] ?? null;
                $state['processed'][$path]['workflowId'] = $processInfo['workflowId'] ?? null;
                if (!empty($state['processed'][$path]['processStarted'])) {
                    $movedPath = moveFileToArchive($path);
                    if ($movedPath) {
                        echo "Datei verschoben nach: $movedPath\n";
                        $state['processed'][$path]['movedTo'] = $movedPath;
                        $state['processed'][$path]['movedAt'] = date(DATE_ATOM);
                    }
                }
                saveState($state);
            } catch (Throwable $e) {
                echo "Error starting process for $path: " . $e->getMessage() . "\n";
            }
        }
    }
    // sleep between scans
    sleep(SLEEP_SECONDS);
}
