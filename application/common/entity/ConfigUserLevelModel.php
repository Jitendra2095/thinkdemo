<?php

namespace app\common\entity;

use think\Model;

class ConfigUserLevelModel extends Model {


    /**
     * @var string 对应的数据表名
     */
    protected $table = 'config_user_level';


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
        if(isset($data['level_name'])){
            $query->level_name = $data['level_name'];
        }
        // $query->team_num = $data['team_num'];
        // $query->valid_num = $data['valid_num'];
        $query->one_level = $data['one_level'];
        $query->two_level = $data['two_level'];
        $query->three_level = $data['three_level'];
        $query->count = $data['count'];
        $query->money = $data['money'];
        $query->one_money = $data['one_money'];
        $query->two_money = $data['two_money'];
        $query->three_money = $data['three_money'];
        $query->deposit_cost = $data['deposit_cost'];
        return $query->save();
    }
}
