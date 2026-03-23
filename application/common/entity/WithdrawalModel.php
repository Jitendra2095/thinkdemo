<?php

namespace app\common\entity;

use think\Db;
use think\Model;

class WithdrawalModel extends Model {


    /**
     * @var string 对应的数据表名
     */
    protected $table = 'user_withdrawal';

    protected $createTime = 'createtime';

    protected $autoWriteTimestamp = false;

    /**
     * 获取所有状态
     */
    public static function getAllStatus()
    {
        return [
            0 => '审核中',
            1 => '通过',
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
            2 => '银行卡',
        ];
    }

    /**
     * 获取所有类型
     */
    public static function getAllOpTypes()
    {
        return [
            1 => '佣金提现',
            2 => '奖金提现',
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
        $query->total = $data['total'];
        $query->proof = $data['proof'];
        $query->status = $data['status'];
        $query->serviceCharge = $data['serviceCharge'];
        $query->realMoney = $data['realMoney'];
        $query->create_time = time();
        return $query->save();
    }
}
