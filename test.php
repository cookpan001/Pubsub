<?php


//$recommend = Ev::recommendedBackends();
//$support = Ev::supportedBackends();
//$recommend = Ev::backend();
//if($recommend & Ev::BACKEND_SELECT ){
//    var_dump('select');
//}
//if($recommend & Ev::BACKEND_POLL ){
//    var_dump('poll');
//}
//if($recommend & Ev::BACKEND_EPOLL ){
//    var_dump('epoll');
//}
//if($recommend & Ev::BACKEND_KQUEUE ){
//    var_dump('kqueue');
//}
//if($support & Ev::BACKEND_SELECT ){
//    var_dump('support select');
//}
//if($support & Ev::BACKEND_POLL ){
//    var_dump('support poll');
//}
//if($support & Ev::BACKEND_EPOLL ){
//    var_dump('support epoll');
//}
//if($support & Ev::BACKEND_KQUEUE ){
//    var_dump('support kqueue');
//}
//
//return;
$i = 500000;

$j = 0;
$arr = array();
$key = intval(microtime(true)*1000);
while($j < $i){
    $arr[$key + $j] = '$recommend = Ev::recommendedBackends();$recommend = Ev::recommendedBackends();'.mt_rand(1, $i);
    ++$j;
}
var_dump(memory_get_usage()/1024/1024);
$m2 = microtime(true);
asort($arr);
$m3 = microtime(true);
var_dump($m3 - $m2);
//array_unshift($arr, 1);
//array_unshift($arr, 10000);