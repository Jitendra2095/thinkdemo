<?php
namespace app\admin\validate;

use think\Request;
use think\Validate;

class Roomclassify extends Validate
{

    protected $rule = [
        'title' => 'require',
    ];

    protected $message = [
        'title.require' => '分类名称不能为空',
    ];


}