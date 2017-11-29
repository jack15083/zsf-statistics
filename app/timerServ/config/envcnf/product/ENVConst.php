<?php

class ENVConst
{
    const  INTERVAL_STAT_CLASS = 300;
    const  INTERVAL_STAT_DETAIL = 300;
    const  INTERVAL_STAT_USER = 300;

    public static function getDBConf()  //一些配置
    {
        return array(
            'host' => '188.188.189.105',
            'username' => 'support_user',
            'password' => 'efnaierhf)^35e(vnie',
            'db'=> 'kaikela_market',
            'port' => 3306,
            'prefix' => '',
            'charset' => 'utf8',
            'instance' => 'users',
            'pool' => [
                'max' => 5, //最大连接数15
                'min' => 1, //最小连接数
                'timeout' => 30  //连接过期时间30S
            ]
        );
    }

    public static function getIkukoDBConf()
    {
        return array(
            'host' => '188.188.189.102',
            'username' => 'php_user',
            'password' => 'e89nierye)88^',
            'db'=> 'kuko',
            'port' => 3306,
            'prefix' => '',
            'charset' => 'utf8',
            'instance' => 'ikukouser',
            'pool' => [
                'max' => 5, //最大连接数15
                'min' => 1, //最小连接数
                'timeout' => 30  //连接过期时间30S
            ]
        );
    }

    public static function getRedisConf()
    {
        return [
            'ip' => '127.0.0.1',
            'port' => 6379,
            'password' => '',
        ];
    }

} 