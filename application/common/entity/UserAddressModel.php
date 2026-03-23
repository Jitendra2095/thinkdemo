<?php

namespace app\common\entity;

use think\Db;
use think\Model;

class UserAddressModel extends Model {


    /**
     * @var string 对应的数据表名
     */
    protected $table = 'user_address';

    protected $createTime = 'create_time';

    protected $autoWriteTimestamp = false;


    public static function getAllStatus()
    {
        return [
            0 => '常用',
            1 => '默认',
        ];
    }
    /**
     * 检查等级大小是否存在
     */
    public static function checkExist($id)
    {
        return self::where('id', $id)->find();
    }
    /**
     * 新增数据
     */
    public static function addRess($query,$data)
    {
        $query->uid = $data['uid'];
        $query->moblie = $data['moblie'];
        $query->user_name = $data['user_name'];
        $query->status = $data['status'];
        $query->address_detail = $data['address_detail'];
        $query->address_area = $data['address_area'];
        $query->create_time = time();
        return $query->save();
    }
}
