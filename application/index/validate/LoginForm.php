<?php
namespace app\index\validate;

use think\Validate;

class LoginForm extends Validate
{
    protected $rule = [
        'mobile' => 'require',
        'password' => 'require',
        '__token__' => 'token',
    ];

    protected $message = [
        'mobile.require' => '账号不能为空',
        'password.require' => '密码不能为空'
    ];

}