<?php if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

if (!function_exists('demo')) {
    function demo($length)
    {
        var_dump('i am base helpers demo');
    }
}

