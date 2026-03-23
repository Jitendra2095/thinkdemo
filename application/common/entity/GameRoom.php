<?php

namespace app\common\entity;

use think\Db;
use think\Model;

class GameRoom extends Model {


    /**
     * @var string 对应的数据表名
     */
    protected $table = 'game_room';

    protected $autoWriteTimestamp = false;

}
