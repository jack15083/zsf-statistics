<?php

/**
 * 执行看课详情定时任务
 * Created by PhpStorm.
 * User: zengfanwei
 * Date: 2017/7/14
 * Time: 14:47
 */
use weblib\log\Log;

class StatisticsDetail extends  BaseTimer
{
    public $interval = ENVConst::INTERVAL_STAT_DETAIL; //每隔5分钟
    public static $running = false;
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

    public function run($subTaskId)
    {
        if(!$this->checkRun()) return false;
        Log::info('正在执行用户观看数据详情写入任务' . date("Y-m-d H:i:s"), 1, __METHOD__);
        $startTime = microtime('true');
        $redisConfig = ENVConst::getRedisConf();
        try
        {
            $redis = new \Redis();
            $redis->pconnect($redisConfig['ip'], $redisConfig['port']);
            $this->db = new \frame\database\dbObject(\ENVConst::getDBConf());
            $this->mysqli = $this->db->mysqli();
            $this->redis = $redis;
        }
        catch (\Exception $e)
        {
            Log::debug(__LINE__ . $e->getMessage(), 1, __METHOD__);
            $this->breakRun();
            return false;
        }

        $keys = [];
        while ($cache = $redis->lPop('statistics_detail_cache_key'))
        {
            $keys[$cache] = 1;
            //每次最多处理3000条数据
            if(count($keys) > 3000)
                break;
        }
        $cacheKeys = array_keys($keys);
        foreach ($cacheKeys as $key)
        {
            $data = explode('_', $key);
            list($prefix, $month, $day, $memberId, $courseId, $lessonId) = $data;
            $detail = $redis->get($key);
            if(empty($detail))
            {
                Log::debug('获取观看数据详情为空', __LINE__, __METHOD__);
                continue;
            }

            //获取今天的看课详情时间区间
            $detail = unserialize($detail);
            if(empty($detail))
            {
                Log::debug('今天去重看课详情为空', __LINE__, __METHOD__);
                continue;
            }
            $list = $this->generateList($detail);
            $list = $this->sortByField($list, 0, 'SORT_ASC');

            if(empty($list))
            {
                Log::debug('今天去重看课区间为空', __LINE__, __METHOD__);
                continue;
            }

            //获取今天不去重的看课时间
            $playTime = $this->getPlayTime($list);
            if($playTime == 0)
            {
                Log::debug('今天不去重看课时长为0', __LINE__, __METHOD__);
                continue;
            }

            //获取今天看课时间区间去重
            $uniqueList = [];
            $list = $this->getUniqueList($list, $uniqueList);

            //获取截止到昨天的看课时长去重
            $lastList = $this->getLastDetail($memberId, $courseId, $lessonId);
            $lastPlayTimeDis = $this->getPlayTime($lastList);

            //获取截止到当前的看课时间去重
            $uniqueList = [];
            $curList = array_merge($list, $lastList);
            $curList = $this->sortByField($curList, 0, 'SORT_ASC');
            $curList = $this->getUniqueList($curList, $uniqueList);
            $curPlayTimeDis = $this->getPlayTime($curList);
            //截止到目前看课详情区间 去重
            $jsonDetail = json_encode($curList);

            //获取今天的看课时长去重
            $todayPlayTimeDis = $curPlayTimeDis - $lastPlayTimeDis;
            if($todayPlayTimeDis < 0) $todayPlayTimeDis = 0;

            $saveData['member_id'] = $memberId;
            $saveData['course_id'] = $courseId;
            $saveData['course_lesson_id'] = $lessonId;
            $saveData['play_time'] = $playTime;
            $saveData['play_time_distinct'] = $todayPlayTimeDis;
            $saveData['month'] = $month;
            $saveData['total_time'] = $this->getLessonTime($lessonId);
            $saveData['day']   = $day;
            $saveData['update_time'] = "'" . date("Y-m-d H:i:s") . "'";
            $saveData['create_date'] = "'" . date("Y-m-d H:i:s") . "'";
            $saveData['play_detail'] = "'" . $this->mysqli->real_escape_string($jsonDetail) . "'";

            if(empty($saveData['total_time']))
            {
                Log::debug('获取视频总时长时间为0 lessonId' . $lessonId , __LINE__, __METHOD__);
                continue;
            }

            $this->buildSave($saveData);
        }

        $this->breakRun();
        $endTime = microtime(true);
        Log::info('执行结束' . date("Y-m-d H:i:s") . ' 运行耗时：' . ($endTime - $startTime) . 's 内存占用：' .
            memory_get_peak_usage() / 1024 . ' kb', 1, __METHOD__);
        return true;
    }

    /**
     * 获取课程时长
     * @param $lessonId
     * @return int
     */
    private function getLessonTime($lessonId)
    {
        $sql = "select duration from home_course_lesson where id = " . $lessonId;
        $res = $this->query($sql);
        if(empty($res)) return 0;

        $row = $res->fetch_assoc();
        if(empty($row['duration'])) return 0;

        return $row['duration'];
    }

    /**
     * 获取截止到昨天的看课详情数据
     * @param $memberId
     * @param $courseId
     * @param $lessonId
     * @return array|mixed
     */
    private function getLastDetail($memberId, $courseId, $lessonId)
    {
        //昨天
        $yesterDayTime = strtotime("-1 day");
        $month = date("Ym", $yesterDayTime);
        $day   = date("d", $yesterDayTime);

        $sql = "select play_detail from home_play_data where member_id = " . $memberId .
            " and month <= " . $month . " and day <= " . $day . " and course_id = " . $courseId . " and course_lesson_id = " .
            $lessonId . " and play_time != 0 order by month, day desc limit 1";

        $res = $this->query($sql);
        if(empty($res)) return [];
        $row = $res->fetch_assoc();

        if(!empty($row['play_detail'])) $list = json_decode($row['play_detail'], true);
        if(empty($list)) return [];

        return $list;
    }

    /**
     * 保存或替换数据
     * @param $data
     * @return bool
     */
    public function buildSave($data)
    {
        if(empty($data)) return false;

        $keys = array_keys($data);
        $values = '(' . implode(',', $data) . ')';

        $insertKeys = '(' . implode(',', $keys) . ')';

        $updateString = '';
        foreach ($keys as $key)
        {
            if($key == 'create_date') continue;
            //if($key == 'play_time') $updateString .= "`$key` = $key + VALUES(" . $key . "),";
            else $updateString .= "`$key` = VALUES(" . $key . "),";
        }

        $updateString = preg_replace('/,$/', '', $updateString);

        $insertSql = 'INSERT INTO `home_play_data` ' . $insertKeys . ' values ' . $values . ' ON DUPLICATE KEY UPDATE ' . $updateString;;
        $this->query($insertSql);
    }

    /**
     * 获取看课详情不去重区间
     * @param $data
     * @return array|mixed
     */
    private function processDetailList($data)
    {
        if(empty($data)) return [];
        $list = $this->generateList($data);
        $list = $this->sortByField($list, 0, 'SORT_ASC');
        $uniqueList = [];
        $list = $this->getUniqueList($list, $uniqueList);
        return $list;
    }

    private function generateList($data)
    {
        $list = [];
        foreach ($data as $key => $string)
        {
            $arr = explode(',', $string);
            $list[$key][0] = (int) $arr[0];
            $list[$key][1] = (int) $arr[1];
        }
        return $list;
    }

    /**
     * 计算看视频时长
     * @param $list
     * @return int
     */
    private function getPlayTime($list)
    {
        $time = 0;
        foreach ($list as $key => $row)
        {
            $time += $row[1] - $row[0];
        }

        return $time;
    }

    /**
     * 获取不重复的看课时间区间
     * @param $list
     * @param $uniqueList
     * @return array
     */
    private function getUniqueList($list, &$uniqueList)
    {
        if(empty($list)) return $uniqueList;
        $first = array_shift($list);
        $newList = [];
        $hasRepeat = false;
        for($i = 0; $i < count($list); $i++)
        {
            $current = $list[$i];
            //结束时间比当前开始时间大，合并时间区间
            if(($first[1] + 1) >= $current[0])
            {
                $hasRepeat = true;
                $row[0] = min($first[0], $current[0]);
                $row[1] = max($first[1], $current[1]);
                $first = $row;
            }
            else
            {
                $newList[] = $list[$i];
            }
        }

        $uniqueList[] = $first;
        //没有重复区间返回
        if(!$hasRepeat && empty($list))
        {
            if(!empty($newList)) $uniqueList = array_merge($uniqueList, $newList);
            return $uniqueList;
        }

        return $this->getUniqueList($newList, $uniqueList);
    }

    /**
     * 二维数组按某个字段排序
     * @param $data
     * @param $field
     * @param string $sort
     * @return mixed
     */
    protected function sortByField($data, $field, $sort = 'SORT_DESC')
    {
        if(empty($data))
            return [];

        $arrSort = array();
        foreach ($data as $id => $row)
        {
            foreach ($row as $key => $value)
            {
                $arrSort[$key][$id] = $value;
            }
        }

        array_multisort($arrSort[$field], constant($sort), $data);
        return $data;
    }

    /**
     * 检查任务运行条件
     * @return bool
     */
    private function checkRun()
    {
        if(self::$running)
        {
            Log::debug('正在执行中', __LINE__, __METHOD__);
            return false;
        }

        self::$running = true;
        return true;
    }

    /**
     * 结束运行
     * @return bool
     */
    private function breakRun()
    {
        $this->db->free();
        self::$running = false;
        return false;
    }
}