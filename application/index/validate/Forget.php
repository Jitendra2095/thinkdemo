<?php
namespace app\index\validate;

use app\common\entity\UserInviteCode;
use app\index\model\SendCode;
use app\index\model\SendMail;
use think\Validate;

class Forget extends Validate
{
    protected $rule = [
        'mobile' => 'require',
        'code' => 'require',
        'password' => 'require|min:6',
//        're_password' => 'require|confirm:password',
    ];

    protected $message = [
        'code.require' => '验证码不能为空',
        'mobile.require' => '手机号不能为空',
        'password.require' => '密码不能为空',
        'password.min' => '密码最少六位数',
//        're_password.confirm' => '两次密码必须一致',

    ];
}