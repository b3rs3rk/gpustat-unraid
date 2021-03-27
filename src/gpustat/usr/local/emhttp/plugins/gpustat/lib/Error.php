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

namespace gpustat\lib;

/**
 * Class Error
 * @package gpustat\lib
 */
class Error
{
    // Unknown Errors Placeholder
    const UNKNOWN                   = ['code' => 100, 'message' => 'An unknown error occurred.'];
    // Configuration Errors
    const CONFIG_SETTINGS_NOT_VALID = ['code' => 200, 'message' => 'Configuration file contains invalid settings.'];
    // Vendor Utility Errors
    const VENDOR_UTILITY_NOT_FOUND  = ['code' => 300, 'message' => 'Vendor utility not found.'];
    const VENDOR_DATA_NOT_RETURNED  = ['code' => 301, 'message' => 'Vendor command returned no data.'];
    const VENDOR_DATA_BAD_PARSE     = ['code' => 302, 'message' => 'Vendor command returned unparseable data.'];
    const VENDOR_DATA_NOT_ENOUGH    = ['code' => 303, 'message' => 'Vendor data valid, but not enough received.'];
    // JSON Response Errors
    const BAD_ARRAY_DATA            = ['code' => 500, 'message' => 'Bad array data received.'];

    /**
     * Returns the error message
     *
     * @param array $error
     * @param string $extra
     * @return array
     */
    public static function get(array $error = self::UNKNOWN, string $extra = ''): array
    {
        return [
            'code'      => $error['code'],
            'message'   => $error['message'],
            'extra'     => $extra,
        ];
    }
}