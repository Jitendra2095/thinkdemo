<?php
namespace app\index\validate;


use think\Validate;

class UserWithdrawal extends Validate
{
    protected $rule = [
        'bank_card' => 'require',
        'bank_name' => 'require',
        'bank_user_name' => 'require',
        'total' => 'require',
//        'trad_password' => 'require',
    ];

    protected $message = [
       'bank_card.require' => '银行卡号不能为空',
       'bank_name.require' => '开户行不能为空',
       'bank_user_name.require' => '开户人不能为空',
       'total.require' => '提现金额不能为空',
//       'trad_password.require' => '交易密码不能为空',
    ];


}