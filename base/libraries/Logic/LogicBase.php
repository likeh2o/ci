<?php
require_once dirname(__DIR__) . '/Http_Proxy.php';

class LogicBase extends Http_Proxy
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function getDataFromResult($result){
        $obj = new ResultData();

        if($result === false){
            $obj->is_http_fail = true;
            return $obj;
        }

        if($result->error == 0){
            $obj->is_success = true;
        }else{
            $obj->is_success = false;
            $obj->error = $result->error;
            $obj->info = empty($result->info)?'':$result->info;
        }

        if(isset($result->result)){
            $obj->result = $result->result;
        }

        return $obj;
    }
}

/**
 * 通用扩展类
 */
class ResultData {
    // 是否http请求错误
    public $is_http_fail = false;
    // error=0
    public $is_success = false;
    // error
    public $error = 0;
    // error info
    public $info = '';
    // 返回值归一
    public $result = null;
}
