<?php

class CurlClient
{
    private $curlHandle;
    private $cookieFile;

    private $sessionId;
    private $jwt;
    private $isJson = false;
    private $debugMode = false;

    public function __construct()
    {
        $this->cookieFile = CURL_COOKIE_PATH . '/' . USERNAME . microtime(true) . '.cookie';
    }

    public function authenticate()
    {
        $this->init();
        $data = '{
                "username": "' . USERNAME . '",
                "password": "' . PASSWORD . '"
            }';

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        $url = BASE_URL . 'application/sessions';

        curl_setopt($this->curlHandle, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($this->curlHandle, CURLOPT_URL, $url);
        curl_setopt($this->curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curlHandle, CURLOPT_POST, 1);
        curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, $data);
        curl_setopt($this->curlHandle, CURLOPT_COOKIEJAR, $this->cookieFile);
        if ($this->debugMode) {
            curl_setopt($this->curlHandle, CURLOPT_VERBOSE, true);
        }

        $response = curl_exec($this->curlHandle);

        $code = curl_getinfo($this->curlHandle, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $err = curl_error($this->curlHandle);
            $eno = curl_errno($this->curlHandle);
            $this->close();
            $this->emptyJar();
            throw new Exception('Authentication cURL Error: #' . $eno . ' ' . $err);
        }

        if ($code !== 201) {
            $info = curl_getinfo($this->curlHandle);
            $this->close();
            $this->emptyJar();
            $msg = 'Authentication Error: ' . $code . ' Response: ' . var_export($response, true) . ' Info: ' . var_export($info, true);
            throw new Exception($msg);
        }

    $response = json_decode($response, true);
    $this->sessionId = isset($response['sessions'][0]['sessionId']) ? $response['sessions'][0]['sessionId'] : null;
    // Token may not be present for all setups; set only if provided
    $this->jwt = isset($response['token'][0]) ? $response['token'][0] : null;
    }

    public function setJson($value)
    {
        $this->isJson = $value;
    }

    public function getCookieFile()
    {
        return $this->cookieFile;
    }

    public function get($route)
    {
        $this->reset();

        curl_setopt($this->curlHandle, CURLOPT_URL, BASE_URL . $route);
        curl_setopt($this->curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curlHandle, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($this->curlHandle, CURLOPT_COOKIEFILE, $this->cookieFile);

        $response = curl_exec($this->curlHandle);
        $statusCode = curl_getinfo($this->curlHandle, CURLINFO_HTTP_CODE);

        if ($statusCode !== 200) {
            echo "\nGET-Route returned error code " . $statusCode . ". Response: " . var_export($response, true) . "\n";
            return [];
        }

        $decoded = json_decode($response, true);
        return $decoded === null ? [] : $decoded;
    }

    public function download($route)
    {
        $this->reset();

        curl_setopt($this->curlHandle, CURLOPT_URL, BASE_URL . $route);
        curl_setopt($this->curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curlHandle, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($this->curlHandle, CURLOPT_COOKIEFILE, $this->cookieFile);

        $tempFile = fopen('./downloadedFiles.zip', 'w+');
        curl_setopt($this->curlHandle, CURLOPT_FILE , $tempFile);

        $response = curl_exec($this->curlHandle);

        fclose($tempFile);

        $statusCode = curl_getinfo($this->curlHandle, CURLINFO_HTTP_CODE);

        if ($statusCode !== 200) {
            echo "\nCould not download! Returned error code " . $statusCode . ". Response: " . var_export($response, true) . "\n";
        }
    }

    public function post($route, $data, $successCode = 201)
    {
        $this->reset();

        curl_setopt($this->curlHandle, CURLOPT_URL, BASE_URL . $route);
        curl_setopt($this->curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curlHandle, CURLOPT_POST, 1);
        curl_setopt($this->curlHandle, CURLOPT_COOKIEFILE, $this->cookieFile);

        if ($this->isJson) {
            $this->setJsonHeaders();
            curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, json_encode($data));
        } else {
            curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, $data);
        }

        if ($this->debugMode) {
            curl_setopt($this->curlHandle, CURLOPT_VERBOSE, true);
        }

        $response = curl_exec($this->curlHandle);
        $statusCode = curl_getinfo($this->curlHandle, CURLINFO_HTTP_CODE);

        if ($statusCode !== $successCode) {
            echo "\nPOST-Route returned error code " . $statusCode . ". Response: " . var_export($response, true) . "\n";
            return [];
        }

        $decoded = json_decode($response, true);
        return $decoded === null ? [] : $decoded;
    }

    public function put($route, $data)
    {
        $this->reset();

        curl_setopt($this->curlHandle, CURLOPT_URL, BASE_URL . $route);
        curl_setopt($this->curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curlHandle, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($this->curlHandle, CURLOPT_COOKIEFILE, $this->cookieFile);

        if ($this->isJson) {
            $this->setJsonHeaders();
            curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, json_encode($data));
        } else {
            curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, $data);
        }

        if ($this->debugMode) {
            curl_setopt($this->curlHandle, CURLOPT_VERBOSE, true);
        }

        $response = curl_exec($this->curlHandle);
        $statusCode = curl_getinfo($this->curlHandle, CURLINFO_HTTP_CODE);

        if ($statusCode !== 200) {
            echo "\nPUT-Route returned error code " . $statusCode . ". Response: " . var_export($response, true) . "\n";
            return [];
        }

        $decoded = json_decode($response, true);
        return $decoded === null ? [] : $decoded;
    }

    public function patch($route, $data)
    {
        $this->reset();

        curl_setopt($this->curlHandle, CURLOPT_URL, BASE_URL . $route);
        curl_setopt($this->curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curlHandle, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($this->curlHandle, CURLOPT_COOKIEFILE, $this->cookieFile);

        if ($this->isJson) {
            $this->setJsonHeaders();
            curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, json_encode($data));
        } else {
            curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, $data);
        }

        if ($this->debugMode) {
            curl_setopt($this->curlHandle, CURLOPT_VERBOSE, true);
        }

        $response = curl_exec($this->curlHandle);
        $statusCode = curl_getinfo($this->curlHandle, CURLINFO_HTTP_CODE);

        if ($statusCode !== 200) {
            echo "\nPUT-Route returned error code " . $statusCode . ". Response: " . var_export($response, true) . "\n";
            return [];
        }

        $decoded = json_decode($response, true);
        return $decoded === null ? [] : $decoded;
    }

    public function delete($route)
    {
        $this->reset();

        curl_setopt($this->curlHandle, CURLOPT_URL, BASE_URL . $route);
        curl_setopt($this->curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curlHandle, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($this->curlHandle, CURLOPT_COOKIEFILE, $this->cookieFile);

        if ($this->debugMode) {
            curl_setopt($this->curlHandle, CURLOPT_VERBOSE, true);
        }

        $response = curl_exec($this->curlHandle);
        $statusCode = curl_getinfo($this->curlHandle, CURLINFO_HTTP_CODE);

        if ($statusCode !== 204) {
            echo "\nDELETE-Route returned error code " . $statusCode . ". Response: " . var_export($response, true) . "\n";
            return [];
        }

        return [];
    }

    public function destroySession()
    {
        // If we have no sessionId, nothing to destroy
        if (empty($this->sessionId)) {
            $this->close();
            $this->emptyJar();
            return;
        }

        $this->reset();

        curl_setopt($this->curlHandle, CURLOPT_URL, BASE_URL . 'application/sessions/' . $this->sessionId);
        curl_setopt($this->curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curlHandle, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($this->curlHandle, CURLOPT_COOKIEFILE, $this->cookieFile);

        $response = curl_exec($this->curlHandle);
        $statusCode = curl_getinfo($this->curlHandle, CURLINFO_HTTP_CODE);

        $this->close();
        $this->emptyJar();

        // Accept 204 (deleted) and 200 (some servers respond 200) and ignore 404 (already deleted/not found)
        if (!in_array($statusCode, [200, 204, 404], true)) {
            throw new Exception($statusCode . ' - Could not delete session: ' . var_export($response, true));
        }
    }

    public function setDebugMode($value)
    {
        $this->debugMode = $value;
    }

    private function init()
    {
        $this->curlHandle = curl_init();
        $this->configureDefaults();
    }

    private function reset()
    {
        curl_reset($this->curlHandle);
        $this->configureDefaults();
    }

    private function configureDefaults()
    {
        // Avoid hanging forever on network issues
        $connectTimeout = defined('CURL_CONNECT_TIMEOUT') ? CURL_CONNECT_TIMEOUT : 10;
        $timeout = defined('CURL_TIMEOUT') ? CURL_TIMEOUT : 30;
        curl_setopt($this->curlHandle, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
        curl_setopt($this->curlHandle, CURLOPT_TIMEOUT, $timeout);

        // SSL verification per config
        $verify = defined('CURL_VERIFY_SSL') ? CURL_VERIFY_SSL : true;
        curl_setopt($this->curlHandle, CURLOPT_SSL_VERIFYPEER, $verify);
        curl_setopt($this->curlHandle, CURLOPT_SSL_VERIFYHOST, $verify ? 2 : 0);
        if ($verify && defined('CA_BUNDLE_PATH') && is_string(CA_BUNDLE_PATH) && file_exists(CA_BUNDLE_PATH)) {
            curl_setopt($this->curlHandle, CURLOPT_CAINFO, CA_BUNDLE_PATH);
        }

        // Debug verbosity per config/flag
        if (defined('DEBUG_CURL') && DEBUG_CURL) {
            curl_setopt($this->curlHandle, CURLOPT_VERBOSE, true);
        }
    }

    private function setJsonHeaders()
    {
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];
        curl_setopt($this->curlHandle, CURLOPT_HTTPHEADER, $headers);
    }

    private function close()
    {
        curl_close($this->curlHandle);
    }

    private function emptyJar()
    {
        if (file_exists($this->cookieFile)) {
            echo "delete file " . $this->cookieFile . "\n";
            unlink($this->cookieFile);
        }
    }
}

