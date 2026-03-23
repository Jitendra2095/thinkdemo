<?php
namespace app\index\validate;

use app\common\entity\GoodsModel;
use app\common\entity\UserAddressModel;
use think\Validate;

class AddOrder extends Validate
{
    protected $rule = [
        'good_id' => 'require|checkGoods',
        'num' => 'require|checkNum',
        'address_id' => 'require|checkAddress',
        'trad_password' => 'require',
    ];

    protected $message = [
        'good_id.in' => '商品id不能为空',
        'num.require' => '购买数量不能为空',
        'address_id.require' => '地址id不能为空',
        'trad_password.require' => '交易密码不能为空',
    ];
    public function checkGoods($value)
    {
        if(!GoodsModel::checkExist($value)){
            return '商品不存在';
        }
        return true;
    }
    public function checkAddress($value)
    {
        if(!UserAddressModel::checkExist($value)){
            return '地址不存在';
        }
        return true;
    }
    /**
     *  检查是否为正数
     */
    protected function checkNum($value)
    {
        if(!is_numeric($value)){
            return '请输入数字';
        }
        if ($value  < 0) {
            return '请输入正数';
        }
        return true;
    }
}