<?php

namespace cookpan001\Pipeline;

class Response
{
    const END = "\r\n";
    
    public static function serialize($data)
    {
        if($data instanceof Reply\Error){
            return '-'.$data->getMessage().self::END;
        }
        if($data instanceof Reply\OK){
            return '+OK'.self::END;
        }
        if($data instanceof Reply\TimeoutException){
            return '*-1'.self::END;
        }
        if(is_int($data)){
            return ':'.$data.self::END;
        }
        if($data instanceof Reply\Bulk){
            return '$'.strlen($data->str).self::END.$data->str.self::END;
        }
        if(is_string($data)){
            return '+'.$data.self::END;
        }
        if(is_null($data)){
            return '$-1'.self::END;
        }
        $str = '*'.count($data).self::END;
        foreach($data as $line){
            if(is_null($line)){
                $str .= '$-1'.self::END;
            }else if(is_array($line)){
                $str .= self::serialize($line).self::END;
            }else{
                $str .= '$'.strlen($line).self::END.$line.self::END;
            }
        }
        return $str;
    }
}