<?php

namespace app\common\entity;

use think\Db;
use think\Model;

class UserOtherModel extends Model {


    /**
     * @var string 对应的数据表名
     */
    protected $table = 'user_other';

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
            1 => '待审核',
            2 => '通过',
            3 => '拒绝',
        ];
    }
    /**
     * 获取所有类型
     */
    public static function getAllTypes()
    {
        return [
            1 => '抖音',
            2 => '快手',
        ];
    }
    /**
     * 添加新数据
     */
    public function addNew($query,$data)
    {
        $query->uid = $data['uid'];
        $query->types = $data['types'];
        $query->image = $data['image'];
        $query->account = $data['account'];
        $query->mobile = $data['mobile'];
        if(!isset($data['status'])){
            $query->status = 1;
        }else{
            $query->status = $data['status'];
        }
        if(!isset($data['create_time'])){
            $query->create_time = time();
        }else{
            $query->create_time = $data['create_time'];
        }
        return $query->save();
    }
}
