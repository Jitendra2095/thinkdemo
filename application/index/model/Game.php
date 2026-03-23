<?php

namespace app\index\model;

use think\Db;


class Game {

    public function getRoomClassify($class_id){
        $class = Db::name('roomclassify')->where('id',$class_id)->find();
        return $class;
    }

    public function getRoom($room_id,$field = []){
        if(is_array($field) && !empty($field)){
            $room = Db::name('game_room')->where('id',$room_id)->field($field)->find();
        }else{
            $room = Db::name('game_room')->where('id',$room_id)->find();
        }
        return $room;
    }

    /**
     * 获取王者荣耀段位
     * @param $game_rank
     * @return mixed
     */
    public function getGameRank($game_rank){
        if(!in_array($game_rank,[1,2,3,4,5,6,7,8])){
            return "";
        }
        $data = [
            '1'=>"青铜",
            '2'=>"白银",
            '3'=>"黄金",
            '4'=>"铂金",
            '5'=>"钻石",
            '6'=>"星耀",
            '7'=>"最强王者",
            '8'=>"荣耀王者",
        ];
        return $data[$game_rank];
    }

    /**
     * 获取王者荣耀位置
     * @param $game_position
     * @return mixed
     */
    public function getGamePosition($game_position){
        if(!in_array($game_position,[1,2,3,4,5,6])){
            return "";
        }
        $data = [
            '1'=>"全能",
            '2'=>"上单",
            '3'=>"中单",
            '4'=>"打野",
            '5'=>"射手",
            '6'=>"游走",
        ];
        return $data[$game_position];
    }

}
