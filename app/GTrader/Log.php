<?php

namespace GTrader;

class Log {

    /*
    Log::emergency(... $messages);
    Log::alert();
    Log::critical();
    Log::error();
    Log::warning();
    Log::notice();
    Log::info();
    Log::debug();
    Log::sparse();
    */

    static $message_type = 3; // append to file
    static $destination = 'logs/GTrader.log';

    public static function __callStatic($severity, $args)
    {
        return error_log(
            '['.date('Y-m-d H:i:s').'] '.
                config('app.env').'.'.strtoupper($severity).' '.
                static::getCaller().' '.
                join(', ', array_map(function($v) {
                    return stripslashes(json_encode($v));
                }, $args))."\n",
            self::$message_type,
            storage_path(self::$destination)
        );
    }

    public static function sparse(... $args)
    {
        return error_log(
            '['.date('Y-m-d H:i:s').'] '.
                join(', ', array_map(function($v) {
                    return stripslashes(json_encode($v));
                }, $args))."\n",
            self::$message_type,
            storage_path(self::$destination)
        );
    }

    protected static function getCaller()
    {
        list($na, $file, $class) = debug_backtrace(false, 3);

        $args = implode(', ', array_map(function($v) {
            return gettype($v);
        }, $class['args']));

        return '['.basename($file['file']).':'.$file['line'].'] ['.
            substr($class['class'], strrpos($class['class'], '\\') + 1).
            $class['type'].$class['function'].'('.$args.')]';

    }
}
