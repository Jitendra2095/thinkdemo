<?php

namespace app\common\entity;

use think\Model;

class ConfigPublishModel extends Model {


    /**
     * @var string 对应的数据表名
     */
    protected $table = 'config_publish';


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
        $query->num = $data['num'];
        $query->price = $data['price'];
        return $query->save();
    }
}
