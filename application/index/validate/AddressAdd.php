<?php
namespace app\index\validate;

use app\common\entity\UserInviteCode;
use app\index\model\SendCode;
use think\Validate;

class AddressAdd extends Validate
{
    protected $rule = [
        'zfb_account' => 'require',
        'real_name' => 'require',
    ];

    protected $message = [
       'real_name.require' => '名称不能为空',
        'zfb_account.require' => '支付宝账号不能为空',
    ];


}