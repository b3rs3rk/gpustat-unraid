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

const ES = ' ';

include 'lib/Main.php';
include 'lib/Nvidia.php';
include 'lib/Intel.php';
include 'lib/AMD.php';
include 'lib/Error.php';

use gpustat\lib\AMD;
use gpustat\lib\Main;
use gpustat\lib\Nvidia;
use gpustat\lib\Intel;
use gpustat\lib\Error;

if (!isset($gpustat_cfg)) {
    $gpustat_cfg = Main::getSettings();
}

// $gpustat_inventory should be set if called from settings page code
if (isset($gpustat_inventory) && $gpustat_inventory) {
    $gpustat_cfg['inventory'] = true;
    // Settings page looks for $gpustat_data specifically -- inventory all supported GPU types
    $gpustat_data = array_merge((new Nvidia($gpustat_cfg))->getInventory(), (new Intel($gpustat_cfg))->getInventory(), (new AMD($gpustat_cfg))->getInventory());
} else {

    switch ($gpustat_cfg['VENDOR']) {
        case 'amd':
            $data = (new AMD($gpustat_cfg))->getStatistics();
            break;
        case 'intel':
            $data = (new Intel($gpustat_cfg))->getStatistics();
            break;
        case 'nvidia':
            $data = (new Nvidia($gpustat_cfg))->getStatistics();
            break;
        default:
            print_r(Error::get(Error::CONFIG_SETTINGS_NOT_VALID));
    }
    $json = $data ;
    header('Content-Type: application/json');
    header('Content-Length:' . ES . strlen($json));
    echo $json;
    file_put_contents("/tmp/gpujson2","Time = ".date(DATE_RFC2822)."\n") ;
    file_put_contents("/tmp/gpujson2",$json."\n",FILE_APPEND) ;

}
