<?php

/*
  MIT License

  Copyright (c) 2020-2022 b3rs3rk

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
    const INVENTORY_PARM_PCI = "-q -x -g %s 2>&1 | grep 'gpu id'";
    const INVENTORY_REGEX = '/GPU\s(?P<id>\d):\s(?P<model>.*)\s\(UUID:\s(?P<guid>GPU-[0-9a-f-]+)\)/i';
    const PCI_INVENTORY_UTILITY = 'lspci';
    const PCI_INVENTORY_PARAM = '| grep VGA';
    const PCI_INVENTORY_PARAMm = " -Dmm | grep VGA";
    const PCD_INVENTORY_REGEX =
        '/^(?P<busid>[0-9a-f]{2}).*\[AMD(\/ATI)?\]\s+(?P<model>.+)\s+(\[(?P<product>.+)\]|\()/imU';

    const STATISTICS_PARAM = '-q -x -g %s 2>&1';
    const SUPPORTED_APPS = [ // Order here is important because some apps use the same binaries -- order should be more specific to less
        'plex'        => ['Plex Transcoder'],
        'jellyfin'    => ['jellyfin-ffmpeg'],
        'handbrake'   => ['/usr/bin/HandBrakeCLI'],
        'emby'        => ['ffmpeg', 'Emby'],
        'tdarr'       => ['ffmpeg', 'HandbrakeCLI'],
        'unmanic'     => ['ffmpeg'],
        'dizquetv'    => ['ffmpeg'],
        'ersatztv'    => ['ffmpeg'],
        'fileflows'   => ['ffmpeg'],
        'frigate'     => ['ffmpeg'],
        'threadfin'   => ['ffmpeg','Threadfin'],
        'tunarr'      => ['ffmpeg','tunarr'],
        'codeproject' => ['python3.8'],
        'deepstack'   => ['python3'],
        'nsfminer'    => ['nsfminer'],
        'shinobipro'  => ['shinobi'],
        'foldinghome' => ['FahCore'],
        'compreface'  => ['uwsgi'],
        'ollama'     => ['ollama_llama_server'],
        'immich'     => ['/config/machine-learning/cuda'],
        'localai'     => ['localai'],
        'invokeai'    => ['invokeai'],
        'chia'        => ['chia'],
        'mmx'         => ['mmx_node'],
        'subspace'    => ['subspace'],
        'xorg'        => ['Xorg'],
        'qemu'        => ['qemu'],
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
        $debug_apps = false;
        if ($debug_apps) file_put_contents("/tmp/gpuappsnv","");
        foreach (self::SUPPORTED_APPS as $app => $commands) {
            foreach ($commands as $command) {
                if (strpos($process->process_name, $command) !== false) {
                    // For Handbrake/ffmpeg: arguments tell us which application called it
                    if (in_array($command, ['ffmpeg', 'HandbrakeCLI', 'python3.8','python3'])) {
                        if (isset($process->pid)) {
                            $pid_info = $this->getFullCommand((int) $process->pid);
                            if ($debug_apps) file_put_contents("/tmp/gpuappsnv","$command\n$pid_info\n",FILE_APPEND);
                            if (!empty($pid_info) && strlen($pid_info) > 0) {
                                if ($command === 'python3.8') {
                                    // CodeProject doesn't have any signifier in the full command output
                                    if (strpos($pid_info, '/ObjectDetectionYolo/detect_adapter.py') === false) {
                                        continue 2;
                                    }
                                } elseif ($command === 'python3') {
                                    // Deepstack doesn't have any signifier in the full command output
                                    if (strpos($pid_info, '/app/intelligencelayer/shared') === false) {
                                        continue 2;
                                    }
                                } elseif (stripos($pid_info, strtolower($app)) === false) {
                                    // Try to match the app name in the parent process
                                    $ppid_info = $this->getParentCommand((int) $process->pid);
                                    if ($debug_apps) file_put_contents("/tmp/gpuappsnv","$ppid_info\n",FILE_APPEND);
                                    if (stripos($ppid_info, $app) === false) {
                                        // We didn't match the application name in the arguments, no match
                                        if ($debug_apps) file_put_contents("/tmp/gpuappsnv","not found app $app\n",FILE_APPEND);
                                        continue 2;
                                    } else if ($debug_apps) file_put_contents("/tmp/gpuappsnv","\nfound app $app\n",FILE_APPEND);
                                }
                            }
                        }
                    }
                    $this->pageData[$app . 'using'] = true;
                    $this->pageData[$app . 'mem'] += (int)$this->stripText(' MiB', $process->used_memory);
                    $this->pageData[$app . 'count']++;
                    if ($debug_apps) file_put_contents("/tmp/gpuappsnv","\nfound app $app $command\n",FILE_APPEND);
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
     * Retrieves NVIDIA card inventory and parses into an array
     *
     * @return array
     */
    public function getInventorym(): array
    {
        $result2 = $result = [];

        $this->runCommand(self::CMD_UTILITY, self::INVENTORY_PARAM, false);
        if (!empty($this->stdout) && strlen($this->stdout) > 0) {
            $this->parseInventory(self::INVENTORY_REGEX);
            if (!empty($this->inventory)) {
                $result = $this->inventory;
            }
        }
        foreach($result as $gpu) {
            $cmd =self::CMD_UTILITY . ES . sprintf(self::INVENTORY_PARM_PCI, $gpu['guid']) ;
            $cmdres = $this->stdout = shell_exec($cmd); 
            $pci = substr($cmdres,14,12);
            $gpu['id'] = substr($pci,5) ;
            $gpu['vendor'] = 'nvidia' ;
            $result2[$pci] = $gpu ; 
        }
        if (empty($result)) $result2=$this->getPCIInventory() ;

        return $result2;
    }

        /**
     * Retrieves Intel inventory using lspci and returns an array
     *
     * @return array
     */
    public function getPCIInventory(): array
    {
        $result = [];

        $this->checkCommand(self::PCI_INVENTORY_UTILITY, false);
        if ($this->cmdexists) {
            $this->runCommand(self::PCI_INVENTORY_UTILITY, self::PCI_INVENTORY_PARAMm, false);
            if (!empty($this->stdout) && strlen($this->stdout) > 0) {
                foreach(explode(PHP_EOL,$this->stdout) AS $vga) {
                    preg_match_all('/"([^"]*)"|(\S+)/', $vga, $matches);
                    if (!isset( $matches[0][0])) continue ;
                    $id = str_replace('"', '', $matches[0][0]) ;
                    $vendor = str_replace('"', '',$matches[0][2]) ;
                    $model = str_replace('"', '',$matches[0][3]) ;
                    $modelstart = strpos($model,'[') ;
                    $model = substr($model,$modelstart,strlen($model)- $modelstart) ;
                    $model= str_replace(array("Quadro","GeForce","[","]"),"",$model) ;

                    if ($vendor != "NVIDIA Corporation") continue ;
                    $result[$id] = [
                        'id' => substr($id,5) ,
                        'model' => $model ,
                        'vendor' => 'nvidia',
                        'guid' => $id
                    ];

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
            if ($words[0] == "GeForce")  {
                array_shift($words) ;  
                $words2 = implode(" ", $words) ;
                if (strlen($words2) <= 20) $this->pageData['name'] = $words2;
                } else  $this->pageData['name'] = sprintf("%0s %1s", $words[0], $words[1]);
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
                        $this->pageData['thrtlrsn'] = ' (' . $this->stripText(['clocks_throttle_reason_','_setting'], $reason) . ')';
                        break;
                    }
                }
            }
            if (isset($data->clocks_event_reasons)) {
                $this->pageData['throttled'] = 'No';
                foreach ($data->clocks_event_reasons->children() as $reason => $throttle) {
                    if ($throttle == 'Active') {
                        $this->pageData['throttled'] = 'Yes';
                        $this->pageData['thrtlrsn'] = ' (' . $this->stripText(['clocks_event_reason_','_setting'], $reason) . ')';
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
            if (isset($data->gpu_power_readings)) {
                if (isset($data->gpu_power_readings->power_draw)) {
                    $this->pageData['power'] = (float) $this->stripText(' W', $data->gpu_power_readings->power_draw);
                    $this->pageData['power'] = $this->roundFloat($this->pageData['power']) . 'W';
                    }
                if (isset($data->gpu_power_readings->instant_power_draw)) {
                    $this->pageData['power'] = (float) $this->stripText(' W', $data->gpu_power_readings->instant_power_draw);
                    $this->pageData['power'] = $this->roundFloat($this->pageData['power']) . 'W';
                    }
                if (isset($data->power_readings->power_limit)) {
                    $this->pageData['powermax'] = (string) $this->stripText('.00 W', $data->gpu_power_readings->current_power_limit);
                }
            }
        }
    }

    /**
     * Retrieves NVIDIA card statistics
     */
    public function getStatistics()
    {
        $driver = strtoupper($this->getKernelDriver("0000:".$this->settings['PCIID']));
        if ($driver != "NVIDIA" && $driver != "NOUVEAU") $driver = "NVIDIA";
        if (!$this->checkVFIO("0000:".$this->settings['PCIID'])) {
            if (($this->cmdexists && $driver == "NVIDIA") || $driver =="NOUVEAU") {
                //Command invokes nvidia-smi in query all mode with XML return
                if ($driver == "NVIDIA") {
                    $this->stdout = shell_exec(self::CMD_UTILITY . ES . sprintf(self::STATISTICS_PARAM, $this->settings['GPUID']));
                    #$this->stdout = shell_exec("cat /tmp/nvtxt");
                    // Saved UUID is stale if the card in this PCI slot was swapped; fall back to the PCI bus ID
                    if (stripos($this->stdout, '<gpu') === false) {
                        $this->stdout = shell_exec(self::CMD_UTILITY . ES . sprintf(self::STATISTICS_PARAM, '0000:' . $this->settings['PCIID']));
                    }
                } else {
                    $this->stdout = $this->buildNouveauXML("0000:".$this->settings['PCIID']);
                }
                #$this->stdout = shell_exec("cat /tmp/nv" );
                if (!empty($this->stdout) && strlen($this->stdout) > 0) {
                    $this->parseStatistics();
                } else {
                    
                    $this->pageData['error'][] = Error::get(Error::VENDOR_DATA_NOT_RETURNED);
                }
            } else {
                $this->pageData['error'][] = Error::get(Error::VENDOR_UTILITY_NOT_FOUND);
                $this->pageData["vendor"] = "Nvidia" ;
                $this->pageData["name"] = "GPU is an Nvidia" ;
                $this->pageData['driver'] = $driver;
                $gpus = $this->getPCIInventory() ;
                if ($gpus) {
                    if (isset($gpus["0000:".$this->settings['PCIID']])) {
                        $this->pageData['name'] = $gpus["0000:".$this->settings['PCIID']]["model"] ;
                    }
                } else $this->pageData["name"] = $this->settings['GPUID'] ;
            }
            $this->pageData["vfio"] = false ;
            $this->pageData["vfiochk"] = $this->checkVFIO("0000:".$this->settings['PCIID']) ;
            $this->pageData["vfiochkid"] = "0000:".$this->settings['PCIID'] ;
            $this->pageData['vfiovm'] = false;
            $this->pageData['driver'] = $driver;
        } else {
            $this->pageData["vfio"] = true ;
            $this->pageData["vendor"] = "Nvidia" ;
            $this->pageData["vfiochk"] = $this->checkVFIO("0000:".$this->settings['PCIID']) ;
            $this->pageData["vfiochkid"] = $this->settings['PCIID'] ;
            $this->pageData['vfiovm'] = $this->get_gpu_vm($this->settings['PCIID']);
            $this->pageData['driver'] = $driver;
            $gpus = $this->getPCIInventory() ;
            $this->getPCIeBandwidth("0000:".$this->settings['PCIID']);
            if ($gpus) {
                if (isset($gpus[$this->settings['GPUID']])) {
                    $this->pageData['name'] = $gpus[$this->settings['GPUID']]["model"] ;
                }
            }

        }
        $this->pageData['igpu'] = (strpos("0000:".$this->settings['PCIID'], "0000:00:") === 0) ? "1" : "0";
        return json_encode($this->pageData) ;
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
                'sessions'      => 0,
                'uuid'          => 'N/A',
            ];

            // Set App HW Usage Defaults
            foreach (self::SUPPORTED_APPS AS $app => $process) {
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
    }
       /**
     * Retrieves the full command with arguments for a given process ID
     *
     * @param int $pid
     * @return string
     */
    protected function buildNouveauXML(string $pci_id): string
    {

        $gpu_path = "/sys/bus/pci/devices/$pci_id/";
        $debugfs_path = "/sys/kernel/debug/dri/$pci_id/";
        $clients_path = "/sys/kernel/debug/dri/$pci_id/clients";
        $hwmon_path = $this->find_hwmon_path($pci_id);

        $xml = new \SimpleXMLElement("<?xml version='1.0' encoding='UTF-8'?><nvidia_smi_log></nvidia_smi_log>");
        $xml->addChild('timestamp', date('r'));
        $xml->addChild('driver_version', $this->get_value("/sys/module/nouveau/version", "Nouveau"));
        $xml->addChild('cuda_version', "N/A");
        $xml->addChild('attached_gpus', "1");

        $gpu = $xml->addChild('gpu');
        $gpu->addAttribute('id', $pci_id);
        $gpu->addChild('product_name', $this->get_gpu_name($pci_id));
        $gpu->addChild('uuid', "GPU-" . md5($pci_id));
        
        $pci = $gpu->addChild('pci');
        $gpu_link_info = $pci->addChild('pci_gpu_link_info');
        $pcie_gen = $gpu_link_info->addChild('pcie_gen');
        $pcie_gen->addChild('max_link_gen', $this->get_value("$gpu_path/max_link_speed"));
        $pcie_gen->addChild('current_link_gen', $this->get_value("$gpu_path/current_link_speed"));
        
        $link_widths = $gpu_link_info->addChild('link_widths');
        $link_widths->addChild('max_link_width', $this->get_value("$gpu_path/max_link_width") . "x");
        $link_widths->addChild('current_link_width', $this->get_value("$gpu_path/current_link_width") . "x");
        
        $pci->addChild('tx_util', "300 KB/s");
        $pci->addChild('rx_util', "300 KB/s");
        
        $gpu->addChild('fan_speed', "33 %");
        $gpu->addChild('performance_state', "P0");
        
        $clocks_event = $gpu->addChild('clocks_event_reasons');
        $events = ["gpu_idle", "applications_clocks_setting", "sw_power_cap", "hw_slowdown", "hw_thermal_slowdown", "hw_power_brake_slowdown", "sync_boost", "sw_thermal_slowdown", "display_clocks_setting"];
        foreach ($events as $event) {
            $clocks_event->addChild("clocks_event_reason_$event", "Not Active");
        }
        
        $fb_memory = $gpu->addChild('fb_memory_usage');
        $fb_memory->addChild('total', "2048 MiB");
        $fb_memory->addChild('used', "1 MiB");
        $fb_memory->addChild('free', "1681 MiB");
        
        $utilization = $gpu->addChild('utilization');
        $utilization->addChild('gpu_util', "0 %");
        $utilization->addChild('memory_util', "0 %");
        $utilization->addChild('encoder_util', "0 %");
        $utilization->addChild('decoder_util', "0 %");
        
       
        $gpu_target_temp = $gpu->addChild('supported_gpu_target_temp');
        $gpu_target_temp->addChild('gpu_target_temp_min', "65 C");
        $gpu_target_temp->addChild('gpu_target_temp_max', "91 C");
        
        $power = $gpu->addChild('gpu_power_readings');
        $power->addChild('power_state', "P0");
        $power->addChild('average_power_draw', "N/A");
        $power->addChild('instant_power_draw', "N/A");
        $power->addChild('current_power_limit', "31.32 W");
        $power->addChild('requested_power_limit', "31.32 W");
        $power->addChild('default_power_limit', "31.32 W");
        $power->addChild('min_power_limit', "20.00 W");
        $power->addChild('max_power_limit', "31.32 W");
        
        $clocks = $gpu->addChild('clocks');
        $clocks->addChild('graphics_clock', "0 MHz");
        $clocks->addChild('mem_clock', "0 MHz");
        
        $max_clocks = $gpu->addChild('max_clocks');
        $max_clocks->addChild('graphics_clock', "2100 MHz");
        $max_clocks->addChild('mem_clock', "5001 MHz");
        
        $fan_speed = $hwmon_path ? $this->get_value("$hwmon_path/pwm1", "N/A") . " %" : "N/A";
        $gpu->addChild('fan_speed', $fan_speed);
        $gpu->addChild('performance_state', "P0");
        
        $temperature = $gpu->addChild('temperature');
        $gpu_temp = $hwmon_path ? ($this->get_value("$hwmon_path/temp1_input", "N/A") / 1000) . " C" : "N/A";
        $temperature->addChild('gpu_temp', $gpu_temp);
        $temperature->addChild('gpu_temp_max_threshold', "101 C");
        
        // Add process clients
        $processes = $gpu->addChild('processes');
        if (file_exists($clients_path)) {
            $lines = file($clients_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            array_shift($lines); // Remove the header row
            $tgidold = '';
            foreach ($lines as $line) {
                $columns = preg_split('/\s+/', trim($line));
                if (count($columns) >= 6) {
                    list($command, $tgid, $dev, $master, $a, $uid) = $columns;
                    if ($tgidold == $tgid) continue;
                    $process_info = $processes->addChild('process_info');
                    $process_info->addChild('gpu_instance_id', "N/A");
                    $process_info->addChild('compute_instance_id', "N/A");
                    $process_info->addChild('pid', $tgid);
                    $process_info->addChild('type', "C");
                    $process_info->addChild('process_name', $command);
                    $process_info->addChild('used_memory', "N/A");
                    $tgidold = $tgid;
                }
            }
        }

            $dom = new \DOMDocument("1.0");
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;
            $dom->loadXML($xml->asXML());
            #echo $dom->saveXML();
            file_put_contents("/tmp/nvnouvxml",$dom->saveXML());
            return $dom->saveXML();
        }

        protected function get_gpu_name($pciid) {
            $input = shell_exec("udevadm info query -p  /sys/bus/pci/devices/$pciid  | grep ID_MODEL") ;
            if (preg_match('/^E:\s*([^=]+)=(.*?)\s*\[(.*?)\]\s*$/', $input, $matches)) {
                return  trim($matches[3]);
            } elseif (preg_match('/^E:\s*([^=]+)=(.*?)$/', $input, $matches)) {
                return trim($matches[2]);
            }
            return _("Unknown");
        }
}
