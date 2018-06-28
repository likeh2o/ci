<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$config['smarty_auto_escape_vars'] = FALSE;
$config['smarty_template_dir'] = realpath(APPPATH . 'views/');
$config['smarty_compile_dir'] = realpath(APPPATH . '../cache/templates_c/');
$config['smarty_config_dir'] = realpath(APPPATH . 'config/');
$config['smarty_cache_dir'] = realpath(APPPATH . '../cache/smarty/');
$config['smarty_plugins_dir'] = array(
    realpath(APPPATH. 'third_party/smarty_app_plugins'),
);
