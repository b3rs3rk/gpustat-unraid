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
            'clock'     => 'N/A',
            'memclock'  => 'N/A',
            'util'      => 'N/A',
            'memutil'   => 'N/A',
            'encutil'   => 'N/A',
            'decutil'   => 'N/A',
            'temp'      => 'N/A',
            'tempmax'   => 'N/A',
            'fan'       => 'N/A',
            'perfstate' => 'N/A',
            'throttled' => 'N/A',
            'thrtlrsn'  => '',
            'power'     => 'N/A',
            'powermax'  => 'N/A',
            'sessions'  =>  0,
        ];
    }
    
    /**
     * Checks if vendor utility exists in the system and dies if it does not
     *
     * @param string $utility
     */
    protected function checkCommand(string $utility)
    {
        $this->cmdexists = false;
        // Check if vendor utility is available
        $this->runCommand(self::COMMAND_EXISTS_CHECKER, $utility);
        // When checking for existence of the command, we want the return to be NULL
        if (is_null($this->stdout)) {
            $this->cmdexists = true;
        } else {
            // Send the error but don't die because we need to continue for inventory
            new Error(Error::VENDOR_UTILITY_NOT_FOUND, '', false);
        }
    }

    /**
     * Runs a command in shell and stores STDOUT in class variable
     *
     * @param string $command
     * @param string $argument
     */
    protected function runCommand(string $command, string $argument = '')
    {
        $this->stdout = shell_exec(escapeshellarg($command . ES . $argument));
    }

    /**
     * Runs a command, waits for output and closes it immediately once received
     *
     * @param string $command
     * @param string $argument
     */
    protected function runLongCommand(string $command = '', string $argument = '')
    {
        $cmdDescriptor = [['pipe', 'w']];

        if (!empty($command)) {
            $process = proc_open(escapeshellarg($command . ES . $argument), $cmdDescriptor, $pipes);
            if (is_resource($process)) {
                $iter = 0;
                // Programs that don't self terminate need to be closed
                while (empty($this->stdout) && $iter <= 10) {
                    usleep(10000);
                    $this->stdout = stream_get_contents($pipes[0]);
                    if (!empty($this->stdout)) {
                        break;
                    }
                    usleep(100000);
                    $iter++;
                }
                fclose($pipes[0]);
                proc_close($process);
            } else {
                new Error(Error::PROCESS_NOT_OPENED);
            }
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
     *
     * @param array $data
     */
    protected function echoJson(array $data = [])
    {
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
     * @return false|float
     */
    protected static function roundFloat(float $number)
    {
        return round($number, 0, PHP_ROUND_HALF_UP);
    }
}
