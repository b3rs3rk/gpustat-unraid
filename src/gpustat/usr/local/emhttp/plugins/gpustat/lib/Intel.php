<?php

namespace gpustat\lib;

/**
 * Class Intel
 * @package gpustat\lib
 */
class Intel extends Main
{
    const CMD_UTILITY = 'intel_gpu_top';
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
    private function parseStatistics () {

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
        }
        $this->echoJson($retval);
    }
}