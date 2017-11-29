<?php
define('APP_PATH', dirname(__FILE__));
$environment = isset($_SERVER['APP_ENV']) ? $_SERVER['APP_ENV'] : 'product';
define('APP_ENV', $environment);
require_once APP_PATH . '/config/envcnf/' . APP_ENV . '/ENVConst.php';

require_once dirname(APP_PATH) . '/weblib/require.php';
$appConfig = require_once(APP_PATH . '/config/UserConfig.php');
date_default_timezone_set('PRC');
return \frame\App::createApplication($appConfig); //返回
