<?php

/**
 * Created by PhpStorm.
 * User: zengfanwei
 * Date: 2017/6/28
 * Time: 15:21
 */
class ReportController extends BaseController
{
    /**
     *  最大日志buffer，大于这个值就写磁盘
     * @var integer
     */
    const MAX_LOG_BUFFER_SIZE = 3000;

    public $module;


    /**
     * 添加统计上报
     * @param data  time|remote_ip|module|interface|cost_time|success_flag|code|message
     */
    public function addlog()
    {
        $reqData = $this->request->data;
        $arr = explode('|', $reqData);
        if(empty($arr) || count($arr) < 2)
            $this->send('invalid data');

        $this->module = strtolower($arr[2]);

        if(!isset(StatisticsBuffer::$buffer[$this->module]))
            StatisticsBuffer::$buffer[$this->module] = [];

        if(count(StatisticsBuffer::$buffer[$this->module]) > self::MAX_LOG_BUFFER_SIZE) {
            StatisticsBuffer::witerModuleStatisticsToDisk($this->module);
        }

        array_push(StatisticsBuffer::$buffer[$this->module], $arr);

        $this->send('ok');
    }

}