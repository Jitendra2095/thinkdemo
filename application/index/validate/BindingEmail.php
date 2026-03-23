<?php
namespace app\index\validate;

use app\common\entity\UserInviteCode;
use app\index\model\SendCode;
use app\index\model\SendMail;
use think\Validate;

class BindingEmail extends Validate
{
    protected $rule = [

        'email' => 'require|email',
        'code' => 'require',

    ];

    protected $message = [
        'email.require' => '邮箱不能为空',
        'code.require' => '验证码不能为空',

    ];



}