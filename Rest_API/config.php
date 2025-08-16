<?php

const BASE_URL = 'https://jracademy.demo.jobrouter.cloud/api/rest/v2/';  // REST API URL
const USERNAME = 'academy1_de'; // Username for authentication
const PASSWORD = "JRAcademy_Tr@1ning"; // Password for authentication

const ARCHIVE = "220BD671-D875-FF15-6C99-22022682020D"; // Archive table or GUID for JobArchive examples

const PROCESS = "RECHNUNGEN"; // Process name for process examples
const PROCESS_VERSION = 1; // Process version for process examples
const PROCESS_BOX_ID = 1; // Process box id for process examples

const JOBDATA_TABLE_GUID = ''; // GUID of JobData table for JobData examples

// Use project-relative paths (portable across machines)
const CURL_COOKIE_PATH = __DIR__ . DIRECTORY_SEPARATOR . 'cookie_path';
// Use repo-level JobArchive as input directory instead of Rest_API/files/
if (!defined('FILE_STORAGE')) {
	define('FILE_STORAGE', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'JobArchive' . DIRECTORY_SEPARATOR);
}

// JobArchive index field names (adjust to match your archive configuration)
// Existing fields already used: 'Aktiv', 'DocuID'. Add process metadata here:
const ARCHIVE_FIELD_DOCUID = 'DocuID';
const ARCHIVE_FIELD_WORKFLOW_ID = 'WorkflowId';       // e.g., 'WorkflowId' or the exact field name in your archive
const ARCHIVE_FIELD_INCIDENT_NO = 'IncidentNumber';   // e.g., 'IncidentNumber'
const ARCHIVE_FIELD_DATA_TYPE = 'DataType';           // e.g., 'DataType' to store e-invoice format (UBL/XRechnung/CII/PDF)

// cURL/HTTP configuration
// If you get "SSL certificate problem: unable to get local issuer certificate",
// either download a CA bundle (cacert.pem) and set CA_BUNDLE_PATH accordingly or
// temporarily set CURL_VERIFY_SSL=false to unblock in development.
const CURL_VERIFY_SSL = false; // if false => don't verify peer/host (NOT for production)
const CA_BUNDLE_PATH = __DIR__ . DIRECTORY_SEPARATOR . 'cacert.pem'; // place cacert.pem here if available
const CURL_CONNECT_TIMEOUT = 10; // seconds
const CURL_TIMEOUT = 60; // seconds
const DEBUG_CURL = false; // enable CURLOPT_VERBOSE logs

// Ensure required local directories exist at runtime
if (!is_dir(CURL_COOKIE_PATH)) {
	@mkdir(CURL_COOKIE_PATH, 0777, true);
}
if (!is_dir(FILE_STORAGE)) {
	@mkdir(FILE_STORAGE, 0777, true);
}
