<?php
class Error_Code extends Base_Error_Code{

    const DEMO_ERROR = -100;

    public static $info = array(
            self::DEMO_ERROR => '-100错误'
    );

    public static function desc($code){
        self::$info = self::$info+self::$base_info;
        return empty(self::$info[$code]) ? '未定义错误' : self::$info[$code];
    }
}

