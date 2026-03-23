<?php

namespace app\common\entity;

use think\Model;
use think\Request;

class TaskTypeModel extends Model {


    /**
     * @var string 对应的数据表名
     */
    protected $table = 'task_type';

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
     * 新增
     */
    public function addNew($query,$data)
    {
        $query->type_name = $data['type_name'];
        $query->type_icon = $data['type_icon'];
        if(!isset($data['create_time'])){
            $query->create_time = time();
        }
        return $query->save();
    }

}
