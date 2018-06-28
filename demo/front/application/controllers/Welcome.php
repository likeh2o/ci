<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(dirname(BASEPATH) . '/libraries/Front_Controller.php');
require_once(APPPATH . '/controllers/Error_Code.php');
require_once(APPPATH . '/controllers/Base.php');

class Welcome extends Front_Controller {
	public function index()
	{
        $this->load->base_config('common');

		$data = [];
		$data['hi'] = 'demo';
		$data['PARAM_TYPE_INT'] = PARAM_TYPE_INT;

		$this->display($data);
	}
}

