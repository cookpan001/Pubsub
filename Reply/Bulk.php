<?php

namespace cookpan001\Pubsub\Reply;

class Bulk
{
    public $str = '';
    
    public function __construct($str)
    {
        $this->str = $str;
    }
}