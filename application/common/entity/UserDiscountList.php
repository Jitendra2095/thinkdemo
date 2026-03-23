<?php

namespace app\common\entity;

use think\Db;
use think\Model;

class UserDiscountList extends Model {


    /**
     * @var string 对应的数据表名
     */
    protected $table = 'user_discount_list';

    protected $autoWriteTimestamp = false;

}
