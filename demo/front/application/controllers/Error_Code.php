<?php
class Error_Code extends Base_Error_Code{

    const DEMO_ERROR = -100;

    public static $info = array(
            self::DEMO_ERROR => '-100错误'
    );

    public static function info($code){
        return self::get_info($code, self::$info+self::$base_info);
    }
}

