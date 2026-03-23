<?php
namespace app\admin\validate;

use think\Validate;

class CreateNeedsType extends Validate
{
    protected $rule = [
        'need_name'  =>  'require',
    ];

    protected $message  =   [
        'need_name.require' => '请输入需求分类名称',
    ];
}