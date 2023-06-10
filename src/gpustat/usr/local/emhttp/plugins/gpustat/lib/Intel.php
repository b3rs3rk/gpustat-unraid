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

use JsonException;

/**
 * Class Intel
 * @package gpustat\lib
 */
class Intel extends Main
{
    const CMD_UTILITY = 'intel_gpu_top';
    const INVENTORY_UTILITY = 'lspci';
    const INVENTORY_PARAM = " -Dmm | grep -E 'Display|VGA' ";
    const INVENTORY_REGEX =
        '/VGA.+:\s+Intel\s+Corporation\s+(?P<model>.*)\s+(\[|Family|Integrated|Graphics|Controller|Series|\()/iU';
    const STATISTICS_PARAM = '-J -s 250 -d pci:slot="';
    const STATISTICS_WRAPPER = 'timeout -k .500 .600';

    /**
     * Intel constructor.
     * @param array $settings
     */
    public function __construct(array $settings = [])
    {
        $settings += ['cmd' => self::CMD_UTILITY];
        parent::__construct($settings);
    }

    /**
     * Retrieves Intel inventory using lspci and returns an array
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
                    foreach(explode(PHP_EOL,$this->stdout) AS $vga) {
                        preg_match_all('/"([^"]*)"|(\S+)/', $vga, $matches);
                        $id = str_replace('"', '', $matches[0][0]) ;
                        $vendor = str_replace('"', '',$matches[0][2]) ;
                        $model = str_replace('"', '',$matches[0][3]) ;
                        if ($vendor != "Intel Corporation") continue ;
                        $result[$id] = [
                            'id' => substr($id,5) ,
                            'model' => $model,
                            'vendor' => 'intel',
                            'guid' => $id
                        ];

                     }
                 }
            }
        }
        return $result;
    }

    /**
     * Retrieves Intel iGPU statistics
     */
    public function getStatistics()
    {
        if ($this->cmdexists) {
            //Command invokes intel_gpu_top in JSON output mode with an update rate of 5 seconds
            $command = self::STATISTICS_WRAPPER . ES . self::CMD_UTILITY;
                        //Command invokes radeontop in STDOUT mode with an update limit of half a second @ 120 samples per second
            $this->runCommand($command, self::STATISTICS_PARAM. $this->settings['GPUID'].'"', false);
            if (!empty($this->stdout) && strlen($this->stdout) > 0) {
                $this->parseStatistics();
            } else {
                $this->pageData['error'][] = Error::get(Error::VENDOR_DATA_NOT_RETURNED);
            }
            return json_encode($this->pageData) ;
        }
    }

    /**
     * Loads JSON into array then retrieves and returns specific definitions in an array
     */
    private function parseStatistics()
    {
        // JSON output from intel_gpu_top with multiple array indexes isn't properly formatted
        $stdout = "[" . str_replace('}{', '},{', str_replace(["\n","\t"], '', $this->stdout)) . "]";

        try {
            $data = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $data = [];
            $this->pageData['error'][] = Error::get(Error::VENDOR_DATA_BAD_PARSE, $e->getMessage());
        }

        // Need to make sure we have at least two array indexes to take the second one
        $count = count($data);
        if ($count < 2) {
            $this->pageData['error'][] = Error::get(Error::VENDOR_DATA_NOT_ENOUGH, "Count: $count");
        }

        // intel_gpu_top will never show utilization counters on the first sample so we need the second position
        $data = $data[1];
        unset($stdout, $this->stdout);

        if (!empty($data)) {

            $this->pageData += [
                'vendor'        => 'Intel',
                'name'          => 'iGPU/GPU',
                '3drender'      => 'N/A',
                'blitter'       => 'N/A',
                'interrupts'    => 'N/A',
                'powerutil'     => 'N/A',
                'video'         => 'N/A',
                'videnh'        => 'N/A',
            ];
            $gpus = $this->getInventory() ;
            if ($gpus) {
                if (isset($gpus[$this->settings['GPUID']])) {
                    $this->pageData['name'] = $gpus[$this->settings['GPUID']]["model"] ;
                }
            }
            if ($this->settings['DISP3DRENDER']) {
                if (isset($data['engines']['Render/3D/0']['busy'])) {
                    $this->pageData['util'] = $this->pageData['3drender'] = $this->roundFloat($data['engines']['Render/3D/0']['busy']) . '%';
                }
            }
            if ($this->settings['DISPBLITTER']) {
                if (isset($data['engines']['Blitter/0']['busy'])) {
                    $this->pageData['blitter'] = $this->roundFloat($data['engines']['Blitter/0']['busy']) . '%';
                }
            }
            if ($this->settings['DISPVIDEO']) {
                if (isset($data['engines']['Video/0']['busy'])) {
                    $this->pageData['video'] = $this->roundFloat($data['engines']['Video/0']['busy']) . '%';
                }
            }
            if ($this->settings['DISPVIDENH']) {
                if (isset($data['engines']['VideoEnhance/0']['busy'])) {
                    $this->pageData['videnh'] = $this->roundFloat($data['engines']['VideoEnhance/0']['busy']) . '%';
                }
            }
            if ($this->settings['DISPPCIUTIL']) {
                if (isset($data['imc-bandwidth']['reads'], $data['imc-bandwidth']['writes'])) {
                    $this->pageData['rxutil'] = $this->roundFloat($data['imc-bandwidth']['reads'], 2) . " MB/s";
                    $this->pageData['txutil'] = $this->roundFloat($data['imc-bandwidth']['writes'], 2) . " MB/s";
                }
            }
            if ($this->settings['DISPPWRDRAW']) {
                // Older versions of intel_gpu_top in case people haven't updated
                if (isset($data['power']['value'])) {
                    $this->pageData['power'] = $this->roundFloat($data['power']['value'], 2) . $data['power']['unit'];
                // Newer version of intel_gpu_top includes GPU and package power readings, just scrape GPU for now
                } else {
                    if (isset($data['power']['Package']) && ($this->settings['DISPPWRDRWSEL'] == "MAX" || $this->settings['DISPPWRDRWSEL'] == "PACKAGE" )) $powerPackage = $this->roundFloat($data['power']['Package'], 2) ; else $powerPackage = 0 ;
                    if (isset($data['power']['GPU']) && ($this->settings['DISPPWRDRWSEL'] == "MAX" || $this->settings['DISPPWRDRWSEL'] == "GPU" )) $powerGPU = $this->roundFloat($data['power']['GPU'], 2) ;  else $powerGPU = 0 ;
                    $this->pageData['power'] = max($powerGPU,$powerPackage) . $data['power']['unit'] ;               
                }
            }
            // According to the sparse documentation, rc6 is a percentage of how little the GPU is requesting power
            if ($this->settings['DISPPWRSTATE']) {
                if (isset($data['rc6']['value'])) {
                    $this->pageData['powerutil'] = $this->roundFloat(100 - $data['rc6']['value'], 2) . "%";
                }
            }
            if ($this->settings['DISPCLOCKS']) {
                if (isset($data['frequency']['actual'])) {
                    $this->pageData['clock'] = (int) $this->roundFloat($data['frequency']['actual']);
                }
            }
            if ($this->settings['DISPINTERRUPT']) {
                if (isset($data['interrupts']['count'])) {
                    $this->pageData['interrupts'] = (int) $this->roundFloat($data['interrupts']['count']);
                }
            }
        } else {
            $this->pageData['error'][] = Error::get(Error::VENDOR_DATA_BAD_PARSE);
        }
      }
}
