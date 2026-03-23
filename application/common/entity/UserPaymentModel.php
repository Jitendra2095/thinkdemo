<?php

namespace app\common\entity;

use think\Model;

class UserPaymentModel extends Model {


    /**
     * @var string 对应的数据表名
     */
    protected $table = 'user_payment';

    protected $createTime = 'create_time';

    protected $autoWriteTimestamp = false;

    /**
     * 检查等级大小是否存在
     */
    public static function checkExist($id)
    {
        return self::where('id', $id)->find();
    }

}
