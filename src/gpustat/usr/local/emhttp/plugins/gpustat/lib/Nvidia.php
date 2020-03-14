<?php

namespace gpustat\lib;

/**
 * Class Nvidia
 * @package gpustat\lib
 */
class Nvidia extends Main
{
    const CMD_UTILITY = 'nvidia-smi';
    const INVENTORY_PARAM = '-L';
    const INVENTORY_REGEX = '/GPU\s(?P<id>\d):\s(?P<model>.*)\s\(UUID:\s(?P<guid>GPU-[0-9a-f-]+)\)/i';
    const STATISTICS_PARAM = '-q -x -i %s 2>&1';

    /**
     * Nvidia constructor.
     * @param array $settings
     */
    public function __construct(array $settings = [])
    {
        $settings += ['cmd' => self::CMD_UTILITY];
        parent::__construct($settings);
    }

    /**
     * Retrieves NVIDIA card inventory and parses into an array
     *
     * @return array
     */
    public function getInventory ()
    {
        $this->stdout = shell_exec(self::CMD_UTILITY . self::ES . self::INVENTORY_PARAM);
        if (!empty($this->stdout) && strlen($this->stdout) > 0) {
            preg_match_all(self::INVENTORY_REGEX, $this->stdout, $this->inventory, PREG_SET_ORDER);
        } else {
            new Error(Error::VENDOR_DATA_NOT_RETURNED);
        }

        return $this->inventory;
    }

    /**
     * Retrieves NVIDIA card statistics
     */
    public function getStatistics()
    {
        //Command invokes nvidia-smi in query all mode with XML return
        $this->stdout = shell_exec(self::CMD_UTILITY . self::ES . sprintf(self::STATISTICS_PARAM, $this->settings['GPUID']));
        if (!empty($this->stdout) && strlen($this->stdout) > 0) {
            $this->parseStatistics();
        } else {
            new Error(Error::VENDOR_DATA_NOT_RETURNED);
        }
    }

    /**
     * Loads stdout into SimpleXMLObject then retrieves and returns specific definitions in an array
     */
    private function parseStatistics () {

        $data = @simplexml_load_string($this->stdout);
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
                    $retval['util'] = (string) $this->stripSpaces($gpu->utilization->gpu_util);
                }
                if (isset($gpu->utilization->memory_util)) {
                    $retval['memutil'] = (string) $this->stripSpaces($gpu->utilization->memory_util);
                }
                if (isset($gpu->utilization->encoder_util)) {
                    $retval['encutil'] = (string) $this->stripSpaces($gpu->utilization->encoder_util);
                }
                if (isset($gpu->utilization->decoder_util)) {
                    $retval['decutil'] = (string) $this->stripSpaces($gpu->utilization->decoder_util);
                }
            }
            if (isset($gpu->temperature)) {
                if (isset($gpu->temperature->gpu_temp)) {
                    $retval['temp'] = (string) $this->stripSpaces($gpu->temperature->gpu_temp);
                }
                if (isset($gpu->temperature->gpu_temp_max_threshold)) {
                    $retval['tempmax'] = (string) $this->stripSpaces($gpu->temperature->gpu_temp_max_threshold);
                }
                if ($this->settings['TEMPFORMAT'] == 'F') {
                    foreach (['temp', 'tempmax'] AS $key) {
                        $retval[$key] = $this->convertCelsius((int) str_replace('C', '', $retval[$key])) . 'F';
                    }
                }
            }
            if (isset($gpu->fan_speed)) {
                $retval['fan'] = (string) $this->stripSpaces($gpu->fan_speed);
            }
            if (isset($gpu->performance_state)) {
                $retval['perfstate'] = (string) $this->stripSpaces($gpu->performance_state);
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
                    $retval['power'] = (string) $this->stripSpaces($gpu->power_readings->power_draw);
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

        $this->echoJson($retval);
    }
}
