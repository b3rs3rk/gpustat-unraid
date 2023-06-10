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
    $gpustat_data = array_merge((new Nvidia($gpustat_cfg))->getInventorym(), (new Intel($gpustat_cfg))->getInventory(), (new AMD($gpustat_cfg))->getInventorym());
} else {


$array=json_decode($_GET['gpus'],true) ;


    $data = array() ;
    foreach ($array as $gpu) {
        $gpustat_cfg["VENDOR"] = $gpu['vendor'] ;
        $gpustat_cfg["GPUID"] = $gpu['guid'] ;

    switch ($gpu['vendor']) {
        case 'amd':
            $return=(new AMD($gpustat_cfg))->getStatistics();
            $decode = json_decode($return,true);
            $decode["panel"] = $gpu['panel'] ;
            $data[$gpu["id"]] = $decode;
            break;
        case 'intel':
            $return=(new Intel($gpustat_cfg))->getStatistics();
            $decode = json_decode($return,true);
            $decode["panel"] = $gpu['panel'] ;
            $data[$gpu["id"]] = $decode;
            break;
        case 'nvidia':
            $return = (new Nvidia($gpustat_cfg))->getStatistics() ;
            $decode = json_decode($return,true);
            $decode["panel"] = $gpu['panel'] ;
            $data[$gpu["id"]] = $decode;
            break;
        default:
            print_r(Error::get(Error::CONFIG_SETTINGS_NOT_VALID));
    }
}
$json=json_encode($data) ;
#Test data
#$json='{"00:02.0":{"clock":100,"fan":50,"memclock":500,"memutil":55,"memused":55,"power":"100W","powermax":500,"rxutil":50,"txutil":60,"temp":50,"tempmax":200,"util":"40%","vendor":"Intel","name":"AlderLake-S GT1","3drender":"50%","blitter":"50%","interrupts":100,"powerutil":"10%","video":"20%","videnh":"30%","panel":1},"03:00.0":{"clock":0,"fan":"N\/A","memclock":"N\/A","memutil":"N\/A","memused":"N\/A","power":"N\/A","powermax":"N\/A","rxutil":"N\/A","txutil":"N\/A","temp":"N\/A","tempmax":"N\/A","util":"0%","vendor":"Intel","name":"DG2 [Arc A770]","3drender":"0%","blitter":"0%","interrupts":0,"powerutil":"0%","video":"0%","videnh":"0%","panel":2},"08:00.0":{"clock":"810","fan":"30%","memclock":"2808","memutil":"50%","memused":"50","power":"28W","powermax":"87","rxutil":50,"txutil":60,"temp":"41 \u00b0C","tempmax":"101 \u00b0C","util":"77%","vendor":"NVIDIA","name":"Quadro K4000","clockmax":"810","memclockmax":"2808","memtotal":"3018","encutil":"50%","decutil":"50%","pciemax":500,"perfstate":"P0","throttled":"No","thrtlrsn":"","pciegen":2,"pciegenmax":2,"pciewidth":1,"pciewidthmax":16,"sessions":0,"uuid":"GPU-ef6c0299-f1bc-7b5c-5291-7cd1a012f8bd","plexusing":true,"plexmem":0,"plexcount":0,"jellyfinusing":true,"jellyfinmem":100,"jellyfincount":2,"handbrakeusing":false,"handbrakemem":0,"handbrakecount":0,"embyusing":false,"embymem":0,"embycount":0,"tdarrusing":false,"tdarrmem":0,"tdarrcount":0,"unmanicusing":true,"unmanicmem":0,"unmaniccount":0,"dizquetvusing":false,"dizquetvmem":0,"dizquetvcount":0,"ersatztvusing":false,"ersatztvmem":0,"ersatztvcount":0,"fileflowsusing":false,"fileflowsmem":0,"fileflowscount":0,"frigateusing":false,"frigatemem":0,"frigatecount":0,"deepstackusing":false,"deepstackmem":0,"deepstackcount":0,"nsfminerusing":false,"nsfminermem":0,"nsfminercount":0,"shinobiprousing":false,"shinobipromem":0,"shinobiprocount":0,"foldinghomeusing":false,"foldinghomemem":0,"foldinghomecount":0,"appssupp":["plex","jellyfin","handbrake","emby","tdarr","unmanic","dizquetv","ersatztv","fileflows","frigate","deepstack","nsfminer","shinobipro","foldinghome"],"panel":3},"0c:00.0":{"clock":2110.5,"fan":200,"memclock":2220.1,"memutil":"21.2%","memused":47.51,"power":50,"powermax":200,"rxutil":"N\/A","txutil":67,"temp":"38 \u00b0C","tempmax":105,"util":"90%","vendor":"AMD","name":"Radeon RX 6400\/6500 XT\/6500M","event":"80%","vertex":"70%","texture":"60%","shaderexp":"50%","sequencer":"40%","shaderinter":"30%","scancon":"30%","primassem":"30%","depthblk":"30%","colorblk":"30%","gfxtrans":"44.1%","transused":11.57,"memclockutil":"9.6%","clockutil":"21.6%","tempunit":"C","fanmax":5550,"voltage":77.7,"voltageunit":"V","panel":4}}' ;
header('Content-Type: application/json');
header('Content-Length:' . ES . strlen($json));
echo $json;
file_put_contents("/tmp/gpujson","Time = ".date(DATE_RFC2822)."\n") ;
file_put_contents("/tmp/gpujson",$json."\n",FILE_APPEND) ;
}
