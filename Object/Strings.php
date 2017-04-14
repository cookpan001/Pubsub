<?php

namespace cookpan001\Pubsub\Object;

use cookpan001\Pubsub\Reply\OK;

class Strings
{
    public function __construct()
    {
        
    }
    
    public function set($database, $key, $value)
    {
        $database->keys[$key] = $value;
        return OK::instance();
    }
    
    public function get($database, $key)
    {
        if(!isset($database->keys[$key])){
            return null;
        }
        return $database->keys[$key];
    }
    
    public function incr($database, $key)
    {
        if(!isset($database->keys[$key])){
            $database->keys[$key] = 0;
        }
        $database->keys[$key]++;
        return $database->keys[$key];
    }
    
    public function incrby($database, $key, $increment)
    {
        if(!isset($database->keys[$key])){
            $database->keys[$key] = 0;
        }
        $database->keys[$key] += $increment;
        return $database->keys[$key];
    }
}

