<?php

namespace cookpan001\Pubsub;

class Request
{
    const END = "\r\n";
    
    private static function parse($str)
    {
        return preg_split('#\s+#', $str);
    }
    
    public static function decode($str)
    {
        if(empty($str)){
            return array();
        }
        $pos = 0;
        $command = array();
        $len = strlen($str);
        while($pos < $len){
            if($str[$pos] != '*'){
                $position = strpos($str, self::END, $pos);
                if(false === $position){
                    $command[] = self::parse(substr($str, $pos));
                    $pos += strlen($str);
                    continue;
                }
                if($position != $pos){
                    $command[] = self::parse(substr($str, $pos, $position - $pos));
                    $pos += $position - $pos;
                }
                $pos += 2;
                continue;
            }
            ++$pos;
            $tmpCmd = array();
            $count = '';
            while($str[$pos] != "\r"){
                $count .= $str[$pos];
                ++$pos;
            }
            $pos += strlen(self::END);
            $count = intval($count);
            while($count){
                ++$pos;
                $strlen = '';
                while($str[$pos] != "\r"){
                    $strlen .= $str[$pos];
                    ++$pos;
                }
                $pos += strlen(self::END);
                $tmpCmd[] = substr($str, $pos, intval($strlen));
                $pos += $strlen + strlen(self::END);
                $count--;
            }
            $command[] = $tmpCmd;
        }
        return $command;
    }
    
    public static function encode()
    {
        $command = func_get_args();
        if (count($command) == 1) {
            $command = array_pop($command);
        }
        if (is_array($command)) {
            // Use unified command format
            $s = '*' . count($command) . self::END;
            foreach ($command as $m) {
                $s.='$' . strlen($m) . self::END;
                $s.=$m . self::END;
            }
        } else {
            $s = $command . self::END;
        }
        return $s;
    }
}
//$end = "\r\n";
//$str = "*2{$end}$3{$end}get{$end}$3{$end}abc{$end}set abc 1{$end}zadd 100 member";
//var_dump(Request::decode("get abc"));
//var_dump(Request::decode($str));
//$req = new \Server\Request();
//$req->setCmd('zadd')
//    ->addParameter('value')
//    ->addParameter('key');
//$str = $req->encode();
////var_dump($str);
//
//$req2 = new \Server\Request();
//$req2->decode($str);
//var_dump($req2->cmd(), $req2->para());