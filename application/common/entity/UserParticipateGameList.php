<?php

namespace app\common\entity;

use think\Db;
use think\Model;

class UserParticipateGameList extends Model {


    /**
     * @var string 对应的数据表名
     */
    protected $table = 'user_participate_game_list';

    protected $autoWriteTimestamp = false;

}
