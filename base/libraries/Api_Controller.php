<?php defined('BASEPATH') or exit('No direct script access allowed');

define('PARAM_NULL_EMPTY', 1);//非必选可空
define('PARAM_NOT_NULL_EMPTY', 2);//必选可空
define('PARAM_NULL_NOT_EMPTY', 3);//非必选不可空
define('PARAM_NOT_NULL_NOT_EMPTY', 4);//必选不可空
define('PARAM_TYPE_NUMBER', 1);//参数类型-数字
define('PARAM_TYPE_STRING', 2);//参数类型-字符串
define('PARAM_TYPE_INT', 3);//参数类型-整数

class Api_Controller extends REST_Controller
{
    private $param_list = array();

    public function __construct()
    {
        parent::__construct();
        $this->load->add_package_path(dirname(BASEPATH));
        $this->load->library('Api_Response');
        //$this->load->base_config('Base_Error_Code');
    }

    public function _remap($method, $args=array())
    {
        try {
        	parent::_remap($method, $args);
        } catch (Exception $e) {
            // 默认是数据库异常
            $info = $e->getMessage();
            $code = $e->getCode();

            if (empty($code) || !is_numeric($code)) {
                $code = Error_Code::ERROR_THROW;
                $info = '[' . $code . '] ' . $info;
            }

            $data = $this->api_response->gen_error($code, $info);
            echo $this->response($data);
            exit;
        }
    }

    // 是否未传值
    protected function is_get_null($key, $xss_clean = true){
        $val = $this->get($key, $xss_clean);
        return $val === false;
    }

    // 是否未传值
    protected function is_post_null($key, $xss_clean = true){
        $val = $this->post($key, $xss_clean);
        return $val === false;
    }

    protected function get_check($key, $check, $type = PARAM_TYPE_STRING, $xss_clean = true)
    {
        $val = $this->get($key, $xss_clean);

        return $this->params_check($key, $check, $type, $val, 'get');
    }

    protected function post_check($key, $check, $type = PARAM_TYPE_STRING, $xss_clean = true)
    {
        $val = $this->post($key, $xss_clean);

        return $this->params_check($key, $check, $type, $val, 'post');
    }

    private function params_type_check($key, $type, $val)
    {
        switch ($type) {
            case PARAM_TYPE_NUMBER:
                if (!is_numeric($val)) {
                    throw new Exception($key . ' 类型错误, 需要数字类型', Error_Code::ERROR_PARAM);
                }
                $val = floatval($val);
                break;
            case PARAM_TYPE_INT:
                if (!is_numeric($val)) {
                    throw new Exception($key . ' 类型错误, 需要数字类型', Error_Code::ERROR_PARAM);
                }
                $val = intval($val);
                break;
            default:
                break;
        }
        return $val;
    }

    private function params_check($key, $check, $type, $val, $method)
    {
        if (false !== $val && !is_numeric($val) && !is_string($val)) {
            throw new Exception($key . ' 不是数字或字符串', Error_Code::ERROR_PARAM);
        }
        $val_origin = $val;
        $val        = trim($val);

        switch ($check) {
            case PARAM_NOT_NULL_NOT_EMPTY:
                if (empty($val)) {
                    throw new Exception($key . ' 必传非空', Error_Code::ERROR_PARAM);
                }
                $val = $this->params_type_check($key, $type, $val);
                break;
            case PARAM_NOT_NULL_EMPTY:
                if (false === $val_origin) {
                    throw new Exception($key . ' 必传', Error_Code::ERROR_PARAM);
                }
                $val = $this->params_type_check($key, $type, $val);
                break;
            case PARAM_NULL_NOT_EMPTY:
                if (false !== $val_origin) {
                    if (empty($val)) {
                        throw new Exception($key . ' 若传非空', Error_Code::ERROR_PARAM);
                    }
                    $val = $this->params_type_check($key, $type, $val);
                }
                break;
            case PARAM_NULL_EMPTY:
                if (false !== $val_origin && !empty($val)) {
                    $val = $this->params_type_check($key, $type, $val);
                }
                break;
        }

        $this->param_list[$method][$key] = array($val_origin, $val);

        return $val;
    }

    protected function get_request_params($method = null)
    {
        if (empty($method)) {
            $method = $_SERVER['REQUEST_METHOD'];
        }

        $method = strtolower($method);
        $params = $this->param_list[$method];

        if (empty($params)) {
            return array();
        }
        foreach (array_keys($params) as $key) {
            if (false === $params[$key][0]) {
                unset($params[$key]);
            } else {
                $params[$key] = $params[$key][1];
            }
        }

        return $params;
    }

    protected function response_error($code, $info = '')
    {
        if (class_exists('Error_Code')) {
            if(!empty(Error_Code::desc($code))){
                $info = Error_Code::desc($code) . ' ' . $info;
            }
        }
        
        $data = $this->api_response->gen_error($code, $info);
        return $this->response($data);
    }

    protected function response_result($result)
    {
        $data = $this->api_response->gen_result($result);
        return $this->response($data);
    }

}