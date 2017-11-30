<?php

$router->get('/test', 'AjaxController@actionTest');
$router->get('/getdata', 'AjaxController@getStaticsData');
$router->post('/getdata', 'AjaxController@getStaticsData');