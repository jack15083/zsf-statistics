<?php
use \frame\base\Protocol;
use \weblib\log\Log;
/**
 * Created by PhpStorm.
 * User: zengfanwei
 * Date: 2017/10/13
 * Time: 10:54
 */
class ReportProtocol extends Protocol
{
    public function onReceive($server, $clientId, $fromId, $data)
    {
        parent::onReceive($server, $clientId, $fromId, $data); // TODO: Change the autogenerated stub
        Log::error(print_r($data, true), 1, __METHOD__);
    }

    public function onConnect($server, $fd, $fromId)
    {
        parent::onConnect($server, $fd, $fromId); // TODO: Change the autogenerated stub
        Log::error('connect success', 1, __METHOD__);
    }

    public function onRequest($request, $response)
    {
        parent::onRequest($request, $response); // TODO: Change the autogenerated stub
        Log::error('request success', 1, __METHOD__);
    }

    public function onRoute($request)
    {
        parent::onRoute($request); // TODO: Change the autogenerated stub
        $buff = $request->buf;
        if(empty($buff)) return false;

        $buff = json_decode($buff);
        return new \frame\base\Route(ucfirst($buff->class) . 'Controller', ucfirst($buff->method), $buff->data);
    }
}