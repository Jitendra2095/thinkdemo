<?php

namespace app\common\entity;

use think\Db;
use think\Model;

class UserLevelConfigModel extends Model {


    /**
     * @var string 对应的数据表名
     */
    protected $table = 'user_level_config';

    protected $createTime = 'create_at';

    protected $autoWriteTimestamp = false;

    /**
     * 检查等级大小是否存在
     */
    public static function checkExist($id)
    {
        return self::where('id', $id)->find();
    }
    /**
     * 获取等级名称
     */
    public function getLevelName($id)
    {
        return self::where('id', $id)->value('level_name');
    }
    /**
     * 新增数据
     */
    public function addNew($query,$data)
    {
        $query->id = $data['id'];
        $query->level_name = $data['level_name'];
        $query->description = $data['description'];
        $query->condition = $data['condition'];
        $query->next_num = $data['next_num'];
        $query->good_id = $data['good_id'];
        $query->good_num = $data['good_num'];
        return $query->save();
    }
}
