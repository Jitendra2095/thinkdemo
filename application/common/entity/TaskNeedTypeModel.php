<?php

namespace app\common\entity;

use think\Model;
use think\Request;

class TaskNeedTypeModel extends Model {


    /**
     * @var string 对应的数据表名
     */
    protected $table = 'task_need_type';

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
     * 添加新数据
     */
    public function addNew($query,$data)
    {
        $query->need_name = $data['need_name'];
        if(!isset($data['create_time'])){
            $query->create_time = time();
        }
        return $query->save();
    }

}
