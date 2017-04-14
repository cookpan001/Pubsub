<?php

namespace cookpan001\Pipeline;

class Server
{
    /**
     * 服务器Socket
     * @var resource 
     */
    public $socket = null;
    public $host = '0.0.0.0';
    public $port = 6379;
    public $interval = 900;
    public $path = './dump.file';
    public $logPath = __DIR__ . DIRECTORY_SEPARATOR;
    public $service = '';
    public $terminate = 0;
    
    public $serverWatcher = null;
    /**
     * 客户端列表
     * @var array
     */
    public $client = array();
    public $defaultLoop = null;
    public $socketLoop = null;
    /**
     * 存储
     * @var array 
     */
    public $databases = array();
    /**
     * 命令处理对象列表 
     * @var array 
     */
    public $commands = array();
    /**
     * 需要定时处理的key
     * @var array
     */
    public $timerKeys = array();
    /**
     * 预处理
     */
    public $handler = null;//hook
    /**
     * 处理服务器状态
     */
    public $daemon = null;
    /**
     * 服务器启动时间
     * @var int
     */
    public $uptime = null;
    /**
     * 持久化处理对象
     * @var type 
     */
    public $persistent = null;
    public $allConnections = 0;
    public $allCmds = 0;
    /**
     * 上次写回磁盘后添加更新数
     * @var type 
     */
    public $newUpdate = 0;
    
    public function __construct()
    {
        $this->daemonize();
        $this->setParam();
        $this->initStream();
        $this->uptime = time();
        $this->loop();
        $this->prepare();
        $this->handler = new Handler($this);
        $this->daemon = new Daemon($this);
        $this->persist();
    }
    
    public function __destruct()
    {
        if($this->socket){
            socket_close($this->socket);
        }
        if($this->persistent){
            $this->persistent->save();
        }
        foreach($this->client as $conn){
            $conn->_close();
        }
    }
    
    public function setParam()
    {
        global $argc, $argv;
        if($argc < 3){
            return;
        }
        $config = parse_ini_file($argv[1], true);
        $index = $argv[2];
        $this->port = $config[$index]['port'];
        $this->service = $config[$index]['service'];
        if(isset($config[$index]['host'])){
            $this->host = $config[$index]['host'];
        }
        if(isset($config[$index]['interval'])){
            $this->interval = $config[$index]['interval'];
        }
        if(isset($config[$index]['path'])){
            $this->path = $config[$index]['path'];
        }
        if(isset($config[$index]['log_path']) && $config[$index]['log_path']){
            $this->logPath = $config[$index]['log_path'] . DIRECTORY_SEPARATOR;
        }
    }
    /**
     * 生成服务器的socket
     */
    public function connect()
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if(!$socket){
            $this->log("Unable to create socket");
            exit(1);
        }
        if(!socket_bind($socket, $this->host, $this->port)){
            $this->log("Unable to bind socket");
            exit(1);
        }
        if(!socket_listen($socket)){
            $this->log("Unable to bind socket");
            exit(1);
        }
        socket_set_nonblock($socket);
        $this->socket = $socket;
    }
    
    public function loop()
    {
        $this->defaultLoop = \EvLoop::defaultLoop();
        if (\Ev::supportedBackends() & ~\Ev::recommendedBackends() & \Ev::BACKEND_KQUEUE) {
            if(PHP_OS != 'Darwin'){
                $this->socketLoop = new \EvLoop(\Ev::BACKEND_KQUEUE);
            }
        }
        if (!$this->socketLoop) {
            $this->socketLoop = $this->defaultLoop;
        }
    }
    /**
     * 生成各种命令对应的处理对象
     */
    public function prepare()
    {
        $map = array('ZSet', 'Strings');
        foreach($map as $class){
            $ref = new \ReflectionClass(__NAMESPACE__."\\Object\\".$class);
            foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $name = $method->getName();
                if (substr($name, 0, 1) === '_') {
                    continue;
                }
                $classname = __NAMESPACE__."\\Object\\".$class;
                $this->commands[$name] = new $classname();
            }
        }
    }
    /**
     * 持久化, 定时
     */
    public function persist()
    {
        $persistent = new Sync\File($this, $this->path);
        $this->persistent = $persistent;
        $w = new \EvPeriodic(0, $this->interval, NULL, function () use($persistent) {
            $persistent->save();
            $persistent->server->newUpdate = 0;
        });
        $this->persistent->setWatcher($w);
    }
    /**
     * 生成守护进程
     */
    public function daemonize()
    {
        umask(0); //把文件掩码清0  
        if (pcntl_fork() != 0){ //是父进程，父进程退出  
            exit();  
        }  
        posix_setsid();//设置新会话组长，脱离终端  
        if (pcntl_fork() != 0){ //是第一子进程，结束第一子进程     
            exit();  
        }
    }
    
    public function stop()
    {
        \Ev::stop();
    }
    
    public function restart()
    {
        global $argv;
        $cmd = 'php '.__FILE__ . implode(' ', $argv);
        exec($cmd);
    }
    
    public function initStream()
    {
        fclose(STDIN);  
        fclose(STDOUT);  
        fclose(STDERR);
        global $STDIN, $STDOUT, $STDERR;
        $filename = $this->logPath. "{$this->service}.log";
        $this->output = fopen($filename, 'a');
        $this->errorHandle = fopen($this->logPath . "{$this->service}.error", 'a');
        $STDIN  = fopen('/dev/null', 'r'); // STDIN
        $STDOUT = $this->output; // STDOUT
        $STDERR = $this->errorHandle; // STDERR
        $this->installSignal();
        if (function_exists('gc_enable')){
            gc_enable();
        }
        register_shutdown_function(array($this, 'fatalHandler'));
        set_error_handler(array($this, 'errorHandler'));
    }
    
    public function installSignal()
    {
        $this->signalWatcher[] = new \EvSignal(SIGTERM, array($this, 'signalHandler'));
        $this->signalWatcher[] = new \EvSignal(SIGUSR2, array($this, 'signalHandler'));
    }
    
    public function signalHandler($w)
    {
        $this->log(json_encode(array(SIGTERM, SIGUSR2, $w->signum)));
        switch ($w->signum) {
            case SIGTERM:
                $this->terminate = 1;
                $this->stop();
                break;
            case SIGUSR2:
                $this->terminate = 1;
                $this->stop();
                $this->restart();
                break;
            default:
                break;
        }
    }
    
    public function errorHandler($errno, $errstr, $errfile, $errline, $errcontext)
    {
        $date = $this->date();
        try {
            throw new Exception;
        } catch (Exception $exc) {
            $errcontext = $exc->getTraceAsString();
            $str = sprintf("%s\t%s:%d\nerrcode:%d\t%s\n%s\n", $date, $errfile, $errline, $errno, $errstr, $errcontext);
            if(!empty($this->errorHandle)){
                fwrite($this->errorHandle, $str);
            }
        }
        return true;
    }
    
    public function fatalHandler()
    {
        if($this->terminate){
            return;
        }
        $date = $this->date();
        $error = error_get_last();
        fwrite($this->errorHandle, $date."\t".var_export($error, true)."\n");
    }
    
    public function date()
    {
        list($m1, ) = explode(' ', microtime());
        return $date = date('Y-m-d H:i:s') . substr($m1, 1);
    }
    /**
     * 写日志
     */
    public function log($message)
    {
        list($m1, ) = explode(' ', microtime());
        $date = date('Y-m-d H:i:s') . substr($m1, 1);
        if(is_array($message)){
            echo $date."\t". json_encode($message)."\n";
        }else if(is_object($message)){
            echo $date."\t".json_encode($message)."\n";
        }else{
            echo $date."\t".$message."\n";
        }
    }
    /**
     * 获取命令对应的处理对象
     */
    public function getCommandObj($cmd)
    {
        if(isset($this->commands[$cmd])){
            return $this->commands[$cmd];
        }
        //$this->log('Err unknown command: '.$cmd);
        return null;
    }
    /**
     * 读取连接中发来的数据
     * @return boolean|string
     */
    public function read(Connection $conn)
    {
        $tmp = '';
        socket_recv($conn->clientSocket, $tmp, 1500, MSG_DONTWAIT);
        $errorCode = socket_last_error($conn->clientSocket);
        //$this->log("read from connection: ".$conn->id.", errorCode: $errorCode, error:".socket_strerror($errorCode));
        if (EAGAIN == $errorCode || EINPROGRESS == $errorCode) {
            socket_clear_error($conn->clientSocket);
            return '';
        }
        if( (0 === $errorCode && null === $tmp) ||EPIPE == $errorCode || ECONNRESET == $errorCode){
            $conn->_close();
            return false;
        }
        return $tmp;
    }
    /**
     * 向连接中写入数据
     * @return boolean
     */
    public function write(Connection $conn, $str)
    {
        $num = socket_write($conn->clientSocket, $str, strlen($str));
        $errorCode = socket_last_error($conn->clientSocket);
        //$this->log("write len: ".json_encode($num).", errorCode: $errorCode, ". socket_strerror($errorCode).", ". json_encode($str));
        if((EPIPE == $errorCode || ECONNRESET == $errorCode)){
            $conn->_close();
            return false;
        }
        //$this->log("write len: ". json_encode($num) .", ". json_encode($str) );
        return $num;
    }
    /**
     * 开始监听
     */
    public function start()
    {
        $socket = $this->socket;
        $that = $this;
        $this->serverWatcher = new \EvIo($this->socket, \Ev::READ, function () use ($that, $socket){
            $clientSocket = socket_accept($socket);
            $that->process($clientSocket);
            ++$that->allConnections;
        });
        \Ev::run();
    }
    /**
     * 处理到来的新连接
     */
    public function process($clientSocket)
    {
        socket_set_nonblock($clientSocket);
        $conn = new Connection($this, $clientSocket);
        $that = $this;
        $watcher = new \EvIo($clientSocket, \Ev::READ, function() use ($that, $conn){
            $str = $that->read($conn);
            if($str){
                $commands = Request::decode($str);
                $that->handle($conn, $commands);
            }
        });
        $hash = uniqid();
        $conn->_setId($hash);
        $conn->_setWatcher($watcher);
        $this->client[$hash] = $conn;
        $this->socketLoop->run();
        //$this->log('connection '.$hash);
        \Ev::run();
    }
    /**
     * 处理连接中发来的指令
     */
    public function handle(Connection $conn, $commands)
    {
        $db = $conn->db();
        if(!isset($this->databases[$db])){
            $this->databases[$db] = new Database($db);
        }
        $ret = array();
        foreach($commands as $arr){
            if(empty($arr) || !is_array($arr)){
                $this->log("wrong message: ". json_encode($arr));
                continue;
            }
            ++$this->allCmds;
            $this->log("incomming message: ". json_encode($arr));
            $cmd = strtolower(array_shift($arr));
            $key = isset($arr[0]) ? $arr[0] : null;
            $obj = $this->getCommandObj($cmd);
            if(method_exists($this->daemon, $cmd)){
                $reply = call_user_func_array(array($this->daemon, $cmd), array($conn, $arr));
            }else if($key && isset($this->timerKeys[$key]) && method_exists($this->handler, $cmd)){
                $reply = call_user_func_array(array($this->handler, $cmd), array($conn, $arr));
            }else if($obj && method_exists($obj, $cmd)){
                array_unshift($arr, $this->databases[$db]);
                $reply = call_user_func_array(array($obj, $cmd), $arr);
            }else if(method_exists($this->handler, $cmd)){
                $reply = call_user_func_array(array($this->handler, $cmd), array($conn, $arr));
            }else{
                $reply = new Reply\Error('ERR command not supported');
            }
            if(!($reply instanceof Reply\NoReply)){
                $ret[] = Response::serialize($reply);
            }
        }
        if(!empty($ret)){
            $this->write($conn, implode('', $ret));
        }
    }
    /**
     * 推送消息给zread订阅的连接
     */
    public function push($key, $message)
    {
        $data = Response::serialize($message);
        while(count($this->timerKeys[$key])){
            if(count($this->timerKeys[$key]) > 1){
                $i = array_rand($this->timerKeys[$key]);
            }else{
                $i = key($this->timerKeys[$key]);
            }
            $connection = $this->timerKeys[$key][$i];
            if(!isset($this->client[$i])){
                unset($this->timerKeys[$key][$i]);
                continue;
            }
            $ret = $this->write($connection, $data);
            if(false === $ret){
                unset($this->timerKeys[$key][$i]);
                continue;
            }
            $tmp = $this->read($connection);
            if (false === $tmp) {
                unset($this->timerKeys[$key][$i]);
                continue;
            }
            return true;
        }
        return false;
    }
}

ini_set('display_errors', 'On');
error_reporting(E_ALL);

include __DIR__.DIRECTORY_SEPARATOR.'autoload.php';
include __DIR__.DIRECTORY_SEPARATOR.'base.php';

$server = new Server();
$server->connect();
$server->start();