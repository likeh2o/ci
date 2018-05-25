<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH . '/controllers/Base.php');

class Article extends Base {
    public function __construct(){
        parent::__construct();

        //$this->load->model('Article_model');
    }

	public function index_get()
	{
		$k = $this->get_check('k', PARAM_NOT_NULL_NOT_EMPTY);
        $this->response_result($k);
		//throw new Exception("Error Processing Request", 1);
	}

	public function error_get()
	{
		$this->response_error(Error_Code::DEMO_ERROR);
		//throw new Exception("Error Exception Test", 1);
	}

	public function exception_get()
	{
		throw new Exception("Error Exception Test");
	}

}

