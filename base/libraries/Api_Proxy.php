<?php
class Http_Proxy
{
    private static $CI;

    /**
     * ApiProxBase constructor.
     */
    public function __construct()
    {
        if (is_null(self::$CI)) {
            self::$CI = &get_instance();
        }

        self::$CI->load->library('curl');
    }

    public function doPost($data, $url='', $ms = true, $timeout = 5, $timeout_ms = 5000, $curlOpts = ''){
        $result = self::$CI->curl->post($data, $url, $ms, $timeout, $timeout_ms);
        $result = json_decode($result);

        return $result;
    }

    public function doRequest(
        $host,
        $api,
        $params,
        $method,
        array $headers = [],
        $ms = true,
        $timeout = 2,
        $timeout_ms = 2000,
        $is_internal_api = true
    ) {
        $requests[] = $this->request($host, $api, $params, $method, $headers);
        
        return $this->result($requests, $ms, $timeout, $timeout_ms, $is_internal_api);
    }

    protected function request($host, $api, $params, $method = 'GET', $headers = array())
    {
        $host    = self::$CI->config->item($host);

        $request = array(
            'host'    => $host,
            'api'     => $api,
            'method'  => $method,
            'params'  => $params,
            'headers' => $headers,
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
     * @access private
     * @return bool|mixed|object
     */
    protected function result($requests, $ms = true, $timeout = 2, $timeout_ms = 500, $is_internal_api = true)
    {
        try {
            $host    = $requests[0]['host'];
            $api     = $requests[0]['api'];
            $method  = $requests[0]['method'];
            $params  = $requests[0]['params'];
            $headers = $requests[0]['headers'];
            $result  = self::$CI->curl->capture($host, $api, $method, $params, $headers, $ms, $timeout, $timeout_ms);
            if (!empty($result)) {
                if ($is_internal_api) {
                    return $this->checkApiResult($result, $requests);
                }

                return $result;
            }

            return (object)array();
        } catch (Exception $e) {
            $msg = $e->getMessage() . json_encode($requests);
            log_message('error', $msg);

            //throw new Exception($msg, $e->getCode());
            return false;
        }
    }

    private function checkApiResult($result, $requests)
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

            // 业务错误
            log_message('error', json_encode($errMsg));
        }

        return $result;
    }
}
