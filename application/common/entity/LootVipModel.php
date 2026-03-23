<?php

namespace app\common\entity;

use think\Model;

class LootVipModel extends Model {


    /**
     * @var string 对应的数据表名
     */
    protected $table = 'loot_vip';

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
        $query->uid = $data['uid'];
        $query->level = $data['level'];
        $query->price = $data['price'];
        $query->gold = $data['gold'];
        if(!isset($data['create_time'])){
            $query->create_time = time();
        }
        return $query->save();
    }
}
