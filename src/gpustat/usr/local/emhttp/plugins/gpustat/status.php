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
        //var_dump(count($data));
        //print_r($data->gpu);
        $retval['gpu_util'] = (string) $data->gpu->utilization->gpu_util;
        $retval['memory_util'] = (string) $data->gpu->utilization->memory_util;
        $retval['gpu_temp'] = (string) $data->gpu->temperature->gpu_temp;
        $retval['fan_speed'] = (string) $data->gpu->fan_speed;
        $retval['power_draw'] = (string) $data->gpu->power_readings->power_draw;
    }
    var_dump($retval);
}