<?php
namespace app\admin\validate;

use think\Validate;

class CreateTaskType extends Validate
{
    protected $rule = [
        'type_name'  =>  'require',
        'type_icon' =>  'require',
    ];

    protected $message  =   [
        'type_name.require' => '请输入分类名称',
        'type_icon.require'     => '请输入分类图标'
    ];
}