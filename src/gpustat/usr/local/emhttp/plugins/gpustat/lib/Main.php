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

/** @noinspection PhpIncludeInspection */
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
        if (isset($this->settings['inventory'])) {
            $this->checkCommand($this->settings['cmd'], false);
        } else {
            $this->checkCommand($this->settings['cmd']);
        }

        $this->stdout = '';
        $this->inventory = [];

        $this->pageData = [
            'clock'     => 'N/A',
            'fan'       => 'N/A',
            'memclock'  => 'N/A',
            'memutil'   => 'N/A',
            'memused'   => 'N/A',
            'power'     => 'N/A',
            'powermax'  => 'N/A',
            'rxutil'    => 'N/A',
            'txutil'    => 'N/A',
            'temp'      => 'N/A',
            'tempmax'   => 'N/A',
            'util'      => 'N/A',
        ];
    }

    /**
     * Checks if vendor utility exists in the system and dies if it does not
     *
     * @param string $utility
     * @param bool $error
     */
    protected function checkCommand(string $utility, bool $error = true)
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
                $this->pageData['error'][] = Error::get(Error::VENDOR_UTILITY_NOT_FOUND);
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
    protected function runCommand(string $command, string $argument = '', bool $escape = true)
    {
        if ($escape) {
            $this->stdout = shell_exec(sprintf("%s %s", $command, escapeshellarg($argument)));
        } else {
            $this->stdout = shell_exec(sprintf("%s %s", $command, $argument));
        }
    }

    /**
     * Retrieves the full command with arguments for a given process ID
     *
     * @param int $pid
     * @return string
     */
    protected function getFullCommand(int $pid): string
    {
        $command = '';
        $file = sprintf('/proc/%0d/cmdline', $pid);

        if (file_exists($file)) {
            $command = trim(@file_get_contents($file), "\0");
        }

        return $command;
    }

    /**
     * Retrieves the full command of a parent process with arguments for a given process ID
     *
     * @param int $pid
     * @return string
     */
    protected function getParentCommand(int $pid): string
    {
        $command = '';
        $pid_command = sprintf('ps j %0d | awk \'{ \$1=\$1 };NR>1\' | cut -d \' \' -f 1', $pid);

        $ppid = (int)trim(shell_exec($pid_command));
        if ($ppid > 0) {
            $command = $this->getFullCommand($ppid);
        }

        return $command;
    }

    /**
     * Retrieves plugin settings and returns them or defaults if no file
     *
     * @return mixed
     */
    public static function getSettings()
    {
        /** @noinspection PhpUndefinedFunctionInspection */
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
     * Strips all spaces from a provided string
     *
     * @param string $text
     * @return string
     */
    protected static function stripSpaces(string $text = ''): string
    {
        return str_replace(' ', '', $text);
    }

    /**
     * Converts Celsius to Fahrenheit
     *
     * @param int $temp
     * @return float
     */
    protected static function convertCelsius(int $temp = 0): float
    {
        $fahrenheit = $temp*(9/5)+32;
        
        return round($fahrenheit, -1);
    }

    /**
     * Rounds a float to a whole number
     *
     * @param float $number
     * @param int $precision
     * @return float
     */
    protected static function roundFloat(float $number, int $precision = 0): float
    {
        if ($precision > 0) {
            $result = number_format(round($number, $precision), $precision, '.','');
        } else {
            $result = round($number, $precision);
        }

        return $result;
    }

    /**
     * Replaces a string within a string with an empty string
     *
     * @param string|string[] $strip
     * @param string $string
     * @return string|string[]
     */
    protected static function stripText($strip, string $string)
    {
        return str_replace($strip, '', $string);
    }
}
