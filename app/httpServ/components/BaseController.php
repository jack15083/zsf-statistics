<?php
/**
 * Created by PhpStorm.
 * User: zengfanwei
 * Date: 2017/7/7
 * Time: 14:31
 */

class BaseController extends \frame\base\Controller
{
    public function __construct(\frame\base\Request $request, \frame\base\Response $response)
    {
        parent::__construct($request, $response);
    }

    /**
     * 获取输入
     * @param $key
     * @param $default
     */
    public function getInput($key = '', $default = '')
    {
        $get  = !empty($this->request->data['get']) ? $this->request->data['get'] : [];
        $post = !empty($this->request->data['post']) ? $this->request->data['post'] : [];

        $input = array_merge($get, $post);

        if(empty($key)) return $input;

        if(isset($input[$key])) return trim($input[$key]);

        return $default;
    }

    public function sendJson($data = [])
    {
        $this->header('content-type', 'application/json');
        $data = ['error' => 0, 'msg' => '', 'data' => $data];
        $this->send(json_encode($data));
    }

    public function sendError($code = \Code::FAIL, $msg = '')
    {
        if(!$msg) $msg = Code::getError($code);
        $this->header('content-type', 'application/json');
        $data = ['error' => $code, 'msg' => $msg];
        $this->send(json_encode($data));
    }
}