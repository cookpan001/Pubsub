<?php

class Service
{
    private $path = '';
    private $config = array();
    private $param = array();
    
    private $serverName = array(
        'delegate' => 'Server.php',
    );
    
    public function __construct()
    {
        global $argv;
        $this->path = $argv[1];
        $this->config = parse_ini_file($argv[1], true);
        $this->param = $this->parse();
    }
    
    public static function getIp()
    {
        $ret = array();
        $ips = array();
        exec("if [ -e /sbin/ip ];then /sbin/ip -4 addr; else `which ip` -4 addr; fi;", $ret);
        foreach($ret as $line){
            $line = trim($line);
            if('inet ' !== substr($line, 0, 5)){
                continue;
            }
            $ip0 = substr($line, 5);
            $ip = substr($ip0, 0, strpos($ip0, '/'));
            if(substr($ip, 0, 3) == '127'){
                //continue;
            }
            $ips[$ip] = $ip;
        }
        return $ips;
    }
    
    public function start($index)
    {
        if(empty($this->config[$index]['service'])){
            echo "No Service in : $index\n";
            return;
        }
        $service = $this->config[$index]['service'];
        if(!isset($this->serverName[$service])){
            echo "No Service in : $service\n";
            return;
        }
        echo "{$this->serverName[$service]} {$this->path} {$index}\n";
        exec('php '.__DIR__ . DIRECTORY_SEPARATOR . "{$this->serverName[$service]} {$this->path} {$index}");
    }
    
    public function restart($index)
    {
        
    }
    
    public function stop($index)
    {
        
    }
    
    public function parse()
    {
        $ret = array(
            'action' => isset($argv[2]) ? $argv[2] : 'start',
            'service' => isset($argv[3]) ? $argv[3] : '',
            'index' => isset($argv[3]) ? $argv[3] : '',
        );
        return $ret;
    }
    
    public function run()
    {
        if(!method_exists($this, $this->param['action'])){
            exit('invalid action '.$this->param['action']."\n");
        }
        $action = $this->param['action'];
        foreach($this->config as $index => $arr){
            if('' != $this->param['service'] && $arr['service'] != $this->param['service']){
                continue;
            }
            if('' != $this->param['index'] && $index != $this->param['index']){
                continue;
            }
            $this->$action($index);
        }
    }
}
if($argc <= 1){
    exit('Usage: php '.__FILE__." <config_path.ini> [start|stop|restart [service_name [index]]]\n");
}
if(!file_exists($argv[1])){
    exit('ini config file '.$argv[1]." not exists.\n");
}
$app = new Service();
$app->run();