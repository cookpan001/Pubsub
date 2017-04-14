<?php

namespace cookpan001\Pubsub\Sync;

class Sqlite
{
    private $path;

    public function __construct($path)
    {
        $this->path = $path;
    }
}