<?php
namespace app\index\validate;


use think\Validate;

class UserTransfer extends Validate
{
    protected $rule = [
        'total' => 'require',
        'mobile' => 'require|checkMobile',
        'trad_password' => 'require',

    ];

    protected $message = [
        'total.require' => '转账金额不能为空',
        'mobile.require' => '手机号不能为空',
        'trad_password.require' => '交易密码不能为空',
    ];
    public function checkMobile($value)
    {
        if (!\app\common\entity\User::checkMobile($value)) {
            return '此账号不存在，请重新填写';
        }
        return true;
    }

}