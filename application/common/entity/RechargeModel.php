<?php

namespace app\common\entity;

use think\Db;
use think\Model;

class RechargeModel extends Model {


    /**
     * @var string 对应的数据表名
     */
    protected $table = 'user_recharge_log';

    protected $createTime = 'createtime';

    protected $autoWriteTimestamp = false;

    /**
     * 获取所有状态
     */
    public static function getAllStatus()
    {
        return [
            0 => '待支付',
            1 => '已支付',
            -1 => '拒绝',
        ];
    }
    /**
     * 获取所有类型
     */
    public static function getAllTypes()
    {
        return [
            1 => '支付宝',
            2 => '微信',
            3 => '银行卡',
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
    public static function addData($query,$data)
    {
        $query->uid = $data['uid'];
        $query->orderNo = $data['orderNo'];
        $query->types = $data['types'];
        $query->level = $data['level'];
//        $query->total = $data['total'];
        $query->proof = $data['proof'];
        $query->status = $data['status'];
        $query->create_time = time();
        return $query->save();
    }
}
