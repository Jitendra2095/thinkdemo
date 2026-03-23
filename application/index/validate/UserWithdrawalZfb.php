<?php
namespace app\index\validate;


use think\Validate;

class UserWithdrawalZfb extends Validate
{
    protected $rule = [
        'alipay_name' => 'require',
        'alipay_account' => 'require',
        'total' => 'require',
//        'trad_password' => 'require',
    ];

    protected $message = [
       'alipay_name.require' => '支付宝姓名不能为空',
       'alipay_account.require' => '支付宝账号不能为空',
       'total.require' => '提现金额不能为空',
//       'trad_password.require' => '交易密码不能为空',
    ];


}