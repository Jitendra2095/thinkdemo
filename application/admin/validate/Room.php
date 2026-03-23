<?php
namespace app\admin\validate;

use think\Request;
use think\Validate;

class Room extends Validate
{

    protected $rule = [
        'roomname' => 'require',
        'enrolltime' => 'require',
//        'room_url' => 'require',
        'readytime' => 'require',
        'starttime' => 'require',
        'ticket_id' => 'require',
        'game_type'   => 'number|between:1,100000',
        'match_type'   => 'number|between:1,100000',
        'mobile_type'   => 'number|between:1,100000',
        'roomgame_type'   => 'number|between:1,100000',
        'reward_type'   => 'number|between:1,100000',
        ];

    protected $message = [
        'roomname.require' => '房间名称不能为空',
//        'room_url.require' => '比赛房间链接不能为空',
        'enrolltime.require' => '请填写比赛报名时间',
        'readytime.require' => '请填写比赛准备时间',
        'starttime.require' => '请填写比赛开始时间',
        'game_type.between'  => '请选择游戏分类',
        'match_type.between'  => '请选择比赛类型分类',
        'mobile_type.between'  => '请选择端游类型分类',
        'roomgame_type.between'  => '请选择房间赛制分类',
        'reward_type.between'  => '请选择奖励方式',
        'ticket_id.require'  => '请选择门票',
    ];


}