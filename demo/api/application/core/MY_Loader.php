<?php
/**
 * 扩展loader加载 base/config
 */
class MY_Loader extends CI_Loader {
    public function __construct(){
        parent::__construct();
    }
/*
    public function base_config($file){
        $file_path = dirname(BASEPATH).'/config/'.$file.'.php';
        if(file_exists($file_path)){
            require_once($file_path);
            $env_file_path = dirname(BASEPATH).'/config/'.ENVIRONMENT.'/'.$file.'.php';
            if(file_exists($env_file_path)){
                require_once($env_file_path);
            }
        }else{
            log_message('error', 'The configuration file '.$file_path.'.php does not exist.');
        }
    }
*/
}
