<?php
namespace app\index\validate;


use think\Validate;

class CreateTask extends Validate
{
    protected $rule = [
        'sort_id' => 'require',
        'need_type_id' => 'require',
        'task_url' => 'require',
        'demand_side' => 'require|checkMobile',
        'task_num' => 'require',
        'task_price' => 'require',
    ];
    protected $message = [
        'sort_id.require' => '分类名称不能为空',
        'need_type_id.require' => '需求分类不能为空',
        'task_url.require' => '链接不能为空',
        'demand_side.require' => '需求方不能为空',
        'task_num.require' => '任务数量不能为空',
        'task_price.require' => '任务价格不能为空',
    ];
    public function checkMobile($value)
    {
        if (!\app\common\entity\User::checkMobile($value)) {
            return '此账号不存在，请重新填写';
        }
        return true;
    }

}