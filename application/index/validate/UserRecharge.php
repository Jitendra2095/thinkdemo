<?php
namespace app\index\validate;


use think\Validate;

class UserRecharge extends Validate
{
    protected $rule = [
        'types' => 'require|between:1,3',
        'total' => 'require',
        'proof' => 'require',
    ];

    protected $message = [
       'types.require' => '充值类型不能为空',
       'total.require' => '充值金额不能为空',
       'proof.require' => '充值凭证不能为空',
    ];


}