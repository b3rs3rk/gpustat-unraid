# gpustat-unraid
An UnRAID plugin for displaying GPU status

## Manual Installation
    - Verify you have Unraid-Nvidia Build 6.7.1+ Installed
    - Within UnRAID Web UI navigate to Plugins -> Install Plugin
    - Enter https://raw.githubusercontent.com/b3rs3rk/gpustat-unraid/master/gpustat.plg
    - Select Install
    - Navigate to Settings -> GPU Statistics and select vendor (only choice is nVidia at this time)
    - Select Done
    - Navigate to Dashboard and look at the top of the leftmost panel for the GPU widget

If any issues occur, visit the support thread [here](https://forums.unraid.net/topic/89453-plugin-gpu-statistics/ "[PLUGIN] GPU Statistics").

## Current Support

    NVIDIA: GPU\Memory Utilization, Temperature, Fan Speed, Power Draw, Active Processes
    
The bulk of this code was adapted from/inspired by @realies and @CyanLabs corsairpsu-unraid project!