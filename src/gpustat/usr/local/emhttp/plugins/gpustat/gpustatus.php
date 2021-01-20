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

define('ES', ' ');

include 'lib/Main.php';
include 'lib/Nvidia.php';
include 'lib/Intel.php';
include 'lib/Error.php';

use \gpustat\lib\Main;
use \gpustat\lib\Nvidia;
use \gpustat\lib\Intel;
use \gpustat\lib\Error;

if (!isset($gpustat_cfg)) {
    $gpustat_cfg = Main::getSettings();
}

// $gpu_inventory should be set if called from settings page code
if (isset($gpustat_inventory) && $gpustat_inventory) {
    $gpustat_cfg['inventory'] = true;
    // Settings page looks for $gpu_data specifically -- inventory all supported GPU types
    $gpustat_data = (new Nvidia($gpustat_cfg))->getInventory();
    $gpustat_data += (new Intel($gpustat_cfg))->getInventory();

} else {

    switch ($gpustat_cfg['VENDOR']) {
        case 'nvidia':
            (new Nvidia($gpustat_cfg))->getStatistics();
            break;
        case 'intel':
            (new Intel($gpustat_cfg))->getStatistics();
            break;
        default:
            print_r(new Error(Error::CONFIG_SETTINGS_NOT_VALID, ''));
    }
}
