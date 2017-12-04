<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件
//加密类 DECODE解密 ,ENCODE加密
function authcode($string, $operation = 'DECODE', $key = '', $expiry = 0)
{

    if ($operation == 'DECODE') {
        $string = str_replace('[a]', '+', $string);
        $string = str_replace('[b]', '&', $string);
        $string = str_replace('[c]', '/', $string);
    }
    $ckey_length   = 4;
    $key           = md5($key ? $key : config('globalsalt'));
    $keya          = md5(substr($key, 0, 16));
    $keyb          = md5(substr($key, 16, 16));
    $keyc          = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length) : substr(md5(microtime()), -$ckey_length)) : '';
    $cryptkey      = $keya . md5($keya . $keyc);
    $key_length    = strlen($cryptkey);
    $string        = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0) . substr(md5($string . $keyb), 0, 16) . $string;
    $string_length = strlen($string);
    $result        = '';
    $box           = range(0, 255);
    $rndkey        = array();
    for ($i = 0; $i <= 255; $i++) {
        $rndkey[$i] = ord($cryptkey[$i % $key_length]);
    }
    for ($j = $i = 0; $i < 256; $i++) {
        $j       = ($j + $box[$i] + $rndkey[$i]) % 256;
        $tmp     = $box[$i];
        $box[$i] = $box[$j];
        $box[$j] = $tmp;
    }
    for ($a = $j = $i = 0; $i < $string_length; $i++) {
        $a       = ($a + 1) % 256;
        $j       = ($j + $box[$a]) % 256;
        $tmp     = $box[$a];
        $box[$a] = $box[$j];
        $box[$j] = $tmp;
        $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
    }
    if ($operation == 'DECODE') {
        if ((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26) . $keyb), 0, 16)) {

            return substr($result, 26);
        } else {
            return '';
        }
    } else {
        $ustr = $keyc . str_replace('=', '', base64_encode($result));
        $ustr = str_replace('+', '[a]', $ustr);
        $ustr = str_replace('&', '[b]', $ustr);
        $ustr = str_replace('/', '[c]', $ustr);
        return $ustr;
    }
}


/**
 * 返回16位md5值
 *
 * @param string $str 字符串
 * @return string $str 返回16位的字符串
 */
function get_md5_16($str)
{
    return substr(md5($str), 8, 16);
}