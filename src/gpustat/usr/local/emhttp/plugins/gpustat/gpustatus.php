<?php

$settingsFile = '/boot/config/plugins/gpustat/gpustat.cfg';
$which = 'which ';

if (file_exists($settingsFile)) {
    $settings = parse_ini_file($settingsFile);
} else {
    $settings["VENDOR"] = "nvidia";
}

switch ($settings['VENDOR']) {
    case 'nvidia':
        //Needed to be able to run the code on Windows for code testing and Windows uses where instead of which
        if (!is_null(shell_exec($which . 'nvidia-smi'))) {
            //Command invokes nvidia-smi in query all mode with XML return
            $stdout = shell_exec('nvidia-smi -q -x 2>&1');
        } else {
            die("GPU vendor set to nVidia, but nvidia-smi was not found.");
        }
        break;
    default:
        die("Could not determine GPU vendor.");
}

$data = detectParser($settings['VENDOR'], $stdout);

// Page file JavaScript expects a JSON encoded string
if (is_array($data)) {
    header('Content-Type: application/json');
    $json = json_encode($data);
    $jsonlen = strlen($json);
    header('Content-Length: ' . $jsonlen);
    echo $json;
} else {
    die("Data not in array format.");
}

/**
 * Detects correct parser and directs stdout to correct function
 *
 * @param string $vendor
 * @param string $stdout
 * @return array
 */
function detectParser (string $vendor = '', string $stdout = '') {

    if (!empty($stdout) && strlen($stdout) > 0) {
        switch ($vendor) {
            case 'nvidia':
                $data = parseNvidia($stdout);
                break;
            default:
                die("Could not determine GPU vendor.");
        }
    } else {
        die("No data returned from statistics command.");
    }

    return $data;
}

/**
 * Loads stdout into SimpleXMLObject then retrieves and returns specific definitions in an array
 *
 * @param string $stdout
 * @return array
 */
function parseNvidia (string $stdout = '') {

    $data = @simplexml_load_string($stdout);
    $retval = array();

    if (!empty($data->gpu)) {

        $gpuData = $data->gpu;

        $retval['name'] =
            isset($gpuData->product_name)
                ? (string) $gpuData->product_name
                : 'Graphics Card';
        if (isset($gpuData->utilization)) {
            $retval['util'] =
                isset($gpuData->utilization->gpu_util)
                    ? (string) str_replace(' ', '', $gpuData->utilization->gpu_util)
                    : '-1%';
            $retval['memutil'] =
                isset($gpuData->utilization->memory_util)
                    ? (string) str_replace(' ', '', $gpuData->utilization->memory_util)
                    : '-1%';
        }
        if (isset($gpuData->temperature)) {
            $retval['temp'] =
                isset($gpuData->temperature->gpu_temp)
                    ? (string) str_replace(' ', '', $gpuData->temperature->gpu_temp)
                    : '-1C';
        }
        $retval['fan'] =
            isset($gpuData->fan_speed)
                ? (string) str_replace(' ', '', $gpuData->fan_speed)
                : '-1%';
        if (isset($gpuData->power_readings)) {
            $retval['power'] =
                isset($gpuData->power_readings->power_draw)
                    ? (string) str_replace(' ', '', $gpuData->power_readings->power_draw)
                    : '-1.0W';
        }
        // For some reason, encoder_sessions->session_count is not reliable on my install, better to count processes
        if (isset($gpuData->processes)) {
            $retval['encoders'] =
                isset($gpuData->processes->process_info)
                    ? (int) count($gpuData->processes->process_info)
                    : 0;
        }
        $retval['vendor'] = 'nVidia';
    }

    return $retval;
}