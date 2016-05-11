<?php
/**
 * The MIT License (MIT)
 * 
 * Copyright (c) 2016 Smartex.io Ltd.
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace Smartex\Util;

use Smartex\Math\Math;

/**
 * Utility class for encoding/decoding BASE-58 data
 *
 */
final class Base58
{
    /**
     * @var string
     */
    const BASE58_CHARS = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    /**
     * Encodes $data into BASE-58 format
     *
     * @param string $data
     *
     * @return string
     */
    public static function encode($data)
    {
        if (strlen($data) % 2 != 0 || strlen($data) == 0) {
            throw new \Exception('Invalid Length');
        }

        $code_string = self::BASE58_CHARS;
        $x = Util::decodeHex($data);
        $output_string = '';

        while (Math::cmp($x, '0') > 0) {
            $q = Math::div($x, 58);
            $r = Math::mod($x, 58);
            $output_string .= substr($code_string, intval($r), 1);
            $x = $q;
        }

        for ($i = 0; $i < strlen($data) && substr($data, $i, 2) == '00'; $i += 2) {
            $output_string .= substr($code_string, 0, 1);
        }

        $output_string = strrev($output_string);

        return $output_string;
    }

    /**
     * Decodes $data from BASE-58 format
     *
     * @param string $data
     *
     * @return string
     */
    public static function decode($data)
    {
        for ($return = '0', $i = 0; $i < strlen($data); $i++) {
            $current = strpos(self::BASE58_CHARS, $data[$i]);
            $return  = Math::mul($return, '58');
            $return  = Math::add($return, $current);
        }

        $return = Util::encodeHex($return);

        for ($i = 0; $i < strlen($data) && substr($data, $i, 1) == '1'; $i++) {
            $return = '00'.$return;
        }

        if (strlen($return) % 2 != 0) {
            $return = '0'.$return;
        }

        return $return;
    }
}
