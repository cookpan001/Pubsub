<?php

namespace cookpan001\Pubsub;

use cookpan001\Pubsub\Reply\NoReply;

class Handler
{
    /**
     * @var Server
     */
    private $server;
    public $timers;

    public function __construct($server)
    {
        $this->server = $server;
        $this->timers = array();
    }
    
    public function zadd(Connection $connection, $arr)
    {
        list($key, $score, $member) = $arr;
        $database = $this->server->databases[$connection->db()];
        return $this->setTimer($database, $key, $score, $member);
    }

    public function setTimer($database, $key, $score, $member)
    {
        $destTime = $score * 0.001;
        $now = microtime(true);
        $obj = $this->server->getCommandObj('zadd');
        $oldTime = $obj->zscore($database, $key, $member);
        if($destTime - 1 <= $now){
            $ret = $this->server->push($key, $member);
            if(!$ret){
                $database->delayed[$key][] = $member;
            }
            if(!is_null($oldTime)){
                $md5 = md5($member);
                if(isset($this->timers[$md5])){
                    $this->timers[$md5]->stop();
                    unset($this->timers[$md5]);
                }
                //发送成功
                if($ret){
                    $obj->zrem($database, $key, $member);
                }
                return 0;
            }
            return 1;
        }
        $ret = 0;
        if(!$oldTime){
            $ret = $obj->zadd($database, $key, $score, $member);
        }
        $this->server->log("timer message will be sent in:".date('Y-m-d H:i:s', $destTime));
        $md5 = md5($member);
        if(isset($this->timers[$md5])){
            if($score == $oldTime){
                return $ret;
            }
            $this->timers[$md5]->stop();
            $this->timers[$md5] = null;
        }
        $server = $this->server;
        $handler = $this;
        //到时间，把消息推送出去
        $this->timers[$md5] = new \EvTimer($destTime - $now, 0, function ($w) use ($server, $handler, $md5,
                $obj, $key, $member, $database){
            $ret = $server->push($key, $member);
            /**
             * SQLite3 or Redis or File
             * @todo 处理生产者连接，已经生成数据，但消费者还没有连接
             */
            if(!$ret){
                $database->delayed[$key][] = $member;
            }
            $obj->zrem($database, $key, $member);
            $w->stop();
            unset($handler->timers[$md5]);
            $server->log("Time is up. message: {$member} sent.");
        });
        return $ret;
    }
    
    public function zrem(Connection $connection, $arr)
    {
        $key = array_shift($arr);
        $count = 0;
        foreach($arr as $member){
            $obj = $this->server->getCommandObj('zrem');
            $database = $this->server->databases[$connection->db()];
            $oldTime = $obj->zscore($database, $key, $member);
            if(!is_null($oldTime)){
                $md5 = md5($member);
                if(isset($this->timers[$md5])){
                    $this->timers[$md5]->stop();
                    unset($this->timers[$md5]);
                }
                $obj->zrem($database, $key, $member);
                ++$count;
            }
        }
        return $count;
    }
    
    private function sendMessages(Database $database, Connection $connection, $key)
    {
        $members = array();
        $now = time() * 1000;
        foreach($database->keys[$key] as $member => $score){
            if($score <= $now){
                $members[] = $member;
                $md5 = md5($member);
                if(isset($this->timers[$md5])){
                    $this->timers[$md5]->stop();
                    unset($this->timers[$md5]);
                }
                unset($database->keys[$key][$member]);
                continue;
            }
            $this->setTimer($database, $key, $score, $member);
        }
        if($members){
            $connection->reply($members);
        }
    }
    
    public function zread(Connection $connection, $arr)
    {
        list($key) = $arr;
        if(!isset($this->server->timerKeys[$key][$connection->id])){
            $this->server->timerKeys[$key][$connection->id] = $connection;
            $connection->_addKey($key);
            $database = $this->server->databases[$connection->db()];
            //$this->server->log("delay: ". json_encode($database->delayed));
            if(isset($database->delayed[$key])){//处理因断连接而滞留的消息
                $delayed = $database->delayed[$key];
                unset($database->delayed[$key]);
                return $delayed;
            }
            //$this->server->log("keys: ". json_encode($database->keys));
            if(isset($database->keys[$key])){//处理因没有订阅而存储的消息
                //先回复申请注册的连接
                $this->sendMessages($database, $connection, $key);
                //已经回复过，返回一个NoReply对象
                return NoReply::instance();
            }
        }
        return 1;
    }
}