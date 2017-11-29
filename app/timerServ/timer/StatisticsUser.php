<?php
use weblib\log\Log;
/**
 * 统计用户观看时间
 * User: zengfanwei
 * Date: 2017/7/7
 * Time: 17:42
 */
class StatisticsUser extends BaseTimer
{
    public $interval = ENVConst::INTERVAL_STAT_USER; //每隔5分钟
    public $redis;
    public $mysqli;
    public $db;

    /**
     * [__construct 构造函数，设定轮训时间]
     * @param [type] $workerId [description]
     */
    public function __construct($taskConf)
    {
        parent::__construct($taskConf, $this->interval, 'info');
    }

    /**
     * [run 执行函数]
     * @return [type] [description]
     */
    public function run($subTaskId)
    {
        Log::info('正在执行用户观看数据定时写入任务' . date("Y-m-d H:i:s"), 1, __METHOD__);
        $startTime = microtime('true');
        $redisConfig = ENVConst::getRedisConf();
        try
        {
            $redis = new \Redis();
            $redis->pconnect($redisConfig['ip'], $redisConfig['port']);
            $this->db = new \frame\database\dbObject(\ENVConst::getDBConf());
            $this->mysqli = $this->db->mysqli();
        }
        catch (\Exception $e)
        {
            Log::debug(__LINE__ . $e->getMessage(), 1, __METHOD__);
            $this->db->free();
            return false;
        }

        $i = 0;
        $newData = [];
        while (($cache = $redis->lPop('statistics_list')) && $i < 3000)
        {
            $data = unserialize($cache);
            $data['create_date'] = date("Y-m-d H:i:s");
            foreach ($data as $key => $value)
            {
                $data[$key] =  "'" . $this->mysqli->real_escape_string($value) . "'";
            }

            $newData[$i] = $data;
            $i++;
        }

        $this->buildSave($newData);
        $this->db->free();
        $endTime = microtime(true);
        Log::info('执行结束' . date("Y-m-d H:i:s") . ' 运行耗时：' . ($endTime - $startTime) . 's 内存占用：' .
            memory_get_peak_usage() / 1024 . ' kb', 1, __METHOD__);
    }

    /**
     * 保存或替换数据
     * @param $data
     * @return bool
     */
    public function buildSave($data)
    {
        if(empty($data)) return false;
        $arr = $keys = []; $i = 0;
        foreach ($data as $key => $row)
        {
            if($i == 0) $keys = array_keys($row);
            $arr[$key] = '(' . implode(',', $row) . ')';
            $i++;
        }

        $values = implode(',', $arr);

        $insertKeys   = '(' . implode(',', $keys) . ')';

        $insertSql = 'INSERT INTO `home_play_data_detail` ' . $insertKeys . ' values ' . $values;
        $this->query($insertSql);
    }
}