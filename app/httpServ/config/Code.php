<?php

/**
 * Created by PhpStorm.
 * User: zengfanwei
 * Date: 2017/7/7
 * Time: 15:01
 */
class Code
{
    const SUCCESS = 0;
    const FAIL = 1;
    const SERVER_ERROR = 2;
    const DATABASE_ERROR = 3;
    const INVALID_PARAMS = 4;


    public static $message = [
        self::SUCCESS => '成功',
        self::FAIL => '失败',
        self::SERVER_ERROR => '服务器错误',
        self::DATABASE_ERROR => '数据库正忙请稍后再试',
        self::INVALID_PARAMS => "请求参数错误",
    ];

    public static function getError($code)
    {
        return isset(self::$message[$code]) ? self::$message[$code] : self::$message[self::SERVER_ERROR];
    }
}