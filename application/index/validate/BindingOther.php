<?php
namespace app\index\validate;

use think\Validate;

class BindingOther extends Validate
{
    protected $rule = [
        'types' => 'require',
//        'image' => 'require',
        'account' => 'require',
        'mobile' => 'require',
    ];

    protected $message = [
        'types.require' => '类型不能为空',
//        'image.require' => '图片不能为空',
        'account.require' => '账号不能为空',
        'mobile.require' => '手机号不能为空',

    ];
}