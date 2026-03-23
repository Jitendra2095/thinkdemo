<?php
namespace app\admin\validate;

use app\common\entity\GoodsModel;
use app\common\entity\PrizeAlikeConfigModel;
use app\common\entity\UserLevelConfigModel;
use think\Validate;

class editManage extends Validate
{

    protected $rule = [
        'our_level_id' => 'require|checkGood',
        'ratio' => 'require|checkNum',
    ];

    protected $message = [
        'our_level_id.require' => '请选择身份等级',
        'ratio.require' => '请输入奖励比例',
    ];

    /**
     * 检查是否存在商品
     */
    public function checkGood($value, $rule)
    {
        if (!UserLevelConfigModel::checkExist($value)) {
            return '非法操作';
        }
        return true;
    }
    /**
     *  检查是否为正数
     */
    protected function checkNum($value, $rule, $data = [])
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