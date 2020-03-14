<?php

include 'lib/Main.php';
include 'lib/Nvidia.php';
include 'lib/Error.php';

use \gpustat\lib\Main;
use \gpustat\lib\Nvidia;
use \gpustat\lib\Error;

if (!isset($gpustat_cfg)) {
    $gpustat_cfg = Main::getSettings();
}

switch ($gpustat_cfg['VENDOR']) {
    case 'nvidia':
        // $gpu_inventory should be set if called from settings page code
        if (isset($gpu_inventory) && $gpu_inventory) {
            $gpu_data = (new Nvidia($gpustat_cfg))->getInventory();
        } else {
            (new Nvidia($gpustat_cfg))->getStatistics();
        }
        break;
    default:
        var_dump('test');
        new Error(Error::CONFIG_SETTINGS_NOT_VALID);
}
