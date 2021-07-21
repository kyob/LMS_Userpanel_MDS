<?php

/*
 *  LMS userpanel module to control devices by ACS server
 *
 *  (C) Copyright 2019 kopiszka.com
 *
 *  Donation make my day :)
 *  Bitcoin (BTC): bc1qvwahntcrwjtdp0ntfd0l6kdvdr9d9h6atp6qrr
 *  Ethereum (ETH): 0xEFCd4b066195652a885d916183ffFfeEEd931f40
 *  Bitcoin SV (BSV): $kopiszka (my HandCash handle)
 *  or any other cryptocurrency if you want just ask for wallet address
 */

if (!class_exists('GuzzleHttp\Client')) {
    exit("Class <mark>GuzzleHttp\Client</mark> not found! Go to LMS-PLUS root path. Then from CLI execute <mark>composer require guzzlehttp/guzzle</mark>. After installing update data with <mark>composer update --no-dev</mark>");
}

use GuzzleHttp\Client;

$acs_url = ConfigHelper::getConfig('mds.acs_url');

$client = new Client([
    'base_uri' => $acs_url,
    'defaults' => [
        'headers' => ['Content-Type' => 'application/json']
    ],
]);


// todo check if host exist
$ch = curl_init($acs_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
if (curl_exec($ch) === false) {
    echo 'Curl error: ' . curl_error($ch);
    die();
}
curl_close($ch);

// todo check if ACS is OK
//exit("Your <mark>acs_url</mark> is empty or invalid. Go to <mark>[LMS-PLUS]/?m=configlist</mark> and add section <mark>userpanel</mark> and name <mark>acs_url</mark>.");

if (defined('USERPANEL_SETUPMODE')) {

    function module_setup()
    {
        global $SMARTY;

        $SMARTY->assign('acs_url', ConfigHelper::getConfig('mds.acs_url'));
        $SMARTY->display('module:mydevicesettings:setup.html');
    }

    function module_submit_setup()
    {
        $db = LMSDB::getInstance();

        $db->Execute(
            'UPDATE uiconfig SET value = ? WHERE section = ? AND var = ?',
            array($_POST['acs_url'], 'mds', 'acs_url')
        );
        header('Location: ?m=userpanel&module=mydevicesettings');
    }
}

function speedTest($file_size, $date_from, $date_to, $direction)
{
    if ($direction == 'Download') {
        $interval = $date_from->diff($date_to);
        $seconds = $interval->format('%s');
        if ((int) $seconds) {
            $download = round(($file_size / 1024 / 1024) * 8 / $seconds) . ' Mb/s';
            return $result = array('speed' => $download, 'seconds' => $seconds);
        } else {
            return false;
        }
    }
}

function post($device_id, $parameterValues)
{
    global $client;
    $data = [
        'json' => [
            "name" => "setParameterValues",
            "parameterValues" => $parameterValues
        ]
    ];
    $response = $client->post("/devices/" . $device_id . "/tasks?connection_request", $data);
}

function getRandomIP()
{
    return mt_rand(0, 255) . "." . mt_rand(0, 255) . "." . mt_rand(0, 255) . "." . mt_rand(0, 255);
}

function getProjectionFromDeviceID($device_id, $projection)
{
    global $client;
    $device_id = filter_var($device_id, FILTER_SANITIZE_STRING);
    $projection = filter_var($projection, FILTER_SANITIZE_STRING);
    $response = $client->get("/devices?query=%7B%22_id%22%3A%22" . $device_id . "%22%7D&projection=" . $projection);
    $json = $response->getBody();
    return json_decode($json, true);
}

function searcharray($value, $key, $array)
{
    foreach ($array as $k => $val) {
        if ($val[$key] == $value) {
            return $k;
        }
    }
    return null;
}

/*
 * curl -i 'http://localhost:7557/devices/F48CEB-Router-QXNN1J1004416/tasks?timeout=3000&connection_request'
 * -X POST --data '{ "name": "refreshObject", "objectName": "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1" }'
 */

function refreshObject($device_id, $objectName)
{
    global $client;
    $is_refreshObject = 0;
    $pending_tasks = getPendingTasks($device_id);
    foreach ($pending_tasks as $item) {
        if ($item['name'] == 'refreshObject') {
            $is_refreshObject = 1;
            continue;
        }
    }
    if ($is_refreshObject == 0) {

        $data = [
            'json' => [
                'name' => 'refreshObject',
                'objectName' => $objectName
            ]
        ];
        $response = $client->post("/devices/" . $device_id . "/tasks?timeout=3000&connection_request", $data);
    }
    header('Location: ?m=mydevicesettings');
}

function refresh($device_id, $options)
{
    global $client;
    $response = $client->post("/devices/" . $device_id . "/tasks", $options);
    $json = $response->getBody();
    return $r = json_decode($json, true);
}

function getPendingTasks($device_id)
{
    global $client;
    $response = $client->get("/tasks/?query=%7B%22device%22%3A%22" . $device_id . "%22%7D");
    $json = $response->getBody();
    $r = json_decode($json, true);
    return $r;
}

function deleteTask($task_id)
{
    global $client;
    $response = $client->delete("/tasks/" . $task_id);
    $json = $response->getBody();
    $r = json_decode($json, true);
    return $r;
}

function module_delete()
{
    global $client;
    $device_id = $_POST['device_id'];
    $response = $client->delete("/devices/" . $device_id);
    unset($_POST);
    header('Location: ?m=mydevicesettings');
}

function module_submit()
{
    $device_id = $_POST['device_id'];
    $number_of_repetitions = 10;

    if ($_POST['action'] == 'deviceInfoRefresh') {
        refreshObject($device_id, 'InternetGatewayDevice.DeviceInfo');
    }

    if ($_POST['action'] == 'wanStatsRefresh') {
        refreshObject($device_id, 'InternetGatewayDevice.WANDevice.1.WANCommonInterfaceConfig');
    }

    if ($_POST['action'] == 'wanInfoRefresh') {
        refreshObject($device_id, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1');
    }

    if ($_POST['action'] == 'wlanRefresh') {
        $radio_id = filter_var($_POST['radio_id'], FILTER_VALIDATE_INT);
        if ((int) $radio_id) {
            refreshObject($device_id, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.' . $radio_id);
        }
    }

    if ($_POST['action'] == 'pingRequested') {
        $host = filter_var($_POST['host'], FILTER_VALIDATE_IP);
        $parameterValues = [
            ["InternetGatewayDevice.IPPingDiagnostics.DiagnosticsState", "Requested", "xsd:string"],
            ["InternetGatewayDevice.IPPingDiagnostics.Host", $host, "xsd:string"],
            ["InternetGatewayDevice.IPPingDiagnostics.NumberOfRepetitions", $number_of_repetitions, "xsd:unsignedInt­"],
        ];
        post($device_id, $parameterValues);
        unset($_POST, $parameterValues);
        refreshObject($device_id, 'InternetGatewayDevice.IPPingDiagnostics');
    }
    if ($_POST['action'] == 'tracerouteRequested') {
        $host = $_POST['host'];
        $parameterValues = [
            ["InternetGatewayDevice.TraceRouteDiagnostics.DiagnosticsState", "Requested", "xsd:string"],
            ["InternetGatewayDevice.TraceRouteDiagnostics.Host", $host, "xsd:string"],
        ];
        post($device_id, $parameterValues);
        unset($_POST, $parameterValues);
        refreshObject($device_id, 'InternetGatewayDevice.TraceRouteDiagnostics');
    }
    if ($_POST['action'] == 'syslogEnable') {
        $host = $_POST['host'];
        $parameterValues = [
            ["InternetGatewayDevice.DeviceInfo.X_DLINK_Syslog.Enable", 1, "xsd:boolean"],
        ];
        post($device_id, $parameterValues);
        unset($_POST, $parameterValues);
        refreshObject($device_id, 'InternetGatewayDevice.DeviceInfo.X_DLINK_Syslog');
    }
    if ($_POST['action'] == 'syslogDisable') {
        $host = $_POST['host'];
        $parameterValues = [
            ["InternetGatewayDevice.DeviceInfo.X_DLINK_Syslog.Enable", 0, "xsd:boolean"],
        ];
        post($device_id, $parameterValues);
        unset($_POST, $parameterValues);
        refreshObject($device_id, 'InternetGatewayDevice.DeviceInfo.X_DLINK_Syslog');
    }
    if ($_POST['action'] == 'downloadRequested') {
        $url = $_POST['url'];
        $parameterValues = [
            ["InternetGatewayDevice.DownloadDiagnostics.DiagnosticsState", "Requested", "xsd:string"],
            ["InternetGatewayDevice.DownloadDiagnostics.DownloadURL", $url, "xsd:string"]
        ];
        post($device_id, $parameterValues);
        unset($_POST, $parameterValues);
        refreshObject($device_id, 'InternetGatewayDevice.DownloadDiagnostics');
    }

    if ($_POST['action'] == 'downloadRefresh') {
        refreshObject($device_id, 'InternetGatewayDevice.DownloadDiagnostics');
    }

    if ($_POST['action'] == 'pingRefresh') {
        refreshObject($device_id, 'InternetGatewayDevice.IPPingDiagnostics');
    }

    if ($_POST['action'] == 'tracerouteRefresh') {
        refreshObject($device_id, 'InternetGatewayDevice.TraceRouteDiagnostics');
    }
    header('Location: ?m=mydevicesettings');
}

function module_ping()
{
    global $SMARTY;
    $device_id = $_POST['device_id'];
    $mac = strtolower($_POST['mac']);
    //if (!filter_var($mac, FILTER_VALIDATE_MAC)) { from PHP 5.5
    if (!$mac) {
        header('Location: ?m=mydevicesettings');
    }
    $projection = 'InternetGatewayDevice.IPPingDiagnostics';
    $result = getProjectionFromDeviceID($device_id, $projection);
    $IPPingDiagnostics = $result[0]['InternetGatewayDevice']['IPPingDiagnostics'];

    $SMARTY->assign('random_ip', getRandomIP());
    $SMARTY->assign('device_id', $device_id);
    $SMARTY->assign('mac', $mac);
    $SMARTY->assign('IPPingDiagnostics', $IPPingDiagnostics);
    $SMARTY->display('module:ping.html');
}

function module_traceroute()
{
    global $SMARTY;
    $device_id = $_POST['device_id'];
    $mac = strtolower($_POST['mac']);
    if (!$mac) {
        header('Location: ?m=mydevicesettings');
    }
    $projection = 'InternetGatewayDevice.TraceRouteDiagnostics';
    $result = getProjectionFromDeviceID($device_id, $projection);
    $TraceRouteDiagnostics = $result[0]['InternetGatewayDevice']['TraceRouteDiagnostics'];

    $SMARTY->assign('device_id', $device_id);
    $SMARTY->assign('random_ip', getRandomIP());
    $SMARTY->assign('TraceRouteDiagnostics', $TraceRouteDiagnostics);
    $SMARTY->assign('mac', $mac);
    $SMARTY->display('module:traceroute.html');
}

function module_download()
{
    global $SMARTY;
    $device_id = $_POST['device_id'];
    $mac = strtolower($_POST['mac']);
    $download_urls = array(
        'tele2' => array(
            'name' => 'tele2 100M',
            'url' => 'http://speedtest.tele2.net/100MB.zip',
        ),
        'thinkbroadband' => array(
            'name' => 'thinkbroadband 100M',
            'url' => 'http://ipv4.download.thinkbroadband.com/100MB.zip',
        ),
    );
    $projection = 'InternetGatewayDevice.DownloadDiagnostics';
    $result = getProjectionFromDeviceID($device_id, $projection);
    $DownloadDiagnostics = $result[0]['InternetGatewayDevice']['DownloadDiagnostics'];

    $DownloadTest = speedTest($DownloadDiagnostics['TestBytesReceived']['_value'], new DateTime($DownloadDiagnostics['BOMTime']['_value']), new DateTime($DownloadDiagnostics['EOMTime']['_value']), 'Download');

    $SMARTY->assign('DownloadTest', $DownloadTest);
    $SMARTY->assign('device_id', $device_id);
    $SMARTY->assign('mac', $mac);
    $SMARTY->assign('download_urls', $download_urls);
    $SMARTY->assign('DownloadDiagnostics', $DownloadDiagnostics);
    $SMARTY->display('module:download.html');
}

function module_log()
{
    global $SMARTY;
    $device_id = $_POST['device_id'];
    $mac = strtolower($_POST['mac']);
    $last_events = 100;

    $projection = 'InternetGatewayDevice.DeviceInfo.DeviceLog';
    $result = getProjectionFromDeviceID($device_id, $projection);
    $DeviceLog = $result[0]['InternetGatewayDevice']['DeviceInfo']['DeviceLog']['_value'];
    $DeviceLog = explode(PHP_EOL, $DeviceLog);

    $projection = 'InternetGatewayDevice.DeviceInfo.X_DLINK_Syslog';
    $result = getProjectionFromDeviceID($device_id, $projection);
    $X_DLINK_Syslog = $result[0]['InternetGatewayDevice']['DeviceInfo']['X_DLINK_Syslog'];

    if ($X_DLINK_Syslog['Enable']['_value']) {
        $syslog = $X_DLINK_Syslog;
    } else {
        $syslog = 0;
    }

    $SMARTY->assign('device_id', $device_id);
    $SMARTY->assign('mac', $mac);
    $SMARTY->assign('syslog', $syslog);
    $SMARTY->assign('last_events', $last_events);
    $SMARTY->assign('DeviceLog', array_slice(array_reverse(array_filter($DeviceLog)), 0, $last_events));
    $SMARTY->display('module:log.html');
}

function module_wlan()
{
    global $SMARTY;
    $device_id = $_POST['device_id'];
    $mac = strtolower($_POST['mac']);
    $radio_id = $_POST['radio_id'];
    if ($_POST['action'] == 'changePassword') {
        $PreSharedKey = $_POST['PreSharedKey'][$radio_id];

        $parameterValues = [
            ["InternetGatewayDevice.LANDevice.1.WLANConfiguration." . $radio_id . ".PreSharedKey.1.PreSharedKey", $PreSharedKey, "xsd:string"],
        ];
        post($device_id, $parameterValues);
        unset($_POST, $options);
        header('Location: ?m=mydevicesettings');
    }
    if ($_POST['action'] == 'updateWLAN') {
        $SSID = $_POST['SSID'];
        $RadioEnabled = $_POST['RadioEnabled'];
        $WPS = $_POST['WPS'][$radio_id];
        $TransmitPower = $_POST['TransmitPower'];
        $Channel = $_POST['Channel'];
        if ($Channel == 0) {
            $AutoChannelEnable = 1;
        } else {
            $AutoChannelEnable = 0;
        }
        if ($RadioEnabled) {
            $RadioEnabled = 1;
        } else {
            $RadioEnabled = 0;
        }
        if ($WPS) {
            $WPS = 1;
        } else {
            $WPS = 0;
        }
        $parameterValues = [
            ["InternetGatewayDevice.LANDevice.1.WLANConfiguration." . $radio_id . ".SSID", $SSID, "xsd:string"],
            ["InternetGatewayDevice.LANDevice.1.WLANConfiguration." . $radio_id . ".RadioEnabled", $RadioEnabled, "xsd:boolean"],
            ["InternetGatewayDevice.LANDevice.1.WLANConfiguration." . $radio_id . ".WPS.Enable", $WPS, "xsd:boolean"],
            ["InternetGatewayDevice.LANDevice.1.WLANConfiguration." . $radio_id . ".TransmitPower", $TransmitPower, "xsd:unsignedInt­"],
            ["InternetGatewayDevice.LANDevice.1.WLANConfiguration." . $radio_id . ".Channel", $Channel, "xsd:unsignedInt­"],
            ["InternetGatewayDevice.LANDevice.1.WLANConfiguration." . $radio_id . ".AutoChannelEnable", $AutoChannelEnable, "xsd:boolean"],
        ];
        post($device_id, $parameterValues);
        unset($_POST, $options);
        header('Location: ?m=mydevicesettings');
    }
    $projection = 'InternetGatewayDevice.LANDevice.1.WLANConfiguration';
    $result = getProjectionFromDeviceID($device_id, $projection);

    $wlan = $result[0]['InternetGatewayDevice']['LANDevice'][1]['WLANConfiguration'][$radio_id];
    $wlan['TransmitPowerSupported']['_value'] = explode(",", $wlan['TransmitPowerSupported']['_value']);
    $wlan['PossibleChannels']['_value'] = explode(",", $wlan['PossibleChannels']['_value']);

    $SMARTY->assign('wlan', array_filter($wlan));
    $SMARTY->assign('device_id', $device_id);
    $SMARTY->assign('mac', $mac);
    $SMARTY->assign('radio_id', $radio_id);
    $SMARTY->display('module:wlan.html');
}

function module_deviceInfo()
{
    global $SMARTY, $client;

    $mac = strtolower($_POST['mac']);
    $request = $client->get("/devices/?query=%7B%22VirtualParameters.unifiedMAC%22%3A%22" . $mac . "%22%7D");
    $response = $request->getBody();
    $json = json_decode($response, true);
    $events['_lastBoot'] = $json[0]['_lastBoot'];
    $events['_lastInform'] = $json[0]['_lastInform'];
    $events['_registered'] = $json[0]['_registered'];
    $events['_uptime'] = time() - strtotime($events['_lastBoot']);
    $tags = $json[0]['_tags'];
    $device_id = $json[0]['_id'];
    //$UploadDiagnostics = $device_info[0]['InternetGatewayDevice']['UploadDiagnostics'];
    //$DownloadDiagnostics = $device_info[0]['InternetGatewayDevice']['DownloadDiagnostics'];
    //$DownloadTest = speedTest($DownloadDiagnostics['TestBytesReceived']['_value'], new DateTime($DownloadDiagnostics['BOMTime']['_value']), new DateTime($DownloadDiagnostics['EOMTime']['_value']), 'Download');
    //$UploadTest = speedTest($DownloadDiagnostics['TotalBytesSent']['_value'], new DateTime($UploadDiagnostics['BOMTime']['_value']), new DateTime($UploadDiagnostics['EOMTime']['_value']), 'Upload');
    $wanInfo[1] = $json[0]['InternetGatewayDevice']['WANDevice'][1]['WANConnectionDevice'][1]['WANIPConnection'][1];
    $wanStats[1] = $json[0]['InternetGatewayDevice']['WANDevice'][1]['WANCommonInterfaceConfig'];

    $SMARTY->assign('device_id', $device_id);
    $SMARTY->assign('device_info', $json);
    $SMARTY->assign('WANDeviceInfo', $wanInfo);
    $SMARTY->assign('WANDeviceStats', $wanStats);
    //$SMARTY->assign('UploadDiagnostics', $UploadDiagnostics);
    //$SMARTY->assign('DownloadDiagnostics', $DownloadDiagnostics);
    //$SMARTY->assign('DownloadTest', $DownloadTest);
    //$SMARTY->assign('UploadTest', $UploadTest);
    $SMARTY->assign('events', $events);
    $SMARTY->assign('tags', $tags);
    $SMARTY->assign('mac', $mac);
    $SMARTY->display('module:device_info.html');
}

function module_pending()
{
    global $SMARTY;
    $device_id = $_POST['device_id'];
    $task_id = $_POST['task_id'];
    $mac = strtolower($_POST['mac']);

    if ($task_id) {
        deleteTask($task_id);
    }

    $pending_tasks = getPendingTasks($device_id);

    if (!empty($pending_tasks)) {
        $SMARTY->assign('pending_tasks', $pending_tasks);
        $SMARTY->assign('mac', $mac);

        $SMARTY->display('module:pending_tasks.html');
    } else {
        header('Location: ?m=mydevicesettings');
    }
}

function summon($device_id)
{
    if ($device_id) {
        refreshObject($device_id, '*');
    }
}

function module_main()
{
    global $LMS, $SESSION, $SMARTY, $client;
    $count_devices = 0;

    if ($_POST['summon']) {
        summon($_POST['device_id']);
    }
    unset($_POST);
    $usernodes = $LMS->GetCustomerNodes($SESSION->id);
    //echo '<pre>';print_r($usernodes);echo '</pre>';


    $isDataAcsExists = null;
    foreach ($usernodes as $node) {
        $mac = strtolower($node['mac']);
        $response = $client->get("/devices/?query=%7B%22VirtualParameters.unifiedMAC%22%3A%22" . $mac . "%22%7D");
        //echo '<pre>';print_r($response);echo '</pre>';

        $json = $response->getBody();
        $result = json_decode($json, true);
        //echo '<pre>';print_r($result);echo '</pre>';

        $isDataAcsExists = $result[0]['InternetGatewayDevice']['DeviceInfo']['SoftwareVersion'];
        if (is_countable($isDataAcsExists)) {
            $data_exists = count($isDataAcsExists) ? '1' : '0';
        }

        //echo '<pre>';print_r($data_exists);echo '</pre>';

        $device_id = $result[0]['_id'];
        if ($device_id)
            $count_devices++;
        $pending_tasks = getPendingTasks($device_id);
        $PeriodicInformInterval = $result[0]['InternetGatewayDevice']['ManagementServer']['PeriodicInformInterval']['_value'];
        if ($PeriodicInformInterval > 0) {
            $lastInform = strtotime($result[0]['_lastInform']);
            $currentTime = time();
            $nextInform = $currentTime - ($PeriodicInformInterval + $lastInform);  //seconds left
        }
        if ($nextInform > $PeriodicInformInterval) {
            $device_state = 'offline';
        } else {
            $device_state = 'online';
        }

        $projection = 'InternetGatewayDevice.IPPingDiagnostics.DiagnosticsState';
        $result = getProjectionFromDeviceID($device_id, $projection);
        $IPPingDiagnostics = $result[0]['InternetGatewayDevice']['IPPingDiagnostics']['DiagnosticsState'];

        $projection = 'InternetGatewayDevice.TraceRouteDiagnostics.DiagnosticsState';
        $result = getProjectionFromDeviceID($device_id, $projection);
        $TraceRouteDiagnostics = $result[0]['InternetGatewayDevice']['TraceRouteDiagnostics']['DiagnosticsState'];

        $projection = 'InternetGatewayDevice.DownloadDiagnostics.DiagnosticsState';
        $result = getProjectionFromDeviceID($device_id, $projection);
        $DownloadDiagnostics = $result[0]['InternetGatewayDevice']['DownloadDiagnostics']['DiagnosticsState'];

        $nodes[] = array(
            'device_id' => $device_id,
            'mac' => $mac,
            'name' => $node['name'],
            'ip' => $node['ip'],
            'location' => $node['location'],
            'pending_tasks' => $pending_tasks,
            'pending_tasks_count' => count($pending_tasks),
            'next_inform' => $nextInform,
            'progress_position' => round((abs($nextInform) / 300) * 100),
            'device_state' => $device_state,
            'ping_state' => $IPPingDiagnostics['_value'],
            'traceroute_state' => $TraceRouteDiagnostics['_value'],
            'download_state' => $DownloadDiagnostics['_value'],
            'data_exists' => $data_exists,
        );
    }
    //echo '<pre>';print_r($nodes);echo '</pre>';
    $SMARTY->assign('nodes', $nodes);
    //$SMARTY->assign('id', $id);
    $SMARTY->assign('count_devices', $count_devices);
    $SMARTY->display('module:devices.html');
}
