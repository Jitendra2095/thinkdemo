<?php
namespace app\admin\validate;

use think\Request;
use think\Validate;

class UserParticipateGameList extends Validate
{

    protected $rule = [
        'bonus' => 'require',
        'goldcoin' => 'require',
        'ranking' => 'require',
        'eliminate_num' => 'require',
        'win_num' => 'require',
    ];

    protected $message = [
        'bonus.require' => '请填写奖金',
        'goldcoin.require' => '请填写金币',
        'ranking.require' => '请填写排名',
        'eliminate_num.require' => '请填写淘汰数',
        'win_num.between'  => '请填写吃鸡数/获奖数',
    ];


}