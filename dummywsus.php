<?php
/*
Dummy WSUS
Version: 1.0.0

Author: whatever127
GitHub: https://github.com/whatever127/dummywsus
License: MIT License
*/

////////////////////////////////////////////////////////////////////////////////
//// Functions used by Dummy WSUS //////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////

//Retrieves SelfUpdate package and saves it as blob
function getBlobFromWU($uri, $blob) {
    $file = @fopen("blobs/$blob", 'w');
    if(!$file) return false;

    $url = "http://ds.download.windowsupdate.com/v11/3/windowsupdate/$uri";
    $req = curl_init($url);

    curl_setopt($req, CURLOPT_HEADER, 0);
    curl_setopt($req, CURLOPT_FILE, $file);
    curl_setopt($req, CURLOPT_ENCODING, '');
    curl_setopt($req, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($req, CURLOPT_HTTPHEADER, array(
        'User-Agent: Windows-Update-Agent/10.0.10011.16384 Client-Protocol/1.70',
    ));

    $out = curl_exec($req);
    if(curl_getinfo($req, CURLINFO_HTTP_CODE) != 200) $out = false;

    curl_close($req);
    fclose($file);

    if(!$out) unlink("blobs/$blob");
    return($out);
}

//Streams a file with all required headers by the Windows Update client
function streamFile($fileName) {
    $file = @fopen($fileName, 'r');
    if(!$file) {
        http_response_code(404);
        return false;
    }

    $stat = fstat($file);
    $name = basename($fileName);

    header('Content-Type: application/octet-stream');
    header("Content-Disposition: attachment; filename=\"$name\"");
    header('Content-Length: '.$stat['size']);
    header('Last-Modified: '.gmdate('D, j M Y H:i:s T', $stat['mtime']));

    if($_SERVER['REQUEST_METHOD'] == 'HEAD') {
        fclose($file);
        return true;
    }

    do {
        echo fread($file, 4096);
    } while(!feof($file));

    fclose($file);
    return true;
}

//Initiates streaming of the file using streamFile(), if it does not exist
//attempts to download it using getBlobFromWU()
function sendSelfUpdate($uri) {
    if(strpos($uri, 'selfupdate/wuident.cab') !== false) {
        streamFile("wuident.cab");
        die();
    }

    $uri = preg_replace('/\?.*/', '', $uri);
    $blob = sha1(strtolower($uri));

    if(!file_exists('blobs')) {
        mkdir('blobs');
    }

    if(!file_exists("blobs/$blob")) {
        $blobDownloaded = getBlobFromWU($uri, $blob);
        if(!$blobDownloaded) {
            http_response_code(404);
            die();
        }
    }

    streamFile("blobs/$blob");
    die();
}

//Retrieves base URL of the server, used only by the usage page
function getBaseUrl() {
    $baseUrl = '';
    if(isset($_SERVER['HTTPS'])) {
        $baseUrl .= 'https://';
    } else {
        $baseUrl .= 'http://';
    }

    $baseUrl .=  $_SERVER['HTTP_HOST'];
    return $baseUrl;
}

//Generates dummy cookie
function genCookie() {
    $hex = '';
    for($i = 0; $i < 48; $i++) {
        $hex .= dechex(rand(0, 15));
    }
    return base64_encode(hex2bin($hex));
}

//Prints usage of the service
function printUsage() {
    $demoUri = getBaseUrl().explode('?', strtolower($_SERVER['REQUEST_URI']))[0];
    echo <<<EOD
<html>
    <head>
        <title>Dummy WSUS service 1.0.0</title>
    </head>
    <body>
        <h1>Dummy WSUS service 1.0.0</h1>

        <h2>Description</h2>
        <p>This dummy WSUS service can be used to bypass all updates on Windows
        versions capable of connecting to WSUS server.</p>

        <h2>Usage</h2>
        <p>To connect to this service you need to enter the following address to
        the WSUS configuration in the group policy:</p>
        <p><code>$demoUri?</code></p>

        <p>Please note that the <b>?</b> has to be appended at the end of the
        URL because of limitations this implementation of WSUS server.</p>
    </body>
</html>
EOD;
}

////////////////////////////////////////////////////////////////////////////////
//// Main code of the Dummy WSUS ///////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////

//Retrieve data after ? which is needed to handle WU SelfUpdate
$uriExploded = explode('?', strtolower($_SERVER['REQUEST_URI']));
$uri = isset($uriExploded[1]) ? $uriExploded[1] : "" ;
$uri = str_replace('/selfupdate/', 'selfupdate/', $uri);

//Check if WU is doing SelfUpdate or user has opened the service page in the
//browser and handle accordingly
if(strpos($uri, 'selfupdate') !== false) {
    sendSelfUpdate($uri);
    die();
} elseif($uri == '' && $_SERVER['REQUEST_METHOD'] == 'GET') {
    printUsage();
    die();
}

//Set proper Content-Type header for WU communication
header('Content-Type: text/xml');

$lastChangeTime = time() - 604800;
$expiresTime = time() + 600;

$lastChange = gmdate(DATE_W3C, $lastChangeTime);
$expires = gmdate(DATE_W3C, $expiresTime);

$postData = file_get_contents("php://input");
$revisions = '';

//Make client drop any Installed Update IDs it has cached
preg_match('/<InstalledNonLeafUpdateIDs.*?>(.*)<\/InstalledNonLeafUpdateIDs>/', $postData, $match);
if(isset($match[1])) {
    $revisions = "{$match[1]}";
}

//Make client drop any Other Update IDs it has cached
preg_match('/<OtherCachedUpdateIDs.*?>(.*)<\/OtherCachedUpdateIDs>/', $postData, $match);
if(isset($match[1])) {
    $revisions .= "{$match[1]}";
}

$cookie = genCookie();

//Templates of responses for WU communication
$getConfigResponse = <<<XML
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
    <s:Body xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
        <GetConfigResponse xmlns="http://www.microsoft.com/SoftwareDistribution/Server/ClientWebService">
            <GetConfigResult>
                <LastChange>$lastChange</LastChange>
                <IsRegistrationRequired>false</IsRegistrationRequired>
                <AuthInfo>
                    <AuthPlugInInfo>
                        <PlugInID>Anonymous</PlugInID>
                        <ServiceUrl/>
                        <Parameter/>
                    </AuthPlugInInfo>
                </AuthInfo>
                <Properties/>
            </GetConfigResult>
        </GetConfigResponse>
    </s:Body>
</s:Envelope>
XML;

$getCookieResponse = <<<XML
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
    <s:Body xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
        <GetCookieResponse xmlns="http://www.microsoft.com/SoftwareDistribution/Server/ClientWebService">
            <GetCookieResult>
                <Expiration>$expires</Expiration>
                <EncryptedData>$cookie</EncryptedData>
            </GetCookieResult>
        </GetCookieResponse>
    </s:Body>
</s:Envelope>
XML;

$syncUpdatesResponse = <<<XML
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
    <s:Body xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
        <SyncUpdatesResponse xmlns="http://www.microsoft.com/SoftwareDistribution/Server/ClientWebService">
            <SyncUpdatesResult>
                <OutOfScopeRevisionIDs>$revisions</OutOfScopeRevisionIDs>
                <Truncated>false</Truncated>
                <NewCookie>
                    <Expiration>$expires</Expiration>
                    <EncryptedData>$cookie</EncryptedData>
                </NewCookie>
                <DriverSyncNotNeeded>true</DriverSyncNotNeeded>
            </SyncUpdatesResult>
        </SyncUpdatesResponse>
    </s:Body>
</s:Envelope>
XML;

$reportingService = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
    <soap:Body>
        <ReportEventBatchResponse xmlns="http://www.microsoft.com/SoftwareDistribution">
            <ReportEventBatchResult>true</ReportEventBatchResult>
        </ReportEventBatchResponse>
    </soap:Body>
</soap:Envelope>
XML;

$internalError = <<<XML
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
    <s:Body>
        <s:Fault>
            <faultcode xmlns:a="http://schemas.microsoft.com/net/2005/12/windowscommunicationfoundation/dispatcher">a:InternalServiceFault</faultcode>
            <faultstring xml:lang="en-US">The server was unable to process the request due to an internal error.</faultstring>
        </s:Fault>
    </s:Body>
</s:Envelope>
XML;

//Check if we received SOAPAction header from client, if not, fail gracefully
if(isset($_SERVER['HTTP_SOAPACTION'])) {
    $action = $_SERVER['HTTP_SOAPACTION'];
} else {
    http_response_code(500);
    echo $internalError;
    die();
}

//Check SOAPAction and respond accordingly, if action is unknown, fail
switch($action) {
    case '"http://www.microsoft.com/SoftwareDistribution/Server/ClientWebService/GetConfig"':
        echo $getConfigResponse;
        break;

    case '"http://www.microsoft.com/SoftwareDistribution/Server/ClientWebService/GetCookie"':
        echo $getCookieResponse;
        break;

    case '"http://www.microsoft.com/SoftwareDistribution/Server/ClientWebService/SyncUpdates"':
        echo $syncUpdatesResponse;
        break;

    case '"http://www.microsoft.com/SoftwareDistribution/ReportEventBatch"':
        echo $reportingService;
        break;

    default:
        http_response_code(500);
        echo $internalError;
        break;
}
