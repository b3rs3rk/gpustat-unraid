<?php

namespace gpustat\lib;

require_once('/usr/local/emhttp/plugins/dynamix/include/Wrappers.php');

/**
 * Class Main
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
     * @var array
     */
    protected $pageData;

    /**
     * @var bool
     */
    protected $cmdexists;

    /**
     * GPUStat constructor.
     *
     * @param array $settings
     */
    public function __construct(array $settings = [])
    {
        $this->settings = $settings;
        $this->checkCommand($this->settings['cmd']);

        $this->stdout = '';
        $this->inventory = [];

        $this->pageData = [
            'clock'         => 'N/A',
            'util'          => 'N/A',
            'power'         => 'N/A',
            'rxutil'        => 'N/A',
            'txutil'        => 'N/A',
        ];
    }

    /**
     * Checks if vendor utility exists in the system and dies if it does not
     *
     * @param string $utility
     * @param bool $error
     */
    protected function checkCommand(string $utility, $error = true)
    {
        $this->cmdexists = false;
        // Check if vendor utility is available
        $this->runCommand(self::COMMAND_EXISTS_CHECKER, $utility, false);
        // When checking for existence of the command, we want the return to be NULL
        if (!empty($this->stdout)) {
            $this->cmdexists = true;
        } else {
            // Send the error but don't die because we need to continue for inventory
            if ($error) {
                new Error(Error::VENDOR_UTILITY_NOT_FOUND, '', false);
            }
        }
    }

    /**
     * Runs a command in shell and stores STDOUT in class variable
     *
     * @param string $command
     * @param string $argument
     * @param bool $escape
     */
    protected function runCommand(string $command, string $argument = '', $escape = true)
    {
        if ($escape) {
            $this->stdout = shell_exec(sprintf("%s %s", $command, escapeshellarg($argument)));
        } else {
            $this->stdout = shell_exec(sprintf("%s %s", $command, $argument));
        }
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
     * Triggers regex match all against class variable stdout and places matches in class variable inventory
     *
     * @param string $regex
     */
    protected function parseInventory(string $regex = '')
    {
        preg_match_all($regex, $this->stdout, $this->inventory, PREG_SET_ORDER);
    }

    /**
     * Echoes JSON to web renderer -- used to populate page data
     */
    protected function echoJson()
    {
        // Page file JavaScript expects a JSON encoded string
        if (is_array($this->pageData)) {
            $json = json_encode($this->pageData);
            header('Content-Type: application/json');
            header('Content-Length:' . ES . strlen($json));
            echo $json;
        } else {
            new Error(Error::BAD_ARRAY_DATA);
        }
    }

    /**
     * Strips all spaces from a provided string
     *
     * @param string $text
     * @return string|string[]
     */
    protected static function stripSpaces(string $text = '')
    {
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

    /**
     * Rounds a float to a whole number
     *
     * @param float $number
     * @param int $precision
     * @return false|float
     */
    protected static function roundFloat(float $number, int $precision = 0)
    {
        return round($number, $precision, PHP_ROUND_HALF_UP);
    }

    /**
     * Replaces a string within a string with an empty string
     *
     * @param string $strip
     * @param string $string
     * @return string|string[]
     */
    protected static function stripText(string $strip, string $string)
    {
        return str_replace($strip, '', $string);
    }
}
