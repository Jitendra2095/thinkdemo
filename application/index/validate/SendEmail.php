<?php
namespace app\index\validate;

use app\common\entity\UserInviteCode;
use app\index\model\SendCode;
use app\index\model\SendMail;
use think\Validate;

class SendEmail extends Validate
{
    protected $rule = [

        'email' => 'require|email',

    ];

    protected $message = [
        'email.require' => '邮箱不能为空',

    ];



}