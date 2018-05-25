<?php
class Api_Model extends CI_Model
{
    private $pdo   = null;
    private $trans = false;

    public function __construct()
    {
        parent::__construct();
        $this->load->library('mcurl');
        $this->load->library('curl');
    }

    public function key($params)
    {
        return md5(json_encode($params));
    }

    public function request($host, $api, $params, $method = 'GET', $headers = array())
    {
        $host = $this->config->item($host);
        $request = array(
            'host'   => $host,
            'api' => $api,
            'method' => $method,
            'params' => $params,
            'headers' => $headers
        );
        return $request;
    }

    /**
     * result
     *
     * @param mixed $requests
     * @param bool  $ms
     * @param int   $timeout
     * @param int   $timeout_ms
     * @param bool  $is_internal_api true调用checkApiResult判断是否有error等api必有字段
     *
     * @access public
     * @return bool|mixed|object
     */
    public function result($requests, $ms = true, $timeout = 2, $timeout_ms = 500, $is_internal_api = true)
    {
        try {
            $host = $requests[0]['host'];
            $api = $requests[0]['api'];
            $method = $requests[0]['method'];
            $params = $requests[0]['params'];
            $headers = $requests[0]['headers'];
            $result = $this->curl->capture($host, $api, $method, $params, $headers, $ms, $timeout, $timeout_ms);
            if (!empty($result)) {
                if ($is_internal_api) {
                    return $this->checkApiResult($result, $requests);
                }
                return $result;
            }
            return (object)array();
        } catch (Exception $e) {
            $msg = $e->getMessage().json_encode($requests);
            log_message('error', $msg);
            //throw new Exception($msg, $e->getCode());
            return false;
        }
    }

    /**
     * 多请求key处理
     */
    public function makeKey($data)
    {
        return md5(json_encode($data));
    }

    /**
     * 一次请求多个
     */
    public function results($requests, $ms = true, $timeout = 2, $timeout_ms = 500)
    {
        try {
            $results = $this->mcurl->capture_multi($requests, $ms, $timeout, $timeout_ms);
            return $results;
        } catch (Exception $e) {
            $msg = $e->getMessage().json_encode($requests);
            log_message('error', $msg);
            //throw new Exception($msg, $e->getCode());
            return false;
        }
    }

    public function callApi($host, $api, $params, $method, $ms = true, $timeout = 2, $timeout_ms = 500, $is_internal_api = true, $headers = array())
    {
        $requests   = array();
        $requests[] = $this->request($host, $api, $params, $method, $headers);

        $result = $this->result($requests, $ms, $timeout, $timeout_ms, $is_internal_api);
        return $result;
    }

    protected function checkApiResult($result, $requests)
    {
        $backTrace  = debug_backtrace()[1];
        $methodName = !empty($backTrace['function']) ? $backTrace['function'] : '';
        $className  = !empty($backTrace['class']) ? $backTrace['class'] . '::' : '';
        $line       = !empty($backTrace['line']) ? $backTrace['line'] : 0;
        $file       = !empty($backTrace['file']) ? $backTrace['file'] : '';
        $__METHOD__ = $className . $methodName;

        if (empty($result) || !isset($result->error)) {
            $errMsg = array(
                'msg'      => $__METHOD__ . ' return null',
                'file'     => $file,
                'line'     => $line,
                'result'   => $result,
                'requests' => $requests,
            );

            log_message('error', json_encode($errMsg));

            return false;
        }

        if ($result->error != 0) {
            $errMsg = array(
                'msg'      => $__METHOD__ . ' failed',
                'file'     => $file,
                'line'     => $line,
                'result'   => $result,
                'requests' => $requests,
            );

            log_message('error', json_encode($errMsg));
        }

        return $result;
    }
}
