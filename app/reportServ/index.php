<?php
define('APP_PATH', __DIR__);
$environment = isset($_SERVER['APP_ENV']) ? $_SERVER['APP_ENV'] : 'product';
define('APP_ENV', $environment);

require_once APP_PATH . '/config/envcnf/' . APP_ENV . '/ENVConst.php';
require_once dirname(APP_PATH) . '/weblib/require.php';

//load app Config
$appConfig = require(APP_PATH . '/config/UserConfig.php');

//init app router
$router = new \frame\base\Router();
$router = $router->loadRoute(APP_PATH . '/route/');

date_default_timezone_set('PRC');

StatisticsBuffer::init();

//require_once '../weblib/require.php';
$app = \frame\App::createApplication($appConfig, $router); //返回

return $app;

