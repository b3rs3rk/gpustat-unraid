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
    const INVENTORY_PARAM = '| grep VGA';
    const INVENTORY_REGEX = '(?P<guid>\d+\:\d+\.\d+)\s+VGA.+\:\s+Intel\s+Corporation\s+(?P<model>.*)\s+\(rev/i';
    const STATISTICS_PARAM = '-J -s 5000';

    /**
     * Intel constructor.
     * @param array $settings
     */
    public function __construct(array $settings = [])
    {
        parent::__construct($settings);
    }

    /**
     * Retrieves Intel inventory using lshw and parses into \SimplexXMLElement
     *
     * @return array
     */
    public function getInventory()
    {
        $this->runCommand(self::INVENTORY_UTILITY, self::INVENTORY_PARAM);
        if (!empty($this->stdout) && strlen($this->stdout) > 0) {
            $this->parseInventory(self::INVENTORY_REGEX);
        } else {
            new Error(Error::VENDOR_DATA_NOT_RETURNED);
        }
        // Only one iGPU per system, so mark it ID 0
        $this->inventory += ["id" => '0'];
        return $this->inventory;
    }

    /**
     * Retrieves NVIDIA card statistics
     */
    public function getStatistics()
    {
        //Command invokes intel_gpu_top in JSON output mode with an update rate of 5 seconds
        $this->runLongCommand(self::CMD_UTILITY, self::STATISTICS_PARAM);
        if (!empty($this->stdout) && strlen($this->stdout) > 0) {
            $this->parseStatistics();
        } else {
            new Error(Error::VENDOR_DATA_NOT_RETURNED);
        }
    }

    /**
     * Loads stdout into SimpleXMLObject then retrieves and returns specific definitions in an array
     */
    private function parseStatistics()
    {
        $gpu = json_encode($this->stdout);
        $retval = array();

        if (!empty($gpu[0])) {

            $retval = $this->pageData;
            $retval += [
                'vendor'    => 'Intel',
                'name'      => 'Integrated Graphics',
            ];

            if (isset($gpu['engines']['Render/3D/0']['busy'])) {
                $retval['util'] = (string) $this->roundFloat($gpu['engines']['Render/3D/0']['busy']) . '%';
            }
            if (isset($gpu['engines']['Video/0']['busy'])) {
                $retval['encutil'] = (string) $this->roundFloat($gpu['engines']['Video/0']['busy']) . '%';
            }
            if (isset($gpu['power']['value'])) {
                $retval['power'] = (string) $this->roundFloat($gpu['power']['value']) . $gpu['power']['unit'];
            }
            if (isset($gpu['frequency']['requested'])) {
                $retval['clock'] = (string) str_replace(' MHz', '', $gpu->clocks->graphics_clock);
            }
        } else {
            new Error(Error::VENDOR_DATA_BAD_PARSE);
        }
        $this->echoJson($retval);
    }
}