<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(dirname(BASEPATH) . '/libraries/Front_Controller.php');
require_once(APPPATH . '/controllers/Error_Code.php');
require_once(APPPATH . '/controllers/Base.php');

class Welcome extends Front_Controller {
	public function index()
	{
        $this->load->library('Logic/Demo/Demo');

        $article = $this->demo->getById(1);
        $articles = $this->demo->list_by_page();

		$data = [];
		$data['hi'] = 'demo';
		$data['PARAM_TYPE_INT'] = PARAM_TYPE_INT;
		$data['article'] = $article->result;
		$data['articles'] = $articles->result;

		$this->display($data);
	}
}

