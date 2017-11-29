<?php
namespace frame;

use frame\base\Router;
use frame\base\UserConfig;
class App
{
    public static function createApplication($config = array(), Router $router) {
        //初始化log组件
        $userConfig = new UserConfig();
        if(!empty($config)) $userConfig->setConfig($config);

        $logConfig = $userConfig->getConfig('log');
        \frame\log\Log::create($logConfig);
        $register = $userConfig->getConfig('register');
        $Serv = new \frame\core\Event();

        if (isset($register['protocol']) && class_exists($register['protocol'])) {
            $protocol = new $register['protocol']($router);
            if (is_subclass_of($protocol, 'frame\base\Protocol')) {
                $Serv->setRegister('protocol', $protocol);
            } else {
                return false;
            }
        } else {//使用默认的路由函数
            $protocol = new \frame\base\RouteRegex($router);
            $Serv->setRegister('protocol', $protocol);
        }

        return $Serv;
    }

}