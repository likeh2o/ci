<?php
//var_dump('i am api/applicate/config/development/config');
$config['log_threshold'] = 1;


// db config
define('DEMO_DB_CONFIG', 'demo');
$config[DEMO_DB_CONFIG]['master']     = array(
    'dsn'  => 'mysql:host=localhost;dbname=demo',
    'user' => 'root',
    'pwd' => '',
);
$config[DEMO_DB_CONFIG]['slaves'][]   = array(
    'dsn'  => 'mysql:host=localhost;dbname=demo',
    'user' => 'root',
    'pwd' => '',
);
$config[DEMO_DB_CONFIG]['persistent'] = false;
$config[DEMO_DB_CONFIG]['timeout']    = 1;
$config[DEMO_DB_CONFIG]['character']  = 'utf8';

