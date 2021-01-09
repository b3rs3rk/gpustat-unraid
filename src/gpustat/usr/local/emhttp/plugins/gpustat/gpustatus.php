<?php

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
            new Error(Error::CONFIG_SETTINGS_NOT_VALID, '', false);
    }
}
