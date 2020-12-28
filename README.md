# gpustat-unraid
An UnRAID plugin for displaying GPU status

## Manual Installation
    - Verify you have Unraid-Nvidia Build 6.7.1+ Installed
    - Within UnRAID Web UI navigate to Plugins -> Install Plugin
    - Enter https://raw.githubusercontent.com/mlapaglia/gpustat-unraid/master/gpustat.plg
    - Select Install
    - Navigate to Settings -> GPU Statistics and select vendor (only choice is NVIDIA at this time)
    - Select Done
    - Navigate to Dashboard and look at the top of the leftmost panel for the GPU widget

If any issues occur, visit the support thread [here](https://forums.unraid.net/topic/89453-plugin-gpu-statistics/ "[PLUGIN] GPU Statistics").

## Current Support

    NVIDIA:
        * GPU/Memory Utilization
        * GPU/Memory Clocks
        * Encoder/Decoder Utilization
        * Temperature
        * Fan Utilization
        * Power Draw
        * Power State
        * Throttled (Y/N) and Reason for Throttle
        * Active Processes
    
The bulk of this code was adapted from/inspired by @realies and @CyanLabs corsairpsu-unraid project!
