<?php

namespace app\common\entity;

use think\Model;

class TaskOrderModel extends Model {


    /**
     * @var string 对应的数据表名
     */
    protected $table = 'task_order';

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
     * 获取所有状态
     */
    public static function getAllStatus()
    {
        return [
            0 => '未完成',
            1 => '待审核',
            2 => '审核通过',
            -1 => '不通过审核',
        ];
    }
    public static function getStatus($id)
    {
        $arr = self::getAllStatus();
        return $arr[$id];
    }
    public function addNew($query,$data)
    {
        $query->task_id = $data['task_id'];
        $query->uid = $data['uid'];
        $query->realprice = $data['realprice'];
        $query->status = $data['status'];
        if(isset($data['image_id'])) {
            $query->image_id = $data['image_id'];
        }
        if(isset($data['types'])){
            $query->types = $data['types'];
        }
        $query->receivetime = $data['receivetime'];
        if(isset($data['submittime'])){
            $query->submittime = $data['submittime'];
        }
        if(isset($data['examinetime'])){
            $query->examinetime = $data['examinetime'];
        }
        $res = $query->save();
        if($res){
            return $query->id;
        }
    }


}
