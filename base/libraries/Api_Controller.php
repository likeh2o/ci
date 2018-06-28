<?php defined('BASEPATH') or exit('No direct script access allowed');

require_once(dirname(__FILE__, 2) . '/config/Base_Error_Code.php');

class Api_Controller extends REST_Controller
{
    private $param_list = array();

    public function __construct()
    {
        parent::__construct();
        $this->load->add_package_path(dirname(BASEPATH));
        $this->load->library('Api_Response');
        $this->load->base_config('common');
    }

    public function _remap($method, $args=array())
    {
        try {
        	parent::_remap($method, $args);
        } catch (Exception $e) {
            // 默认是数据库异常
            $info = $e->getMessage();
            $code = $e->getCode();

            $this->response_error($code, $info);
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
			$error_info  = Error_Code::info($code);
            if($error_info == Error_Code::$base_info[Error_Code::ERROR_UNKNOWN]){
                $info = '[' . $code . '] ' . $info;
                $code = Error_Code::ERROR_UNKNOWN;
            }else{
                $info = $error_info.' ' .$info;
            }
        }

        $data = $this->api_response->gen_error($code, $info);
        return $this->response($data);
    }

    protected function response_update($result)
    {
        $data = $this->api_response->gen_update($result);
        return $this->response($data);
    }

    protected function response_insert($result)
    {
        $data = $this->api_response->gen_insert($result);
        return $this->response($data);
    }

    protected function response_object($result)
    {
        $data = $this->api_response->gen_object($result);
        return $this->response($data);
    }

    protected function response_list($result, $total=0, $page=1, $size=10, array $ext = [])
    {
        $data = $this->api_response->gen_list($result, $total, $page, $size, $ext);
        return $this->response($data);
    }

}
