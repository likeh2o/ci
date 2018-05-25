<?php
/**
 * 全局错误码定义
 * 各个业务错误码定义需要继承该类
 */
abstract class Base_Error_Code
{
    // 成功
	const SUCCESS     = 0;
    // -1 到 -99 是通用错误预留
	const ERROR_PARAM   = -1;
	const ERROR_DB      = -2;
	const ERROR_THROW   = -3;
	const ERROR_REDIS   = -4;

    const ERROR_UNKNOWN = -99;
    // -100 以上为业务预留
    // 各个项目可以约定好错误码段

    protected static $base_info = array(
        self::SUCCESS       => '成功',
        self::ERROR_PARAM   => '参数错误',
        self::ERROR_DB      => '数据库操作错误',
        self::ERROR_THROW   => '异常错误',
        self::ERROR_REDIS   => 'REDIS错误',
        self::ERROR_UNKNOWN => '未知错误',
    );

    protected static function get_info($code, $info){
        return empty($info[$code]) ? '未定义错误' : $info[$code];
    }
}
