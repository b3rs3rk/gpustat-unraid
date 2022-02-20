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

#### AMD:
- UnRAID (6.9+)
  * RadeonTop plugin by @ich777 from Community Apps

Note: From an UnRAID console if `nvidia-smi` (NVIDIA), `intel_gpu_top` (Intel) or `radeontop` (AMD) cannot be found or run for any reason,
the plugin will fail for that vendor. If none of these commands exists, the plugin install will fail.

## Manual Installation
    - No longer supported, install from Community Apps unless beta testing

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

#### AMD:
    APU/GPU
    - GPU/Memory Utilization
    - Event Engine Utilization
    - Vertex Grouper and Tesselator Utilization
    - Texture Addresser Utilization
    - Shader Export/Interpolator Utilization
    - Sequencer Instruction Cache Utilization
    - Scan Converter Utilization
    - Primitive Assembly Utilization
    - Depth/Color Block Utilization
    - Graphics Translation Table Utilization
    - Memory/Shader Clocks
    - Temperature

    GPU Only
    - Power Draw
    - Fan Current/Max RPM

The bulk of this code was adapted from/inspired by @realies and @CyanLabs corsairpsu-unraid project!

## Contributor Thanks

    - @mlapaglia for his work on UI slider bars
    - @ich777 for his help with Intel iGPU testing
    - John_M for his help with AMD APU testing
    - @corgan2222 for adding nsfminer and Shinobi Pro app detections
