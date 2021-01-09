<?php

namespace gpustat\lib;

use SimpleXMLElement;

/**
 * Class Nvidia
 * @package gpustat\lib
 */
class Nvidia extends Main
{
    const CMD_UTILITY = 'nvidia-smi';
    const INVENTORY_PARAM = '-L';
    const INVENTORY_REGEX = '/GPU\s(?P<id>\d):\s(?P<model>.*)\s\(UUID:\s(?P<guid>GPU-[0-9a-f-]+)\)/i';
    const STATISTICS_PARAM = '-q -x -g %s 2>&1';

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
    public function getInventory(): array
    {
        $result = [];

        if ($this->cmdexists) {
            $this->runCommand(self::CMD_UTILITY, self::INVENTORY_PARAM, false);
            if (!empty($this->stdout) && strlen($this->stdout) > 0) {
                $this->parseInventory(self::INVENTORY_REGEX);
                if (!empty($this->inventory)) {
                    $result = $this->inventory;
                }
            }
        }

        return $result;
    }

    /**
     * Retrieves NVIDIA card statistics
     */
    public function getStatistics()
    {
        if ($this->cmdexists) {
            //Command invokes nvidia-smi in query all mode with XML return
            $this->stdout = shell_exec(self::CMD_UTILITY . ES . sprintf(self::STATISTICS_PARAM, $this->settings['GPUID']));
            if (!empty($this->stdout) && strlen($this->stdout) > 0) {
                $this->parseStatistics();
            } else {
                new Error(Error::VENDOR_DATA_NOT_RETURNED);
            }
        } else {
            new Error(Error::VENDOR_UTILITY_NOT_FOUND);
        }
    }

    /**
     * Loads stdout into SimpleXMLObject then retrieves and returns specific definitions in an array
     */
    private function parseStatistics()
    {

        $data = @simplexml_load_string($this->stdout);
        $this->stdout = '';

        if ($data instanceof SimpleXMLElement && !empty($data->gpu)) {

            $data = $data->gpu;
            $this->pageData += [
                'vendor'        => 'NVIDIA',
                'name'          => 'Graphics Card',
            ];

            if (isset($data->product_name)) {
                $this->pageData['name'] = (string) $data->product_name;
            }
            if (isset($data->utilization)) {
                if (isset($data->utilization->gpu_util)) {
                    $this->pageData['util'] = (string) $this->stripSpaces($data->utilization->gpu_util);
                }
                if (isset($data->fb_memory_usage->used) && isset($data->fb_memory_usage->total)) {
                    $this->pageData['memtotal'] = (string) str_replace(' MiB', '', $data->fb_memory_usage->total);
                    $this->pageData['memused'] = (string) str_replace(' MiB', '', $data->fb_memory_usage->used);
                    $this->pageData['memutil'] = round($this->pageData['memused'] / $this->pageData['memtotal'] * 100) . "%";
                }
                // If card doesn't support utilization property, fall back to computation for memory usage
                if ($this->pageData['memutil'] == "N/A" && isset($data->fb_memory_usage->total, $data->fb_memory_usage->used)) {
                    $memTotal = $this->stripText(' MiB', $data->fb_memory_usage->total);
                    $memUsed = $this->stripText(' MiB', $data->fb_memory_usage->used);
                    if ($memUsed !== "N/A" && $memTotal !== "N/A" && $memUsed <= $memTotal) {
                        $this->pageData['memutil'] = $this->roundFloat(((int) $memUsed / (int) $memTotal) * 100, -1) . '%';
                    }
                    unset($memTotal, $memUsed);
                }
            }
            if (isset($data->temperature)) {
                if (isset($data->temperature->gpu_temp)) {
                    $this->pageData['temp'] = (string) str_replace('C', '°C', $data->temperature->gpu_temp);
                }
                if (isset($data->temperature->gpu_temp_max_threshold)) {
                    $this->pageData['tempmax'] = (string) str_replace('C', '°C', $data->temperature->gpu_temp_max_threshold);
                }
                if ($this->settings['TEMPFORMAT'] == 'F') {
                    foreach (['temp', 'tempmax'] AS $key) {
                        $this->pageData[$key] = $this->convertCelsius((int) $this->stripText('C', $this->pageData[$key])) . 'F';
                    }
                }
            }
            if (isset($data->fan_speed)) {
                $this->pageData['fan'] = (string) $this->stripSpaces($data->fan_speed);
            }
            if (isset($data->performance_state)) {
                $this->pageData['perfstate'] = (string) $this->stripSpaces($data->performance_state);
            }
            if (isset($data->clocks_throttle_reasons)) {
                $this->pageData['throttled'] = 'No';
                foreach ($data->clocks_throttle_reasons->children() AS $reason => $throttle) {
                    if ($throttle == 'Active') {
                        $this->pageData['throttled'] = 'Yes';
                        $this->pageData['thrtlrsn'] = ' (' . $this->stripText('clocks_throttle_reason_', $reason) . ')';
                        break;
                    }
                }
            }
            if (isset($data->power_readings)) {
                if (isset($data->power_readings->power_draw)) {
                    $this->pageData['power'] = (string) str_replace(' W', '', $data->power_readings->power_draw);
                }
                if (isset($data->power_readings->power_limit)) {
                    $this->pageData['powermax'] = (string) str_replace('.00 W', '', $data->power_readings->power_limit);
                }
            }
            if (isset($data->clocks)) {
                if (isset($data->clocks->graphics_clock) && isset($data->max_clocks->graphics_clock)) {
                    $this->pageData['clock'] = (string) str_replace(' MHz', '', $data->clocks->graphics_clock);
                    $this->pageData['clockmax'] = (string) str_replace(' MHz', '', $data->max_clocks->graphics_clock);
                }
                if (isset($data->clocks->mem_clock) && isset($data->max_clocks->mem_clock)) {
                    $this->pageData['memclock'] = (string) str_replace(' MHz', '', $data->clocks->mem_clock);
                    $this->pageData['memclockmax'] = (string) str_replace(' MHz', '', $data->max_clocks->mem_clock);
                }
            }
            // For some reason, encoder_sessions->session_count is not reliable on my install, better to count processes
            if (isset($data->processes) && isset($data->processes->process_info)) {
                $this->pageData['sessions'] = (int) count($data->processes->process_info);
            }
            if (isset($data->pci)) {
                if (isset($data->pci->rx_util)) {
                    $this->pageData['rxutil'] = (string) $this->stripText(' KB/s', ($this->roundFloat($data->pci->rx_util / 1000)));
                }
                if (isset($data->pci->tx_util)) {
                    $this->pageData['txutil'] = (string) $this->stripText(' KB/s', ($this->roundFloat($data->pci->tx_util / 1000)));
                }
            }
        } else {
            new Error(Error::VENDOR_DATA_BAD_PARSE);
        }
        $this->echoJson();
    }
}
