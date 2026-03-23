<?php

namespace app\common\entity;

use think\Db;
use think\Model;

class UserGameTicket extends Model {


    /**
     * @var string 对应的数据表名
     */
    protected $table = 'user_game_ticket';

    protected $autoWriteTimestamp = false;

}
