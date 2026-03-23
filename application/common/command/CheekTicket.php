<?php

namespace app\common\command;


use app\common\service\Task\Service;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\Log;
use app\common\entity\UserGameTicket;
ini_set('opcache.revalidate_freq',0);


/**
 * 检查门票是否过期  每分钟执行
 *
 */
class CheekTicket extends Command
{

    protected function configure()
    {
        //设置参数
        $this->setName('cheek_ticket')
            ->setDescription('检查门票是否过期');
    }

    protected function execute(Input $input, Output $output)
    {
        $user_game_ticket = Db::name('user_game_ticket')->where(['status'=>0])->select();
        $times = time();
        $data = [];
        foreach ($user_game_ticket as $item){
            if ($item['orvertime'] <= $times){
                $data[] = ['id'=>$item['id'],'status'=>-1];
            }
        }
        if($data){
            $UserGameTicket = new UserGameTicket();
            $UserGameTicket->isUpdate()->saveAll($data);
            $output->writeln('检查门票，执行完成("有门票更新")');die;
        }

        $output->writeln('检查门票，执行完成("无门票更新")');die;

    }

}