<?php

/**
 * Created by PhpStorm.
 * User: zengfanwei
 * Date: 2017/10/16
 * Time: 14:28
 */
use \weblib\log\Log;
class StatisticsBuffer
{
    public static $init_statistics = false;
    public static $buffer = [];
    /**
     * 多长时间写一次数据到磁盘s
     * @var integer
     */
    const WRITE_PERIOD_LENGTH = 60;

    /**
     * 多长时间清一次磁盘s
     * @var integer
     */
    const  CLEAN_TIME_INTERVAL = 300;

    const  EXPIRE_TIME = 604800; //一周


    public static function init()
    {
        if(self::$init_statistics) return;
        self::intervalWriteToDisk();
    }

    /**
     * 将统计数据写入磁盘
     * @return void
     */
    public static function writeStatisticsToDisk()
    {
        $modules = array_keys(self::$buffer);
        Log::debug('start write modules ' . print_r($modules, true),  __LINE__, __METHOD__);
        foreach ($modules as $module)
        {
            self::witerModuleStatisticsToDisk($module);
        }
    }

    public static function witerModuleStatisticsToDisk($module)
    {
        if(empty($module)) return ;

        $module = strtolower($module);
        Log::debug('start to write ', __LINE__, __METHOD__);
        if(empty(self::$buffer[$module])) {
            Log::debug('empty buffer', __LINE__, __METHOD__);
            return;
        }

        if(!is_dir(Config::STATICS_PATH . $module)) {
            mkdir(Config::STATICS_PATH . $module);
        }

        $file_dir = Config::STATICS_PATH . $module . '/' . date("Ymd") . '/';
        if(!is_dir($file_dir) && !mkdir($file_dir, 0777, true))
        {
            Log::error('Cannot create dir ' . $file_dir, __LINE__, __METHOD__);
            return;
        }

        //每5分钟记录一次
        $file_dir .= self::getFileName();
        $temp_str = '';
        while ($buffer = array_shift(self::$buffer[$module]))
        {
            $temp_str .= implode("\t", $buffer) . "\n";
        }

        file_put_contents($file_dir, $temp_str, FILE_APPEND | LOCK_EX);
        Log::debug('success write log buffer');
    }

    public static function intervalWriteToDisk()
    {
        Log::debug('timer to write to disk start:' . self::WRITE_PERIOD_LENGTH, 1, __METHOD__);
        swoole_timer_tick(self::WRITE_PERIOD_LENGTH * 1000, function() {
            Log::debug('write statics data to disk', __LINE__, 'intervalWriteToDisk');
            self::writeStatisticsToDisk();
        });
    }

    /**
     * @param $timestamp
     * @return string
     */
    public static function getFileName($timestamp = '')
    {
        $time_break_num = (int) ( date("s", $timestamp) / 5) * 5;
        $fileName = date("H") . ($time_break_num == 13 ? 12 : $time_break_num) . '.log';

        return $fileName;
    }

    public static function intervalCleanDisk()
    {
        Log::debug(__METHOD__ . 'timer to write to disk start:' . self::WRITE_PERIOD_LENGTH,1, __METHOD__);
        swoole_timer_tick(self::CLEAN_TIME_INTERVAL * 1000, function() {
            self::clearDisk(Config::STATICS_PATH, self::EXPIRE_TIME);
        });

    }

    /**
     * 清除磁盘数据
     * @param string $file
     * @param int $exp_time
     */
    public static function clearDisk($file = null, $exp_time = 86400)
    {
        $time_now = time();
        if(is_file($file))
        {
            $mtime = filemtime($file);
            if(!$mtime)
            {
                Log::error('file mtime faile', 1, __METHOD__);
                return;
            }

            if($time_now - $mtime > $exp_time)
            {
                unlink($file);
            }
            return;
        }

        foreach (glob($file . "/*") as $file_name)
        {
            self::clearDisk($file_name, $exp_time);
        }
    }
}