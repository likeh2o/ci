<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Welcome extends CI_Controller {

	/**
	 * Index Page for this controller.
	 *
	 * Maps to the following URL
	 * 		http://example.com/index.php/welcome
	 *	- or -
	 * 		http://example.com/index.php/welcome/index
	 *	- or -
	 * Since this controller is set as the default controller in
	 * config/routes.php, it's displayed at http://example.com/
	 *
	 * So any other public methods not prefixed with an underscore will
	 * map to /index.php/welcome/<method_name>
	 * @see https://codeigniter.com/user_guide/general/urls.html
	 */
	public function index()
	{
        $this->load->add_package_path(dirname(BASEPATH));
        $this->load->helper('demo');
        $this->load->base_config('DEMO');

       	// 先加载
        $this->load->base_config('Base_Error_Code');
        include_once(APPPATH . 'controllers/Error_Code.php');
        var_dump(Error_Code::desc(Error_Code::DEMO_ERROR));
        
		$this->load->view('welcome_message');
	}
}

