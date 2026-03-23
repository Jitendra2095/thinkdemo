<?php
namespace app\index\validate;

use think\Validate;
use app\common\model\Goods;

class Addcart extends Validate
{
    protected $rule = [
        'type' => 'in:1,2,3',
        'goodsid' => 'require|issetgoods',
    ];

    protected $message = [
        'type.in' => '类型错误',
        'goodsid.require' => '商品id不能为空',
    ];
    public function issetgoods($value, $rules, $data = [])
    {
        $info = Goods::goodsdetail($value);
        if(empty($info)){
            return '商品不存在';
        }
        return true;
    }

}