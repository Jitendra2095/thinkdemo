<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------
function api_template_data(int $code, string $msg,   $data = []): array
{
    return  [
        'code'  => $code,
        'msg'   => $msg,
        'data'  => $data
    ];
}

function get_project_has()
{
    $list = db('project_log')->field('username')
        ->whereTime('create_at','today')
        ->group('username')
        ->select();
    foreach ($list as $v){
        if($v['username']){
            $userArr[] = $v['username'];
        }
    }
    if(isset($userArr)){
        $user_arr = array_unique($userArr);
        return $user_arr;
    }else{
        return [];
    }

}
/**
 * postMan 返回json信息
 */
function autoJson($code = 0, $info = array(), $msg ='操作成功')
{
    $json =  ['code' => $code, 'msg' => $msg, 'info'=>$info];
    return json($json);
}

function autoOrder($prefix = 'SN', $length = 8){
    $arr = array_merge(range('A','Z'), range(0, 9));
    $arrstr = $str = '';

    foreach ($arr as $v) $arrstr .= $v;

    for ($i=0; $i<$length; $i++) {
        $num = rand(0, strlen($arrstr));
        $str .= substr($arrstr, $num, 1);
    }
    return $prefix .date('Ymd'). $str;
}
//创建TOKEN
function createToken() {
    $code = chr(mt_rand(0xB0, 0xF7)) . chr(mt_rand(0xA1, 0xFE)) .       chr(mt_rand(0xB0, 0xF7)) . chr(mt_rand(0xA1, 0xFE)) . chr(mt_rand(0xB0, 0xF7)) . chr(mt_rand(0xA1, 0xFE));
    session('TOKEN', authcode($code));
    return authcode($code);
}
//判断TOKEN
function checkToken($token) {
    if ($token == session('TOKEN')) {
        session('TOKEN', NULL);
        return TRUE;
    } else {
        return FALSE;
    }
}
/* 加密TOKEN */
function authcode($str) {
    $key = "YOURKEY";
    $str = substr(md5($str), 8, 10);
    return md5($key . $str);
}
function now ()
{
    return date('Y-m-d H:i:s',time());
}
