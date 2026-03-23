<?php

namespace app\common\entity;

use think\Model;

class TaskModel extends Model {


    /**
     * @var string 对应的数据表名
     */
    protected $table = 'task';

    protected $createTime = 'create_time';

    protected $autoWriteTimestamp = false;

    /**
     * 检查等级大小是否存在
     */
    public static function checkExist($id)
    {
        return self::where('id', $id)->find();
    }
    /**
     * 获取全部任务分类
     */
    public static function getAllSort()
    {
        $key = TaskTypeModel::column('id');
        $val = TaskTypeModel::column('type_name');
        $arr =  array_combine($key,$val);
        return $arr;
    }
    /**
     * 获取全部任务需求分类
     */
    public static function getAllNeedType()
    {
        $key = TaskNeedTypeModel::column('id');
        $val = TaskNeedTypeModel::column('need_name');
        $arr =  array_combine($key,$val);
        return $arr;
    }
    /**
     * 获取任务分类
     */
    public static function getSort($id)
    {
        return TaskTypeModel::where('id',$id)->value('type_name');
    }
    /**
     * 获取任务需求分类
     */
    public static function getNeedType($id)
    {
        return TaskNeedTypeModel::where('id',$id)->value('need_name');
    }
    /**
     * 获取任务状态
     */
    public static function getStatus($id)
    {
        $arr = [
            0 => '下架',
            1 => '上架',
        ];
        return $arr[$id];
    }
    /**
     * 添加新数据
     */
    public function addNew($query,$data)
    {
        $query->task_url = $data['task_url'];
        $query->demand_side = $data['demand_side'];
        $query->task_num = $data['task_num'];
        $query->level = $data['level'];

        if(isset($data['requirement'])) {
            $query->requirement = $data['requirement'];
        }
        $query->task_price = 1.8;
        $query->status = $data['status'];
        if(!isset($data['create_time'])){
            $query->create_time = time();
        }
        return $query->save();
    }
}
