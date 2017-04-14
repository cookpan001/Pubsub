<?php

namespace cookpan001\Pipeline;

class Connection
{
    public $watcher;
    public $clientSocket;
    /**
     * @var Server 
     */
    public $server;
    public $db = 0;
    public $id = 0;
    
    public $keys = array();
    
    public function __construct($server, $socket)
    {
        $this->server = $server;
        $this->clientSocket = $socket;
    }
    
    public function __destruct()
    {
        $this->_close();
    }
    
    public function _setWatcher($watcher)
    {
        $this->watcher = $watcher;
    }
    
    public function _setId($id)
    {
        $this->id = $id;
    }
    
    public function _addKey($key)
    {
        $this->keys[$key] = $key;
    }
    
    public function _close()
    {
        if($this->watcher){
            $this->watcher->stop();
        }
        $this->watcher = null;
        if($this->clientSocket){
            socket_close($this->clientSocket);
        }
        $this->clientSocket = null;
        if($this->server){
            unset($this->server->client[$this->id]);
            unset($this->server);
        }
    }
    
    public function reply($message)
    {
        $data = Response::serialize($message);
        $ret = $this->server->write($this, $data);
        if(false === $ret){
            unset($this->client[$this->id]);
            return false;
        }
        $tmp = $this->server->read($this);
        if (false === $tmp) {
            unset($this->client[$this->id]);
            return false;
        }
        return true;
    }
    
    public function getSocket()
    {
        return $this->clientSocket;
    }
    
    public function select($db)
    {
        $this->db = $db;
    }
    
    public function db()
    {
        return $this->db;
    }
}