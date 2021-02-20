# gpustat-unraid
An UnRAID plugin for displaying GPU status

## Prerequisites

#### NVIDIA:
- UnRAID 6.9.0 Beta 34 and below needs one of the following:
  * ~~UNRAID-Nvidia Plugin with Nvidia Kernel Installed~~
  * Custom Kernel build with Nvidia drivers
- UnRAID 6.9.0 Beta 35 and newer needs one of the following:
  * Nvidia Plugin by @ich777 from Community Apps
  * Custom Kernel build with Nvidia drivers

For custom kernel builds, see the original post of the UnRAID-Kernel-Build-Helper [thread](https://forums.unraid.net/topic/92865-support-ich777-nvidiadvbzfsiscsimft-kernel-helperbuilder-docker/).

#### INTEL:
- UnRAID (All Versions)
  * Intel GPU TOP plugin by @ich777 from Community Apps

Note: From an UnRAID console if `nvidia-smi` (NVIDIA) or `intel_gpu_top` (Intel) cannot be found or run for any reason,
the plugin will fail for that vendor. If neither command exists, the plugin install will fail.

## Manual Installation
    - Make sure all pre-requisites are installed and configured as needed
    - Verify you have Unraid-Nvidia Build 6.7.1+ Installed
    - Within UnRAID Web UI navigate to Plugins -> Install Plugin
    - Enter https://raw.githubusercontent.com/b3rs3rk/gpustat-unraid/master/gpustat.plg
    - Select Install
    - Navigate to Settings -> GPU Statistics and select vendor (only choice is NVIDIA at this time)
    - Select Done
    - Navigate to Dashboard and look at the top of the leftmost panel for the GPU widget

If any issues occur, visit the support thread [here](https://forums.unraid.net/topic/89453-plugin-gpu-statistics/ "[PLUGIN] GPU Statistics").

## Current Support

#### NVIDIA:
    - GPU/Memory Utilization
    - GPU/Memory Clocks
    - Encoder/Decoder Utilization
    - PCI Bus Utilization
    - Temperature
    - Fan Utilization
    - Power Draw
    - Power State
    - Throttled (Y/N) and Reason for Throttle
    - Active Process Count

#### INTEL:
    - 3D Render Engine Utilization
    - Blitter Engine Utilization
    - Video Engine Utilization
    - VideoEnhance Engine Utilization
    - IMC Bandwidth Throughput
    - Power Draw and Power Demand (rc6 slider)
    - GPU Clock
    - Interrupts per Second

The bulk of this code was adapted from/inspired by @realies and @CyanLabs corsairpsu-unraid project!

## Contributor Thanks

    - Thanks to @mlapaglia for his work on UI slider bars!
    - Thanks to @ich777 for his help with iGPU testing setup!
