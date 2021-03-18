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

/**
 * Class AMD
 * @package gpustat\lib
 */
class AMD extends Main
{
    const CMD_UTILITY = 'radeontop';
    const INVENTORY_UTILITY = 'lspci';
    const INVENTORY_PARAM = '| grep VGA';
    const INVENTORY_REGEX =
        '/(?P<busid>\d{2}).*?\[AMD(\/ATI)?\]\s+(?P<model>.*)\s+\[/iU';
    const STATISTICS_PARAM = '-d - -i .5 -l 1';

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
                        $result[] = [
                            'id'    => (int) $gpu['busid'],
                            'model' => (string) $gpu['model'],
                            'guid'  => '0000-00-000-000000',
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
                $this->pageData['error'][] += new Error(Error::VENDOR_DATA_NOT_RETURNED);
            }
        }
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

        $keyMap = [
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
            'mclk'  => ['memclockutil', 'memclock'],
            'sclk'  => ['clockutil', 'clock'],
        ];

        // radeontop data doesn't follow a standard object format -- need to parse CSV and then explode by spaces
        $data = explode(", ", substr($this->stdout, strpos($this->stdout, 'gpu')));

        if ($count = count($data) > 0) {
            foreach ($data AS $metric) {
                // metric util% value
                $fields = explode(" ", $metric);
                if (isset($keyMap[$fields[0]])) {
                    $values = $keyMap[$fields[0]];
                    if ($this->settings['DISP' . strtoupper($values[0])]) {
                        $this->pageData[$values[0]] = $fields[1];
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
                    }
                }
            }
            unset($data, $this->stdout);
        } else {
            $this->pageData['error'][] += new Error(Error::VENDOR_DATA_NOT_ENOUGH, "Count: $count");
        }
        $this->echoJson();
    }
}
