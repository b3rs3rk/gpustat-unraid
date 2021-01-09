<?php

namespace gpustat\lib;

/**
 * Class Intel
 * @package gpustat\lib
 */
class Intel extends Main
{
    const CMD_UTILITY = 'intel_gpu_top';
    const INVENTORY_UTILITY = 'lspci';
    const INVENTORY_PARAM = "| grep VGA";
    const INVENTORY_REGEX =
        '/VGA.+\:\s+Intel\s+Corporation\s+(?P<model>.*)\s+(Family|Integrated|Graphics|Controller|Series|\()/iU';
    const STATISTICS_PARAM = '-J -s 5000';
    const STATISTICS_WRAPPER = 'timeout -k 1 .100';

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
        $result = $inventory = [];

        if ($this->cmdexists) {
            $this->checkCommand(self::INVENTORY_UTILITY, false);
            if ($this->cmdexists) {
                $this->runCommand(self::INVENTORY_UTILITY, self::INVENTORY_PARAM, false);
                if (!empty($this->stdout) && strlen($this->stdout) > 0) {
                    $this->parseInventory(self::INVENTORY_REGEX);
                }
                if (!empty($this->inventory)) {
                    // Only one iGPU per system, so mark it ID 99 and pad other results
                    $inventory[0] = [
                        'id' => 99,
                        'model' => $this->inventory[0]['model'],
                        'guid' => '0000-00-000-000000',
                    ];
                    $result = $inventory;
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
            $this->runCommand($command, self::STATISTICS_PARAM, false);
            if (!empty($this->stdout) && strlen($this->stdout) > 0) {
                $this->parseStatistics();
            } else {
                new Error(Error::VENDOR_DATA_NOT_RETURNED);
            }
        }
    }

    /**
     * Loads JSON into array then retrieves and returns specific definitions in an array
     */
    private function parseStatistics()
    {
        $data = json_decode($this->stdout, true);
        $this->stdout = '';

        if (!empty($data)) {

            $this->pageData += [
                'vendor'    => 'Intel',
                'name'      => 'Integrated Graphics',
                '3drender'  => 'N/A',
                'blitter'   => 'N/A',
                'video'     => 'N/A',
                'videnh'    => 'N/A',
            ];

            if (isset($data['engines']['Render/3D/0']['busy'])) {
                $this->pageData['util'] = $this->pageData['3drender'] = (string) $this->roundFloat($data['engines']['Render/3D/0']['busy']) . '%';
            }
            if (isset($data['engines']['Blitter/0']['busy'])) {
                $this->pageData['blitter'] = (string) $this->roundFloat($data['engines']['Blitter/0']['busy']) . '%';
            }
            if (isset($data['engines']['Video/0']['busy'])) {
                $this->pageData['video'] = (string) $this->roundFloat($data['engines']['Video/0']['busy']) . '%';
            }
            if (isset($data['engines']['VideoEnhance/0']['busy'])) {
                $this->pageData['videnh'] = (string) $this->roundFloat($data['engines']['VideoEnhance/0']['busy']) . '%';
            }
            if (isset($data['imc-bandwidth']['reads']) && isset($data['imc-bandwidth']['writes'])) {
                $this->pageData['rxutil'] = $this->roundFloat($data['imc-bandwidth']['reads']);
                $this->pageData['txutil'] = $this->roundFloat($data['imc-bandwidth']['writes']);
                $this->pageData['bwutil'] = $this->pageData['rxutil'] . " | " . $this->pageData['txutil'];
            }
            if (isset($data['power']['value'])) {
                $this->pageData['power'] = (string) $this->roundFloat($data['power']['value']) . $data['power']['unit'];
            }
            if (isset($data['frequency']['actual'])) {
                $this->pageData['clock'] = (string) $this->stripText(' MHz', $data->clocks->graphics_clock);
            }
        } else {
            new Error(Error::VENDOR_DATA_BAD_PARSE);
        }
        $this->echoJson();
    }
}