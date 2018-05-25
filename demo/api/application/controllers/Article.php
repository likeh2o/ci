<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH . '/controllers/Base.php');

class Article extends Base {
    public function __construct(){
        parent::__construct();
        $this->load->model('Article_model');
    }

	public function index_get()
	{
		$id = $this->get_check('id', PARAM_NOT_NULL_NOT_EMPTY);

        $queryFields = array();
        $queryConditions = array(
            array('pk_article', $id, '='),
        );

        $result = $this->Article_model->dbSelect($queryFields, $queryConditions);
        $data = !empty($result)?$result[0]:$result;

        $this->response_result($data);
	}

	public function error_get()
	{
		$this->response_error(Error_Code::DEMO_ERROR);
	}

	public function exception_get()
	{
		throw new Exception("Error Exception Test");
	}

}

