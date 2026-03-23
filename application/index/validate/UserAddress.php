<?php
namespace app\index\validate;

use app\common\entity\UserInviteCode;
use app\index\model\SendCode;
use think\Validate;

class UserAddress extends Validate
{
    protected $rule = [
        'user_name' => 'require',
        'address_area' => 'require',
        'address_detail' => 'require',
        'moblie' => 'require|regex:^1\d{10}$',
    ];

    protected $message = [
       'user_name.require' => '收件人不能为空',
       'address_area.require' => '收货地区不能为空',
       'address_detail.require' => '详细不能为空',
        'moblie.require' => '手机号码不能为空',
    ];


}