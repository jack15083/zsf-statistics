<?php
/**
 * 统计班级用户观看时间
 */

use weblib\log\Log;
class Statistics extends BaseTimer {

    public $redis;
    public static $running = false;
    public $mysqli;
    public $db;

    //每隔5分钟
    public $interval = ENVConst::INTERVAL_STAT_CLASS;

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
        if(!$this->checkRun())
            return false;

        try
        {
            $this->db = new \frame\database\dbObject(\ENVConst::getDBConf());
            $this->mysqli = $this->db->mysqli();
            $redis = new \Redis();
            $redisConfig = ENVConst::getRedisConf();
            $redis->pconnect($redisConfig['ip'], $redisConfig['port']);
        }
        catch (\Exception $e)
        {
            Log::error(__LINE__ . $e->getMessage(), 1, __METHOD__);
            return $this->breakRun();
        }

        Log::info('正在执行' . date("Y-m-d H:i:s"), 1, __METHOD__);
        self::$running = true;
        $startTime = microtime(true);

        /*$memberIds = []; $i = 0;
        while (($cache = $redis->lPop('statistics_user_list')) && $i < 3000)
        {
            if(!isset($memberIds[$cache])) $i++;
            $memberIds[$cache] = 1;
            $i++;
        }

        if(empty($memberIds))
        {
            Log::debug('用户列表为空', __LINE__, __METHOD__);
            return $this->breakRun();
        }

        $memberIds = array_keys($memberIds);*/

        $gradeYear =  $this->processGradeYear();
        //按班级从下往上计算
        /*$fetchClassSql = 'select school_id, grade, class from home_member_child  where member_id in( ' . implode(',', $memberIds)
            . ') and grade in (' . $gradeYear['9'] . ',' . $gradeYear['8'] . ',' . $gradeYear['7'] . ') group by school_id, grade, class';*/

        $fetchClassSql = 'select school_id, grade, class from home_member_child  where grade in (' . $gradeYear['9'] . ',' . $gradeYear['8'] . ',' . $gradeYear['7'] .
            ') group by school_id, grade, class';
        $result = $this->query($fetchClassSql);
        if(!$result)
        {
            return $this->breakRun();
        }

        $staticsDate = $this->getStaticsDate();

        while ($row = $result->fetch_assoc())
        {
            $schoolId = $row['school_id'];
            $grade = (int) $row['grade'];
            $class = (int) $row['class'];

            if(!$schoolId || !$grade || !$class)
                continue;

            //查询班级所有家长
            $fetchMemberIdSql = "select hmc.member_id, hm.district, hm.province, hm.city from home_member_child hmc 
                left join home_member hm  on hmc.member_id = hm.id
                where hmc.school_id = $schoolId and hmc.grade = $grade and hmc.class = '$class' ";

            $resultMember = $this->query($fetchMemberIdSql);
            $memberIds = [];
            //$memberData = [];

            $i = $provinceId = $cityId = $areaId = 0;
            while ($rowMember = $resultMember->fetch_assoc())
            {
                if($i == 0)
                {
                    $provinceId = $rowMember['province'];
                    $cityId   = $rowMember['city'];
                    $areaId   = $rowMember['district'];
                }

                $memberId = $rowMember['member_id'];
                $memberIds[] = $memberId;
                $i++;
            }

            if(empty($memberIds) || !$provinceId || !$cityId || ! $areaId)
            {
                continue;
            }

            $memberIdsStr = implode(',',$memberIds);
            $fetchClassDataSql = "select * from home_play_data where member_id in ($memberIdsStr)";
            $resultView = $this->query($fetchClassDataSql);

            $default = [
                'play_time' => 0,  //总观看时长
                'play_time_distinct' => 0,
                'course' => 0, //完成课节数
                'parents' => 0
            ];

            $ret['total']    = $ret['lastMonth'] = $ret['lastWeek'] = $ret['curWeek'] = $default;
            $course = $parents = [
                'total' => [],
                'lastMonth' => [],
                'lastWeek' => [],
                'curWeek' => [],
            ];

            //计算观看总时长，及完成课节数
            while ($rowView = $resultView->fetch_assoc())
            {
                $this->getMemberViewData('lastMonth', $rowView, $staticsDate, $course, $ret, $parents);
                $this->getMemberViewData('lastWeek', $rowView, $staticsDate, $course, $ret, $parents);
                $this->getMemberViewData('total', $rowView, $staticsDate, $course, $ret, $parents);
                $this->getMemberViewData('curWeek', $rowView, $staticsDate, $course, $ret, $parents);
            }

            $savedata['play_time']        = $ret['total']['play_time_distinct'];
            $savedata['play_time_repeat'] = $ret['total']['play_time'];
            //播放时长为0不记录
            if($savedata['play_time_repeat'] == 0)
            {
                continue;
            }

            $savedata['last_month_play_time'] = $ret['lastMonth']['play_time_distinct'];
            $savedata['last_week_play_time']  = $ret['lastWeek']['play_time_distinct'];
            $savedata['cur_week_play_time']   = $ret['curWeek']['play_time_distinct'];

            $savedata['complete_lessons']            = $ret['total']['course'];
            $savedata['last_month_complete_lessons'] = $ret['lastMonth']['course'];
            $savedata['last_week_complete_lessons']  = $ret['lastWeek']['course'];
            $savedata['cur_week_complete_lessons']   = $ret['curWeek']['course'];

            $savedata['member_number']             = $ret['total']['parents'];
            $savedata['last_month_member_number']  = $ret['lastMonth']['parents'];
            $savedata['last_week_member_number']   = $ret['lastWeek']['parents'];
            $savedata['cur_week_member_number']    = $ret['curWeek']['parents'];

            $savedata['update_time']        = microtime(true);
            $savedata['create_time']        = microtime(true);
            $savedata['province_id']        = $provinceId;
            $savedata['city_id']            = $cityId;
            $savedata['area_id']            = $areaId;
            $savedata['grade']              = $grade;
            $savedata['class']              = $class;
            $savedata['school_id']          = $schoolId;

            //$this->save($savedata);
            $multiData[] = $savedata;
            $this->saveMulti($multiData);
        }

        $this->saveMulti($multiData, true);

        $this->breakRun();
        $endTime = microtime(true);
        Log::info('执行结束' . date("Y-m-d H:i:s") . ' 运行耗时：' . ($endTime - $startTime) . 's 内存占用：' .
            memory_get_peak_usage() / 1024 . ' kb', 1, __METHOD__);

	}

    public function processGradeYear()
    {
        $year = date("Y") + (date("m") > 7 ? 7 : 6);
        $gradeYear = [
            '7' => ($year - 7),
            '8' => ($year - 8),
            '9' => ($year - 9),
        ];

        return $gradeYear;
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
     * 保存多条
     * @param $data
     * @param bool $end
     */
    private function saveMulti(&$data, $end = false)
    {
        if(empty($data)) return;

        if(count($data) != 2000 && !$end)
            return;

        $row = $data[0];
        $keys = array_keys($row);
        $updateString = '';
        foreach ($keys as $key)
        {
            if($key == 'create_time') continue;
            $updateString .= "`$key` = VALUES(" . $key . "),";
        }

        $updateString = preg_replace('/,$/', '', $updateString);
        $insertKeys   = '(' . implode(',', $keys) . ')';

        $arr = [];
        foreach ($data as $key => $row)
        {
            $arr[$key] = '(' . implode(',', $row) . ')';
        }

        $values = implode(',', $arr);

        $insertSql = 'INSERT INTO `home_play_statistics` ' . $insertKeys . ' values ' . $values . ' ON DUPLICATE KEY UPDATE ' . $updateString;
        $this->query($insertSql);
        $data = [];
    }


    /**
     * 获取统计需要的日期
     * @return mixed
     */
	private function getStaticsDate()
    {
        $year  = date("Y");
        $month = date("m");
        $day   = date("d");
        $week  = date('w');
        $date['lastMonth'] = $this->getLastMonth($year, $month);
        $date['lastWeekFirstDay'] = date("Ymd",mktime(0, 0 , 0,$month,$day - $week + 1 - 7, $year));
        $date['lastWeekLastDay'] = date("Ymd",mktime(23, 59, 59, $month, $day - $week + 7 - 7,$year));

        $date['curWeekFirstDay'] =  date("Ymd",mktime(0, 0 , 0,$month,$day - $week + 1, $year));
        $date['curWeekLastDay']  = date("Ymd",mktime(23,59,59,$month,$day - $week + 7, $year));
        return $date;
    }

    /**
     * 计算个人观看数据
     * @param $type
     * @param $data
     * @param $date
     * @param $course
     * @param $ret
     * @param $parents
     */
	private function getMemberViewData($type, $data, $date, &$course, &$ret, &$parents)
    {
        if($type == 'lastMonth' && $data['month'] != $date['lastMonth'])
            return;

        if($data['day'] < 10)
        {
            $data['day'] = '0' . $data['day'];
        }

        $Ymd = $data['month'] . $data['day'];
        if($type == 'lastWeek' && ($Ymd > $date['lastWeekLastDay'] || $Ymd < $date['lastWeekFirstDay']) )
            return;

        if($type == 'curWeek' && ($Ymd > $date['curWeekLastDay'] || $Ymd < $date['curWeekFirstDay']) )
            return;

        $ret[$type]['play_time'] += $data['play_time'];
        $ret[$type]['play_time_distinct'] += $data['play_time_distinct'];

        if(!isset($course[$type][$data['course_id'] . '-' . $data['course_lesson_id']]))
        {
            $course[$type][$data['course_id'] . '-' . $data['course_lesson_id']] = 0;
        }

        $course[$type][$data['course_id'] . '-' . $data['course_lesson_id']] += $data['play_time_distinct'];

        //观看时长大于课程总时长90%算完成
        if($data['total_time'] > 0 && (($course[$type][$data['course_id'] . '-' . $data['course_lesson_id']]) / $data['total_time'] >= 0.9))
        {
            $ret[$type]['course']++;
        }

        if(!isset($parents[$type][$data['member_id']]))
        {
            $ret[$type]['parents']++;
            $parents[$type][$data['member_id']] = 1;
        }
    }

    /**
     * 上个月
     * @param $year
     * @param $currentMonth
     * @return int|string
     */
	public function getLastMonth($year, $currentMonth)
    {
        if($currentMonth == 1)
        {
            $year = $year - 1;
            $lastMonth = 12;
        }
        else
        {
            $lastMonth = $currentMonth - 1;
        }

        if($lastMonth < 10)
        {
            $lastMonth = '0' . $lastMonth;
        }

        return intval($year . $lastMonth);
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