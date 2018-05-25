<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(dirname(BASEPATH) . '/config/Base_Error_Code.php');
require_once(dirname(BASEPATH) . '/libraries/Api_Controller.php');
require_once(APPPATH . '/controllers/Error_Code.php');

class Base extends Api_Controller {
    public function __construct(){
        parent::__construct();
        $this->load->model('ORM_Model');
    }

}

