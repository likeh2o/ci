<?php
class ORM_Exception extends Exception
{
    private static $instance;

    const ERR_MSG_NOT_EMPTY                  = ' can not be empty';
    const ERR_MSG_MUST_INT_TYPE_ARRAY_STRING = ' must in array|string type';
    const ERR_MSG_MUST_INT_TYPE_ARRAY        = ' must in array type';
    const ERR_MSG_MUST_INT_TYPE_STRING       = ' must in string type';
    const ERR_MSG_COLUMN_FORMAT_INVALID      = ' format invalid';

    public static function getInstance()
    {
        if (self::$instance instanceof static) {
            return self::$instance;
        }
        self::$instance = new static();

        return self::$instance;
    }

    public function throwException($message, $exception_code = -2, $privious = null)
    {
        log_message('error', $message);
        throw new Exception('系统繁忙', $exception_code, $privious);
    }
}
