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
        if (!is_null(shell_exec($which . 'nvidia-smi'))) {
            $stdout = shell_exec('nvidia-smi -q -x 2>&1');
        } else {
            die("GPU vendor set to nVidia, but nvidia-smi was not found.");
        }
        break;
    default:
        die("Could not determine GPU vendor.");
}

$data = parseStdout($settings['VENDOR'], $stdout);

if (is_array($data)) {
    header('Content-Type: application/json');
    echo json_encode($data);
} else {
    die("Data not in array format.");
}

function parseStdout (string $vendor = '', string $stdout = '') {

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
        if (isset($gpuData->processes)) {
            $retval['encoders'] =
                isset($gpuData->processes->process_info)
                    ? (int) count($gpuData->processes->process_info)
                    : -1;
        }
        $retval['vendor'] = 'nVidia';
    }

    return $retval;
}