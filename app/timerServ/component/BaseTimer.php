<?php
use weblib\log\Log;

class BaseTimer extends frame\core\Runnable
{

    public function __construct($taskConf, $interval = 30, $logLevel = 'error', $num = 1)
    {
        parent::__construct($taskConf, $interval, $logLevel, $num);
        ini_set('default_socket_timeout', -1);
    }

    /**
     * [run 执行函数]
     * @return [type] [description]
     */
    public function run($subTaskId)
    {
		
    }

    /**
     * 执行sql
     * @param $sql
     * @return bool|mysqli_result
     */
    protected function query($sql)
    {
        try
        {
            $res = $this->mysqli->query($sql);
            if (!$res && !empty($this->mysqli->errno))
            {
                Log::error("Query Failed, ERRNO: " . $this->mysqli->errno . " (" . $this->mysqli->error . ")", $this->mysqli->errno, __METHOD__);
                if ($this->mysqli->errno == 2013 || $this->mysqli->errno == 2006) {
                    $this->db->retryConnect();
                    $this->mysqli = $this->db->mysqli();
                    $res = $this->mysqli->query($sql);
                }
            }
        }
        catch (\Exception $e)
        {
            Log::error(__LINE__. $e->getMessage(), 1, __METHOD__);
            return false;
        }

        if(empty($res))
        {
            Log::error('Query Failed ' . $sql, 1,__METHOD__);
        }

        return $res;
    }

}