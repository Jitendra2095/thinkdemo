<?php
namespace app\index\validate;


use think\Validate;

class SetPayment extends Validate
{
    protected $rule = [
        'bank_card' => 'require',
        'bank_name' => 'require',
        'bank_user_name' => 'require',
    ];

    protected $message = [
       'bank_card.require' => '银行卡号不能为空',
       'bank_name.require' => '开户行不能为空',
       'bank_user_name.require' => '开户人不能为空',
    ];


}