<?php

namespace cookpan001\Pubsub;

class Database
{
    public $db;
    public $keys = array();
    public $delayed = array();
    
    public function __construct($db)
    {
        $this->db = $db;
    }
}