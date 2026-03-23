<?php

namespace app\common\command;


use app\common\entity\GameRoom;
use app\common\service\Task\Service;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\Log;



/**
 * 检查房间状态  每分钟执行
 *
 */
class CheekRoom extends Command
{

    protected function configure()
    {
        //设置参数
        $this->setName('cheek_room')
            ->setDescription('检查房间状态');
    }

    protected function execute(Input $input, Output $output)
    {
        $game_room = Db::name('game_room')->where(['deleted'=>0,'status'=>['<=',3]])->select();
        $times = time();
        $data = [];
        foreach ($game_room as $item){
            $status = 0;
            if($times >= $item['enrolltime']){
                $status = 1;
            }
            if($times >= $item['readytime']){
                $status = 2;
            }
            if($times >= $item['starttime']){
                $status = 3;
            }

            if($status >= 1){
                $data[] = ['id'=>$item['id'],'status'=>$status];
            }
        }
        if($data){
            $UserGameTicket = new GameRoom();
            $UserGameTicket->isUpdate(true)->saveAll($data);
            $output->writeln('检查房间状态，执行完成("有房间更新")');die;
        }

        $output->writeln('检查房间状态，执行完成("无房间更新")');die;


    }

}