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

/**
 * Class AMD
 * @package gpustat\lib
 */
class AMD extends Main
{
    const CMD_UTILITY = 'radeontop';
    const INVENTORY_UTILITY = 'lspci';
    const INVENTORY_PARAM = '| grep VGA';
    const INVENTORY_PARAMm = " -Dmm | grep VGA";
    const INVENTORY_REGEX =
        '/^(?P<busid>[0-9a-f]{2}).*\[AMD(\/ATI)?\]\s+(?P<model>.+)\s+(\[(?P<product>.+)\]|\()/imU';

    const STATISTICS_PARAM = '-d - -l 1';
    const STATISTICS_KEYMAP = [
        'gpu'   => ['util'],
        'ee'    => ['event'],
        'vgt'   => ['vertex'],
        'ta'    => ['texture'],
        'sx'    => ['shaderexp'],
        'sh'    => ['sequencer'],
        'spi'   => ['shaderinter'],
        'sc'    => ['scancon'],
        'pa'    => ['primassem'],
        'db'    => ['depthblk'],
        'cb'    => ['colorblk'],
        'vram'  => ['memutil', 'memused'],
        'gtt'   => ['gfxtrans', 'transused'],
        'mclk'  => ['memclockutil', 'memclock', 'clocks'],
        'sclk'  => ['clockutil', 'clock', 'clocks'],
    ];

    const TEMP_UTILITY = 'sensors';
    const TEMP_PARAM = '-j 2>errors';

    /**
     * AMD constructor.
     * @param array $settings
     */
    public function __construct(array $settings = [])
    {
        $settings += ['cmd' => self::CMD_UTILITY];
        parent::__construct($settings);
    }

    /**
     * Retrieves AMD inventory using lspci and returns an array
     *
     * @return array
     */
    public function getInventory(): array
    {
        $result = [];

        if ($this->cmdexists) {
            $this->checkCommand(self::INVENTORY_UTILITY, false);
            if ($this->cmdexists) {
                $this->runCommand(self::INVENTORY_UTILITY, self::INVENTORY_PARAM, false);
                if (!empty($this->stdout) && strlen($this->stdout) > 0) {
                    $this->parseInventory(self::INVENTORY_REGEX);
                }
                if (!empty($this->inventory)) {
                    foreach ($this->inventory AS $gpu) {
                        $result[$gpu['busid']] = [
                            'id'    => "Bus ID " . $gpu['busid'],
                            'model' => (string) ($gpu['product'] ?? $gpu['model']),
                            'guid'  => $gpu['busid'],
                        ];
                    }
                }
            }
        }

        return $result;
    }

        /**
     * Retrieves AMD inventory using lspci and returns an array
     *
     * @return array
     */
    public function getInventorym(): array
    {
        $result = [];

        if ($this->cmdexists) {
            $this->checkCommand(self::INVENTORY_UTILITY, false);
            if ($this->cmdexists) {
                $this->runCommand(self::INVENTORY_UTILITY, self::INVENTORY_PARAMm, false);
                if (!empty($this->stdout) && strlen($this->stdout) > 0) {
                    foreach(explode(PHP_EOL,$this->stdout) AS $vga) {
                        preg_match_all('/"([^"]*)"|(\S+)/', $vga, $matches);
                        $id = str_replace('"', '', $matches[0][0]) ;
                        $vendor = str_replace('"', '',$matches[0][2]) ;
                        $model = str_replace('"', '',$matches[0][3]) ;
                        if ($vendor != "Advanced Micro Devices, Inc. [AMD/ATI]") continue ;
                        $result[$id] = [
                            'id' => substr($id,5) ,
                            'model' => $model,
                            'vendor' => 'amd',
                            'guid' => substr($id,5,2)
                        ];

                     }
                 }
            }
        }

        return $result;
    }

    /**
     * Retrieves AMD APU/GPU statistics
     */
    public function getStatistics()
    {
        if ($this->cmdexists) {
            //Command invokes radeontop in STDOUT mode with an update limit of half a second @ 120 samples per second
            $command = sprintf("%0s -b %1s", self::CMD_UTILITY, $this->settings['GPUID']);
            $this->runCommand($command, self::STATISTICS_PARAM, false);
            if (!empty($this->stdout) && strlen($this->stdout) > 0) {
                $this->parseStatistics();
            } else {
                $this->pageData['error'][] += Error::get(Error::VENDOR_DATA_NOT_RETURNED);
            }
            return json_encode($this->pageData) ;
        }
    }

    /**
     * Retrieves AMD APU/GPU Temperature/Fan/Power/Voltage readings from lm-sensors
     * @returns array
     */
    private function getSensorData(): array
    {
        $sensors = [];

        $this->checkCommand(self::TEMP_UTILITY, false);
        if ($this->cmdexists) {
            $tempFormat = '';
            if ($this->settings['TEMPFORMAT'] == 'F') {
                $tempFormat = '-f';
            }
            $chip = sprintf('amdgpu-pci-%1s00', $this->settings['GPUID']);
            $command = sprintf('%0s %1s %2s', self::TEMP_UTILITY, $chip, $tempFormat);
            $this->runCommand($command, self::TEMP_PARAM, false);
            if (!empty($this->stdout) && strlen($this->stdout) > 0) {
                $data = json_decode($this->stdout, true);
                if(isset($data[$chip])) {
                    $data = $data[$chip];
                    if ($this->settings['DISPTEMP']) {
                        if (isset($data['edge']['temp1_input'])) {
                            $sensors['tempunit'] = $this->settings['TEMPFORMAT'];
                            $sensors['temp'] = $this->roundFloat($data['edge']['temp1_input']) . ' °' . $sensors['tempunit'];
                            if (isset($data['edge']['temp1_crit'])) {
                                $sensors['tempmax'] = $this->roundFloat($data['edge']['temp1_crit']);
                            }
                        }
                    }
                    if ($this->settings['DISPFAN']) {
                        if (isset($data['fan1']['fan1_input'])) {
                            $sensors['fan'] = $this->roundFloat($data['fan1']['fan1_input']);
                            if (isset($data['fan1']['fan1_max'])) {
                                $sensors['fanmax'] = $this->roundFloat($data['fan1']['fan1_max']);
                            }
                        }
                    }
                    if ($this->settings['DISPPWRDRAW']) {
                        if (isset($data['power1']['power1_average'])) {
                            $sensors['power'] = $this->roundFloat($data['power1']['power1_average'], 1);
                            $sensors['powerunit'] = 'W';
                            if (isset($data['power1']['power1_cap'])) {
                                $sensors['powermax'] = $this->roundFloat($data['power1']['power1_cap'], 1);
                            }
                        }
                        if (isset($data['vddgfx']['in0_input'])) {
                            $sensors['voltage'] = $this->roundFloat($data['vddgfx']['in0_input'], 2);
                            $sensors['voltageunit'] = 'V';
                        }
                    }
                }
            }
        }

        return $sensors;
    }

    /**
     * Loads radeontop STDOUT and parses into an associative array for mapping to plugin variables
     */
    private function parseStatistics()
    {
        $this->pageData += [
            'vendor'        => 'AMD',
            'name'          => 'APU/GPU',
            'event'         => 'N/A',
            'vertex'        => 'N/A',
            'texture'       => 'N/A',
            'shaderexp'     => 'N/A',
            'sequencer'     => 'N/A',
            'shaderinter'   => 'N/A',
            'scancon'       => 'N/A',
            'primassem'     => 'N/A',
            'depthblk'      => 'N/A',
            'colorblk'      => 'N/A',
        ];

        // radeontop data doesn't follow a standard object format -- need to parse CSV and then explode by spaces
        $data = explode(", ", substr($this->stdout, strpos($this->stdout, 'gpu')));
        $count = count($data);
        if ($count > 0) {
            foreach ($data AS $metric) {
                // metric util% value
                $fields = explode(" ", $metric);
                if (isset(self::STATISTICS_KEYMAP[$fields[0]])) {
                    $values = self::STATISTICS_KEYMAP[$fields[0]];
                    if ($this->settings['DISP' . strtoupper($values[0])] || $this->settings['DISP' . strtoupper($values[2])]) {
                        $this->pageData[$values[0]] = $this->roundFloat($this->stripText('%', $fields[1]), 1) . '%';
                        if (isset($fields[2])) {
                            $this->pageData[$values[1]] = $this->roundFloat(
                                trim(
                                    $this->stripText(
                                        ['mb','ghz'],
                                        $fields[2]
                                    )
                                ), 2
                            );
                        }
                    } elseif ($fields[0] == 'gpu') {
                        // GPU Load doesn't have a setting, for now just pass the check
                        $this->pageData[$values[0]] = $this->roundFloat($this->stripText('%', $fields[1]), 1) . '%';
                    }
                }
            }
            unset($data, $this->stdout);
        } else {
            $this->pageData['error'][] = Error::get(Error::VENDOR_DATA_NOT_ENOUGH, "Count: $count");
        }
        $this->pageData = array_merge($this->pageData, $this->getSensorData());

        $this->echoJson();
    }
}
