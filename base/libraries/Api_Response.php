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

    public function gen_result($data){
        $this->calcu_cost();
        $this->_result->data = $data;
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
