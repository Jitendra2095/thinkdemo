<?php
namespace app\index\validate;


use think\Validate;

class SetPaymentZfb extends Validate
{
    protected $rule = [
        'alipay_name' => 'require',
        'alipay_account' => 'require',
        'pay_image_id' => 'require',
    ];

    protected $message = [
       'alipay_name.require' => '支付宝姓名不能为空',
       'alipay_account.require' => '支付宝账号不能为空',
       'pay_image_id.require' => '二维码不能为空',
    ];


}