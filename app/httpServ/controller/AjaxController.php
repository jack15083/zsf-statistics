<?php

/**
 * Created by PhpStorm.
 * User: zengfanwei
 * Date: 2017/6/28
 * Time: 15:21
 */
class AjaxController extends BaseController
{
    /**
     * 添加统计上报
     */
    public function getStaticsData()
    {
        $module     = $this->getInput('module');
        $interface  = $this->getInput('interface');
        $start_date = $this->getInput('start_date', date('Y-m-d') . ' 00:00:00');
        $end_date   = $this->getInput('end_date', date('Y-m-d H:i:s'));

        $start_time = strtotime($start_date);
        $end_time   = strtotime($end_date);

        if($end_time - $start_time < 3600) {
            return $this->sendError(Code::FAIL, '时间间隔不能少于一个小时');
        }

        if(date('Y-m-d', $start_time) != date("Y-m-d", $end_time)) {
            return $this->sendError(Code::FAIL, '开始时间与结束时间必须在同一天内');
        }

        if(empty($module)) {
            $this->sendError(Code::INVALID_PARAMS, 'Module不能为空');
        }

        $data = $this->getData($module, $start_time, $end_time, $interface);
        return $this->sendJson($data);
    }

    private function getData($module, $startTime, $endTime, $interface = '')
    {
        $module = strtolower($module);
        $fileDir = Config::STATICS_PATH . $module . '/' . date("Ymd", $startTime) . '/';
        $fileList = scandir($fileDir, 1);

        $data = [];

        $startFile = (int) $this->getFileName($startTime);
        $endFile   = (int) $this->getFileName($endTime);
        foreach ($fileList as $file) {
            if ($file == '..' || $file == '.')
                continue;

            $fileNameNum = (int) str_replace('.log', '', $file);

            if($fileNameNum < $startFile) continue;
            if($fileNameNum > $endFile) continue;

            $handle = fopen($fileDir . $file, 'r');
            if(!$handle) {
                $data[$fileNameNum] = [
                    'requests_num'     => 0,
                    'average_time'     => 0,
                    'error_rate'       => 0,
                    'timeout_rate'     => 0
                ];
                continue;
            }

            $costTime = $errorNum = $requestNum = $timeoutNum = 0;
            while (!feof($handle)) {
                $line = fgets($handle, 4906);
                if(empty($line)) continue;

                $reportData = explode("\t", $line);
                //time|remote_ip|module|interface|cost_time|success_flag|code|message
                if($interface && $interface != $reportData[3]) continue;

                $requestNum++;
                if($reportData[5] == 0) $errorNum++;
                if($reportData[4] > 5000) $timeoutNum++;

                $costTime += $reportData[4];
            }
            fclose($handle);

            $data[$fileNameNum] = [
                'requests_num'     => $requestNum,
                'average_time'     => $costTime > 0 ? $costTime / $requestNum : 0,
                'error_rate'       => $errorNum > 0 ? $errorNum / $requestNum : 0,
                'timeout_rate'     => $timeoutNum > 0 ? $timeoutNum / $requestNum : 0,
            ];
        }

        return $data;
    }

    /**
     * @param $timestamp
     * @return string
     */
    public  function getFileName($timestamp = '')
    {
        $time_break_num = (int) ( date("s", $timestamp) / 5) * 5;
        $fileName = date("H") . ($time_break_num == 13 ? 12 : $time_break_num);

        return $fileName;
    }

    public function actionTest()
    {
        $this->send("Hello World");
    }

}