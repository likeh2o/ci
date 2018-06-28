<?php if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Article_model extends ORM_Model{

    public $table = 't_article';

    public function __construct(){
        parent::__construct();
        $this->setDBConfig(DEMO_DB_CONFIG);
    }
}
