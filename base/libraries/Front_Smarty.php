<?php  if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 5.1.6 or newer
 *
 * @package		CodeIgniter
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2008 - 2014, EllisLab, Inc.
 * @license		http://codeigniter.com/user_guide/license.html
 * @link		http://codeigniter.com
 * @since		Version 1.0
 * @filesource
 */

require(dirname(BASEPATH) . '/libraries/Smarty/Smarty.class.php');

class Front_Smarty extends Smarty
{
    public function __construct()
    {
        parent::__construct();
        $CI = & get_instance();
        $CI->load->config('smarty');

        if ($CI->config->item('smarty_template_dir')) {
            $this->setTemplateDir($CI->config->item('smarty_template_dir'));
        }
        if ($CI->config->item('smarty_compile_dir')) {
            $this->setCompileDir($CI->config->item('smarty_compile_dir'));
        }
        if ($CI->config->item('smarty_config_dir')) {
            $this->setConfigDir($CI->config->item('smarty_config_dir'));
        }
        if ($CI->config->item('smarty_cache_dir')) {
            $this->setCacheDir($CI->config->item('smarty_cache_dir'));
        }
        if ($CI->config->item('smarty_caching')) {
            $this->caching = $CI->config->item('smarty_caching');
        }
        if ($CI->config->item('smarty_cache_lifetime')) {
            $this->cache_lifetime = $CI->config->item('smarty_cache_lifetime');
        }
        if ($CI->config->item('smarty_auto_escape_vars')) {
            $this->escape_html = true;
        }
        $smarty_plugins_dir = $CI->config->item('smarty_plugins_dir');
        if ($smarty_plugins_dir) {
            if (is_array($smarty_plugins_dir)) {
                foreach ($smarty_plugins_dir as $v) {
                    $this->addPluginsDir(strVal($v));
                }
            } else {
                $this->addPluginsDir(strVal($smarty_plugins_dir));
            }
        }

        $this->assignByRef('CI', $CI);
    }
}
