<?php

namespace frame\base;
use \frame\core\Task;

class Controller
{

    protected $request;
    protected $response;
    
    private $_val = array();

    function __construct(Request $request, Response $response) {
        $this->request = $request;
        $this->response = $response;
    }

    /**
     * Get response from server
     * @return Response
     */
    public function getResponse() {
        return $this->response;
    }

    /**
     * Get request data
     * @return Request
     */
    public function getRequest() {
        return $this->request;
    }

    /**
     * Send data to client
     * @param $data
     */
    public function send($data) {
        $this->response->send($data);
    }

    /**
     * Send data to ip client
     * @param $ip
     * @param $port
     * @param $data
     * @param bool $ipv6
     */
    public function sendto($ip, $port, $data, $ipv6 = false) {
        $this->response->sendto($ip, $port, $data, $ipv6);
    }

    /**
     * Response header to client
     * @param $key
     * @param $value
     */
    public function header($key, $value) {
        $this->response->header($key, $value);
    }

    public function status($http_status_code) {
        $this->response->status($http_status_code);
    }

    public function  cookie($key, $value = '', $expire = 0, $path = '/', $domain = '', $secure = false, $httponly = false) {
        $this->response->cookie($key, $value, $expire, $path, $domain, $secure, $httponly);
    }

    /**
     * @param $data
     * @param $uri
     */
    public function viewRender($data, $uri) {

    }

    private function init() {
        return;
    }


    /**
     * run action
     * @param $actionName
     */
    public function run($actionName) {
        $this->init();
        $this->{$actionName}();
        return;
    }

    public function addTask(Task $task) {
        $this->response->addTask($task);
    }

    public function setProtocol($protocol) {
        $this->response->protocol = $protocol;
    }
    
    private function renderFile($_viewFile, $_data = null) {
        if (is_array($_data)) {
            $this->_val = array_merge($this->_val, $_data);
            extract($_data, EXTR_PREFIX_SAME, 'data');
        } else {
            $data = $_data;
        }
    
        ob_start();
        ob_implicit_flush(false);
    
        require($_viewFile);
        return ob_get_clean();
    }
    
    /**
     * assign template val
     * @param mixed $name
     * @param mixed $value
     */
    public function assign($name, $value = '') {
        if (is_array($name)) {
            $this->_var = array_merge($this->_var, $name);
        }
        elseif (is_object($name)) {
            foreach ($name as $key => $val)
            {
                $this->_var[$key] = $val;
            }
        }
        else {
            $this->_var[$name] = $value;
        }
    }
    
    /**
     * output template
     * @param string $view
     * @param string $data
     * @throws \Exception
     */
    public function display($view, $data = null) {
        if (($viewFile = $this->getViewFile($view)) === false) {
            throw new \Exception("Cannot find the requested view '{$view}'.");
        }
    
        $output = $this->renderFile($viewFile, $data);
        $this->send($output);
    }
    
    private function getViewFile($viewName) {
        $viewFile = APP_PATH . '/views/' . $viewName;
        if (is_file($viewFile . '.php'))
            return $viewFile . '.php';
        else
            return false;
    }
}