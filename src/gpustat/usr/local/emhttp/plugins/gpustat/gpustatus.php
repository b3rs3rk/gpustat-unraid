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
            die("GPU vendor set to NVIDIA, but nvidia-smi was not found.");
        }
        break;
    default:
        die("Could not determine GPU vendor.");
}

$data = detectParser($settings['VENDOR'], $stdout);

// Page file JavaScript expects a JSON encoded string
if (is_array($data)) {
    $json = json_encode($data);
    header('Content-Type: application/json');
    header('Content-Length: ' . strlen($json));
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

        $gpu = $data->gpu;
        $retval = [
            'vendor'    => 'NVIDIA',
            'name'      => 'Graphics Card',
            'clock'     => 'N/A',
            'memclock' => 'N/A',
            'util'      => 'N/A',
            'memutil'   => 'N/A',
            'encutil'   => 'N/A',
            'decutil'   => 'N/A',
            'temp'      => 'N/A',
            'tempmax'   => 'N/A',
            'fan'       => 'N/A',
            'perfstate' => 'N/A',
            'throttled' => 'N/A',
            'thrtlrsn'  => '',
            'power'     => 'N/A',
            'powermax'  => 'N/A',
            'sessions'  =>  0,
        ];

        if (isset($gpu->product_name)) {
            $retval['name'] = (string) $gpu->product_name;
        }
        if (isset($gpu->utilization)) {
            if (isset($gpu->utilization->gpu_util)) {
                $retval['util'] = (string) strip_spaces($gpu->utilization->gpu_util);
            }
            if (isset($gpu->utilization->memory_util)) {
                $retval['memutil'] = (string) strip_spaces($gpu->utilization->memory_util);
            }
            if (isset($gpu->utilization->encoder_util)) {
                $retval['encutil'] = (string) strip_spaces($gpu->utilization->encoder_util);
            }
            if (isset($gpu->utilization->decoder_util)) {
                $retval['decutil'] = (string) strip_spaces($gpu->utilization->decoder_util);
            }
        }
        if (isset($gpu->temperature)) {
            if (isset($gpu->temperature->gpu_temp)) {
                $retval['temp'] = (string) strip_spaces($gpu->temperature->gpu_temp);
            }
            if (isset($gpu->temperature->gpu_temp_max_threshold)) {
                $retval['tempmax'] = (string) strip_spaces($gpu->temperature->gpu_temp_max_threshold);
            }
        }
        if (isset($gpu->fan_speed)) {
            $retval['fan'] = (string) strip_spaces($gpu->fan_speed);
        }
        if (isset($gpu->performance_state)) {
            $retval['perfstate'] = (string) strip_spaces($gpu->performance_state);
        }
        if (isset($gpu->clocks_throttle_reasons)) {
            $retval['throttled'] = 'No';
            foreach ($gpu->clocks_throttle_reasons->children() AS $reason => $throttle) {
                if ($throttle == 'Active') {
                    $retval['throttled'] = 'Yes';
                    $retval['thrtlrsn'] = ' (' . str_replace('clocks_throttle_reason_', '', $reason) . ')';
                    break;
                }
            }
        }
        if (isset($gpu->power_readings)) {
            if (isset($gpu->power_readings->power_draw)) {
                $retval['power'] = (string) strip_spaces($gpu->power_readings->power_draw);
            }
            if (isset($gpu->power_readings->power_limit)) {
                $retval['powermax'] = (string) str_replace('.00 ', '', $gpu->power_readings->power_limit);
            }
        }
        if (isset($gpu->clocks)) {
            if (isset($gpu->clocks->graphics_clock)) {
                $retval['clock'] = (string) str_replace(' MHz', '', $gpu->clocks->graphics_clock);
            }
            if (isset($gpu->clocks->mem_clock)) {
                $retval['memclock'] = (string) $gpu->clocks->mem_clock;
            }
        }
        // For some reason, encoder_sessions->session_count is not reliable on my install, better to count processes
        if (isset($gpu->processes) && isset($gpu->processes->process_info)) {
            $retval['sessions'] = (int) count($gpu->processes->process_info);
        }
    }

    return $retval;
}

/**
 * Strips all spaces from a provided string
 *
 * @param string $text
 * @return string|string[]
 */
function strip_spaces(string $text = '') {

    return str_replace(' ', '', $text);
}
