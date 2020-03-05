<?php

$settingsFile = '/boot/config/plugins/gpustat/gpustat.cfg';
$which = 'which ';

if (file_exists($settingsFile)) {
    $settings = parse_ini_file($settingsFile);
} else {
    $settings["TYPE"] = "nvidia";
}

switch ($settings['TYPE']) {
    case 'nvidia':
        if (shell_exec($which . 'nvidia-smi 2>&1') !== '') {
            $stdout = shell_exec('nvidia-smi -q -x 2>&1');
        } else {
            die("GPU Type set to Nvidia, but nvidia-smi was not found.");
        }
        break;
    default:
        die("Could not determine GPU type.");
}

parseStdout($settings['TYPE'], $stdout);

function parseStdout (string $type = '', string $stdout = '') {

    if (!empty($stdout) && strlen($stdout) > 0) {
        switch ($type) {
            case 'nvidia':
                parseNvidia($stdout);
                break;
            default:
                die("Could not determine GPU type.");
        }
    } else {
        die("No data returned from statistics command.");
    }
}

function parseNvidia (string $stdout = '') {

    $data = @simplexml_load_string($stdout);
    $retval = array();

    if (!empty($data->gpu)) {

        $gpuData = $data->gpu;
        //var_dump(count($data));
        //print_r($data->gpu);
        if (isset($gpuData->utilization)) {
            $retval['gpu_util'] =  isset($gpuData->utilization->gpu_util) ? (string) $gpuData->utilization->gpu_util : '-1%';
            $retval['memory_util'] = isset($gpuData->utilization->memory_util) ? (string) $gpuData->utilization->memory_util : '-1%';
        }
        if (isset($gpuData->temperature)) {
            $retval['gpu_temp'] = isset($gpuData->temperature->gpu_temp) ? (string) $gpuData->temperature->gpu_temp : '-1C';
        }
        $retval['fan_speed'] = isset($gpuData->fan_speed) ? (string) $gpuData->fan_speed : '-1%';
        if (isset($gpuData->power_readings)) {
            $retval['power_draw'] = isset($gpuData->power_readings->power_draw) ? (string) $gpuData->power_readings->power_draw : '-1.0W';
        }
        if (isset($gpuData->encoder_stats)) {
            $retval['active_sessions'] = isset($gpuData->encoder_stats->session_count) ? (int) $gpuData->encoder_stats->session_count : -1;
        }
    }
}