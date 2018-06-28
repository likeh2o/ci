<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH . '/controllers/Base.php');

class Article extends Base {
    public function __construct(){
        parent::__construct();
        $this->load->model('Article_model');
    }

	public function list_by_page_get()
	{
		$page = $this->get_check('page', PARAM_NULL_EMPTY, PARAM_TYPE_INT);
        $page = empty($page)?1:$page;
        $size = 10;

        $queryFields = array();
        $queryConditions = array();
        $queryConditions [] = array('pk_article', 0, '>');
        $orderBy = array('pk_article'=>'desc');

        $data = $this->Article_model->dbSelect(
            $queryFields, $queryConditions, $orderBy, array(), $page, $size
        );
        $total = $this->Article_model->dbTotal($queryConditions);

        return $this->response_list($data, $total[0]['num'], $page, $size);
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

        $this->response_object($data);
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

