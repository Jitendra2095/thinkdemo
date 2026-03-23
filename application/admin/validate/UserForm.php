<?php
namespace app\admin\validate;

use think\Validate;

class UserForm extends Validate
{
    protected $rule = [
        'higher'  =>  'require',
        'mobile'  =>  'require',
        'password' =>  'require',
        're_password' =>  'require',
//        'trad_password' =>  'require',
//        're_trad_password' =>  'require',
    ];

    protected $message  =   [
        'higher.require' => '请输入邀请人',
        'mobile.require'     => '请输入会员账号',
        'password.require'     => '请输入登陆密码',
        're_password.require'     => '请再次输入登陆密码',
//        'trad_password.require'     => '请输入交易密码',
//        're_trad_password.require'     => '请再次输入交易密码',
    ];
}