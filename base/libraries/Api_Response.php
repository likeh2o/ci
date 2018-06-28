<?php
/**
 * Api response
 */

class Api_Response{
    private $_result = null;

    public function __construct(){
        $this->_result = new stdClass();
        $this->_result->error = Base_Error_Code::SUCCESS;
        $this->_result->cost  = 0;
    }

    public function gen_insert($lastid){
        $result['lastid'] = $lastid;
        return $this->gen_result($result);
    }

    public function gen_update($affected_rows){
        $result['affected_rows'] = $affected_rows;
        return $this->gen_result($result);
    }

    public function gen_list($data = array(), $total = 0, $page = 1, $size = 10, $ext = array()){
        $result['total'] = (int)$total;
        $result['page'] = (int)$page;
        $result['size'] = (int)$size;
        if($ext){
            foreach($ext as $k=>$v){
                $result[$k] = $v;
            }
        }
        $result['data'] = $data;
        return $this->gen_result($result);
    }

    public function gen_object($data){
        return $this->gen_result($data);
    }

    private function gen_result($data){
        $this->calcu_cost();
        $this->_result->result = $data;
        return $this->_result;
    }

    public function gen_error($error_code, $error_info=''){
        $this->_result->error = $error_code;
        if (! empty($error_info)) {
            $this->_result->error_info = $error_info;
        }

        $this->calcu_cost();
        return $this->_result;
    }

    public function calcu_cost(){
        $BM =& load_class('Benchmark', 'core');
        //$BM->mark('total_execution_time_end');
        $elapsed = $BM->elapsed_time('total_execution_time_start', 'total_execution_time_end');
        $this->_result->cost = $elapsed;
    }
}
