<?php

/*
  MIT License

  Copyright (c) 2020-2021 b3rs3rk

  Permission is hereby granted, free of charge, to any person obtaining a copy
  of this software and associated documentation files (the "Software"), to deal
  in the Software without restriction, including without limitation the rights
  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
  copies of the Software, and to permit persons to whom the Software is
  furnished to do so, subject to the following conditions:

  The above copyright notice and this permission notice shall be included in all
  copies or substantial portions of the Software.

  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
  SOFTWARE.
*/

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
    const SUPPORTED_APPS = [ // Order here is important because some apps use the same binaries -- order should be more specific to less
        'plex'      => ['Plex Transcoder'],
        'jelly'     => ['jellyfin-ffmpeg'],
        'handbrake' => ['/usr/bin/HandBrakeCLI'],
        //'emby'      => ['ffmpeg'],
        'tdarr'     => ['ffmpeg', 'HandbrakeCLI'],
        'unmanic'   => ['ffmpeg'],
        'dizquetv'  => ['ffmpeg'],
        'deepstack' => ['python3'],
        'nsfminer'  => ['/opt/nsfminer/nsfminer'],
        'shinobipro' => ['/opt/shinobi/plugins/face/shinobi-face.js'],        
    ];

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
     * Iterates supported applications and their respective commands to match against processes using GPU hardware
     *
     * @param SimpleXMLElement $process
     */
    private function detectApplication (SimpleXMLElement $process)
    {
        foreach (self::SUPPORTED_APPS as $app => $commands) {
            foreach ($commands as $command) {
                if (strpos($process->process_name, $command) !== false) {
                    // For Handbrake/ffmpeg: arguments tell us which application called it
                    if (in_array($command, ['ffmpeg', 'HandbrakeCLI', 'python3'])) {
                        if (isset($process->pid)) {
                            $pid_info = $this->getFullCommand((int) $process->pid);
                            if (!empty($pid_info) && strlen($pid_info) > 0) {
                                if ($command === 'python3') {
                                    // Deepstack doesn't have any signifier in the full command output
                                    if (strpos($pid_info, '/app/intelligencelayer/shared') === false) {
                                        continue 2;
                                    }
                                } elseif (strpos($pid_info, $app) === false) {
                                    // We didn't match the application name in the arguments, no match
                                    continue 2;
                                }
                            }
                        }
                    }
                    $this->pageData[$app . 'using'] = true;
                    $this->pageData[$app . 'mem'] += (int)$this->stripText(' MiB', $process->used_memory);
                    $this->pageData[$app . 'count']++;
                    // If we match a more specific command/app to a process, continue on to the next process
                    break 2;
                }
            }
        }
    }

    /**
     * Parses PCI Bus Utilization data
     *
     * @param SimpleXMLElement $pci
     */
    private function getBusUtilization(SimpleXMLElement $pci)
    {
        if (isset($pci)) {
            if (isset($pci->rx_util, $pci->tx_util)) {
                // Not all cards support PCI RX/TX Measurements
                if ((string) $pci->rx_util !== 'N/A') {
                    $this->pageData['rxutil'] = (string) $this->roundFloat($this->stripText(' KB/s', $pci->rx_util) / 1000);
                }
                if ((string) $pci->tx_util !== 'N/A') {
                    $this->pageData['txutil'] = (string) $this->roundFloat($this->stripText(' KB/s', $pci->tx_util) / 1000);
                }
            }
            if (
            isset(
                $pci->pci_gpu_link_info->pcie_gen->current_link_gen,
                $pci->pci_gpu_link_info->pcie_gen->max_link_gen,
                $pci->pci_gpu_link_info->link_widths->current_link_width,
                $pci->pci_gpu_link_info->link_widths->max_link_width
            )
            ) {
                $this->pageData['pciegen'] = $generation = (int) $pci->pci_gpu_link_info->pcie_gen->current_link_gen;
                $this->pageData['pciewidth'] = $width = (int) $this->stripText('x', $pci->pci_gpu_link_info->link_widths->current_link_width);
                // @ 16x Lanes: Gen 1 = 4000, 2 = 8000, 3 = 16000 MB/s -- Slider bars won't be that active with most workloads
                $this->pageData['pciemax'] = pow(2, $generation - 1) * 250 * $width;
                $this->pageData['pciegenmax'] = (int) $pci->pci_gpu_link_info->pcie_gen->max_link_gen;
                $this->pageData['pciewidthmax'] = (int) $this->stripText('x', $pci->pci_gpu_link_info->link_widths->max_link_width);
            }
        }
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
     * Parses product name and stores in page data
     *
     * @param string $name
     */
    private function getProductName (string $name)
    {
        // Some product names include NVIDIA and we already set it to be Vendor + Product Name
        if (stripos($name, 'NVIDIA') !== false) {
            $name = trim($this->stripText('NVIDIA', $name));
        }
        // Some product names are too long, like TITAN Xp COLLECTORS EDITION and need to be shortened for fitment
        if (strlen($name) > 20 && str_word_count($name) > 2) {
            $words = explode(" ", $name);
            $this->pageData['name'] = sprintf("%0s %1s", $words[0], $words[1]);
        } else {
            $this->pageData['name'] = $name;
        }
    }

    /**
     * Parses sensor data for environmental metrics
     *
     * @param SimpleXMLElement $data
     */
    private function getSensorData (SimpleXMLElement $data)
    {
        if ($this->settings['DISPTEMP']) {
            if (isset($data->temperature)) {
                if (isset($data->temperature->gpu_temp)) {
                    $this->pageData['temp'] = (string) str_replace('C', '°C', $data->temperature->gpu_temp);
                }
                if (isset($data->temperature->gpu_temp_max_threshold)) {
                    $this->pageData['tempmax'] = (string) str_replace('C', '°C', $data->temperature->gpu_temp_max_threshold);
                }
                if ($this->settings['TEMPFORMAT'] == 'F') {
                    foreach (['temp', 'tempmax'] as $key) {
                        $this->pageData[$key] = $this->convertCelsius((int) $this->stripText('C', $this->pageData[$key])) . 'F';
                    }
                }
            }
        }
        if ($this->settings['DISPFAN']) {
            if (isset($data->fan_speed)) {
                $this->pageData['fan'] = $this->stripSpaces($data->fan_speed);
            }
        }
        if ($this->settings['DISPPWRSTATE']) {
            if (isset($data->performance_state)) {
                $this->pageData['perfstate'] = $this->stripSpaces($data->performance_state);
            }
        }
        if ($this->settings['DISPTHROTTLE']) {
            if (isset($data->clocks_throttle_reasons)) {
                $this->pageData['throttled'] = 'No';
                foreach ($data->clocks_throttle_reasons->children() as $reason => $throttle) {
                    if ($throttle == 'Active') {
                        $this->pageData['throttled'] = 'Yes';
                        $this->pageData['thrtlrsn'] = ' (' . $this->stripText('clocks_throttle_reason_', $reason) . ')';
                        break;
                    }
                }
            }
        }
        if ($this->settings['DISPPWRDRAW']) {
            if (isset($data->power_readings)) {
                if (isset($data->power_readings->power_draw)) {
                    $this->pageData['power'] = (float) $this->stripText(' W', $data->power_readings->power_draw);
                    $this->pageData['power'] = $this->roundFloat($this->pageData['power']) . 'W';
                }
                if (isset($data->power_readings->power_limit)) {
                    $this->pageData['powermax'] = (string) $this->stripText('.00 W', $data->power_readings->power_limit);
                }
            }
        }
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
                $this->pageData['error'][] = Error::get(Error::VENDOR_DATA_NOT_RETURNED);
            }
        } else {
            $this->pageData['error'][] = Error::get(Error::VENDOR_UTILITY_NOT_FOUND);
        }
    }

    /**
     * Parses hardware utilization data
     *
     * @param SimpleXMLElement $data
     */
    private function getUtilization(SimpleXMLElement $data)
    {
        if (isset($data->utilization)) {
            if (isset($data->utilization->gpu_util)) {
                $this->pageData['util'] = $this->stripSpaces($data->utilization->gpu_util);
            }
            if ($this->settings['DISPENCDEC']) {
                if (isset($data->utilization->encoder_util)) {
                    $this->pageData['encutil'] = $this->stripSpaces($data->utilization->encoder_util);
                }
                if (isset($data->utilization->decoder_util)) {
                    $this->pageData['decutil'] = $this->stripSpaces($data->utilization->decoder_util);
                }
            }
        }
        if ($this->settings['DISPMEMUTIL']) {
            if (isset($data->fb_memory_usage->used, $data->fb_memory_usage->total)) {
                $this->pageData['memtotal'] = (string) $this->stripText(' MiB', $data->fb_memory_usage->total);
                $this->pageData['memused'] = (string) $this->stripText(' MiB', $data->fb_memory_usage->used);
                $this->pageData['memutil'] = round($this->pageData['memused'] / $this->pageData['memtotal'] * 100) . "%";
            }
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
                'clockmax'      => 'N/A',
                'memclockmax'   => 'N/A',
                'memtotal'      => 'N/A',
                'encutil'       => 'N/A',
                'decutil'       => 'N/A',
                'pciemax'       => 'N/A',
                'perfstate'     => 'N/A',
                'throttled'     => 'N/A',
                'thrtlrsn'      => '',
                'pciegen'       => 'N/A',
                'pciegenmax'    => 'N/A',
                'pciewidth'     => 'N/A',
                'pciewidthmax'  => 'N/A',
                'sessions'      => 0,
                'uuid'          => 'N/A',
            ];

            // Set App HW Usage Defaults
            foreach (self::SUPPORTED_APPS AS $app) {
                $this->pageData[$app . "using"] = false;
                $this->pageData[$app . "mem"] = 0;
                $this->pageData[$app . "count"] = 0;
            }
            if (isset($data->product_name)) {
                $this->getProductName($data->product_name);
            }
            if (isset($data->uuid)) {
                $this->pageData['uuid'] = (string) $data->uuid;
            } else {
                $this->pageData['uuid'] = $this->settings['GPUID'];
            }
            $this->getUtilization($data);
            $this->getSensorData($data);
            if ($this->settings['DISPCLOCKS']) {
                if (isset($data->clocks, $data->max_clocks)) {
                    if (isset($data->clocks->graphics_clock, $data->max_clocks->graphics_clock)) {
                        $this->pageData['clock'] = (string) $this->stripText(' MHz', $data->clocks->graphics_clock);
                        $this->pageData['clockmax'] = (string) $this->stripText(' MHz', $data->max_clocks->graphics_clock);
                    }
                    if (isset($data->clocks->mem_clock, $data->max_clocks->mem_clock)) {
                        $this->pageData['memclock'] = (string) $this->stripText(' MHz', $data->clocks->mem_clock);
                        $this->pageData['memclockmax'] = (string) $this->stripText(' MHz', $data->max_clocks->mem_clock);
                    }
                }
            }
            // For some reason, encoder_sessions->session_count is not reliable on my install, better to count processes
            if ($this->settings['DISPSESSIONS']) {
                $this->pageData['appssupp'] = array_keys(self::SUPPORTED_APPS);
                if (isset($data->processes->process_info)) {
                    $this->pageData['sessions'] = count($data->processes->process_info);
                    if ($this->pageData['sessions'] > 0) {
                        foreach ($data->processes->children() as $process) {
                            if (isset($process->process_name)) {
                                $this->detectApplication($process);
                            }
                        }
                    }
                }
            }
            if ($this->settings['DISPPCIUTIL']) {
                $this->getBusUtilization($data->pci);
            }
        } else {
            $this->pageData['error'][] = Error::get(Error::VENDOR_DATA_BAD_PARSE);
        }
        $this->echoJson();
    }
}
