<?php

const SETTINGS_FILE_PATH = '/boot/config/plugins/gpustat/gpustat.cfg';
const COMMAND_EXISTS_CHECKER = 'which ';
const NVIDIA_STATISTICS_COMMAND = 'nvidia-smi -q -x -i %s 2>&1';
const NVIDIA_INVENTORY_COMMAND = 'nvidia-smi -L';
const NVIDIA_INVENTORY_REGEX = '/GPU\s(?P<id>\d):\s(?P<model>.*)\s\(UUID:\s(?P<guid>GPU-[0-9a-f-]+)\)/i';

if (file_exists(SETTINGS_FILE_PATH)) {
    $gpu_settings = parse_ini_file(SETTINGS_FILE_PATH);
} else {
    $gpu_settings['VENDOR'] = 'nvidia';
    $gpu_settings['TEMPFORMAT'] = 'F';
    $gpu_settings['GPUID'] = '0';
}

switch ($gpu_settings['VENDOR']) {
    case 'nvidia':
        //Needed to be able to run the code on Windows for code testing and Windows uses where instead of which
        if (!is_null(shell_exec(COMMAND_EXISTS_CHECKER . 'nvidia-smi'))) {
            // If Settings page includes, this should be true
            if (isset($gpu_inventory) && $gpu_inventory) {
                $gpu_stdout = shell_exec(NVIDIA_INVENTORY_COMMAND);
                $gpu_data = parseNvidiaInventory($gpu_stdout);
            } else {
                //Command invokes nvidia-smi in query all mode with XML return
                $gpu_stdout = shell_exec(sprintf(NVIDIA_STATISTICS_COMMAND, $gpu_settings['GPUID']));
            }
        } else {
            die('GPU vendor set to NVIDIA, but nvidia-smi was not found.');
        }
        break;
    default:
        die('Could not determine GPU vendor.');
}

if (!isset($gpu_data)) {
    $gpu_data = detectParser($gpu_settings, $gpu_stdout);
    // Page file JavaScript expects a JSON encoded string
    if (is_array($gpu_data)) {
        $gpu_json = json_encode($gpu_data);
        header('Content-Type: application/json');
        header('Content-Length: ' . strlen($gpu_json));
        echo $gpu_json;
    } else {
        die('Data not in array format.');
    }
}

/**
 * Detects correct parser and directs stdout to correct function
 *
 * @param array $settings
 * @param string $stdout
 * @return array
 */
function detectParser (array $settings = [], string $stdout = '') {

    if (!empty($stdout) && strlen($stdout) > 0) {
        switch ($settings['VENDOR']) {
            case 'nvidia':
                $data = parseNvidia($settings, $stdout);
                break;
            default:
                die('Could not determine GPU vendor.');
        }
    } else {
        die('No data returned from statistics command.');
    }

    return $data;
}

/**
 * Parses output of nvidia-sml -L and returns an array of matches with named groups
 *
 * @param string $stdout
 * @return mixed
 */
function parseNvidiaInventory(string $stdout = '') {
    preg_match_all(NVIDIA_INVENTORY_REGEX, $stdout, $matches, PREG_SET_ORDER);

    return $matches;
}

/**
 * Loads stdout into SimpleXMLObject then retrieves and returns specific definitions in an array
 *
 * @param array $settings
 * @param string $stdout
 * @return array
 */
function parseNvidia (array $settings = [], string $stdout = '') {

    $data = @simplexml_load_string($stdout);
    $retval = array();

    if (!empty($data->gpu)) {

        $gpu = $data->gpu;
        $retval = [
            'vendor'    => 'NVIDIA',
            'name'      => 'Graphics Card',
            'clock'     => 'N/A',
            'memclock'  => 'N/A',
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
            if ($settings['TEMPFORMAT'] == 'F') {
                foreach (['temp', 'tempmax'] AS $key) {
                    $retval[$key] = celsius_to_fahrenheit((int) str_replace('C', '', $retval[$key])) . 'F';
                }
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

/**
 * Converts Celsius to Fahrenheit
 *
 * @param int $temp
 * @return false|float
 */
function celsius_to_fahrenheit(int $temp = 0)
{
    $fahrenheit = $temp*(9/5)+32;

    return round($fahrenheit, -1, PHP_ROUND_HALF_UP);
}
