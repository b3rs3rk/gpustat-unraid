<?php

namespace gpustat\lib;

require_once('/usr/local/emhttp/plugins/dynamix/include/Wrappers.php');

/**
 * Class GPUStat
 * @package gpustat\lib
 */
class Main
{
    const PLUGIN_NAME = 'gpustat';
    const COMMAND_EXISTS_CHECKER = 'which';

    /**
     * @var array
     */
    public $settings;

    /**
     * @var string
     */
    protected $stdout;

    /**
     * @var array
     */
    protected $inventory;
    
    /**
     * GPUStat constructor.
     *
     * @param array $settings
     */
    public function __construct(array $settings = [])
    {
        $this->settings = $settings;
        $this->checkCommand($settings['cmd']);
    }
    
    /**
     * Retrieves plugin settings and returns them or defaults if no file
     *
     * @return mixed
     */
    public static function getSettings()
    {
        return parse_plugin_cfg(self::PLUGIN_NAME);
    }

    /**
     * Echoes JSON to web renderer -- used to populate page data
     *
     * @param array $data
     */
    protected function echoJson (array $data = []) {
        // Page file JavaScript expects a JSON encoded string
        if (is_array($data)) {
            $json = json_encode($data);
            header('Content-Type: application/json');
            header('Content-Length:' . ES . strlen($json));
            echo $json;
        } else {
            new Error(Error::BAD_ARRAY_DATA);
        }
    }

    /**
     * Checks if vendor utility exists in the system and dies if it does not
     *
     * @param string $utility
     */
    protected function checkCommand(string $utility = '') {
        // Check if vendor utility is available
        if (is_null(shell_exec(self::COMMAND_EXISTS_CHECKER . ES . $utility))) {
            new Error(Error::VENDOR_UTILITY_NOT_FOUND);
        }
    }

    /**
     * Strips all spaces from a provided string
     *
     * @param string $text
     * @return string|string[]
     */
    protected static function stripSpaces(string $text = '') {
        
        return str_replace(' ', '', $text);
    }

    /**
     * Converts Celsius to Fahrenheit
     *
     * @param int $temp
     * @return false|float
     */
    protected static function convertCelsius(int $temp = 0)
    {
        $fahrenheit = $temp*(9/5)+32;
        
        return round($fahrenheit, -1, PHP_ROUND_HALF_UP);
    }
}
