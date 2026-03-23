<?php

namespace app\common\command;


use app\common\service\Task\Service;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\Log;


/**
 * 任务一键托管  每分钟执行
 * 
 */
class OneClick extends Command
{

    protected function configure()
    {
        //设置参数
        $this->setName('one_click')
             ->setDescription('任务一键托管');
    }

    protected function execute(Input $input, Output $output)
    {

        Db::table('deposit')
            ->where('status',1)
            ->chunk(100,function ($data) {
                foreach ($data as $k =>$v){
                    $query = new Service();
                    $query->doFirst($v['uid']);
                }
            },'id','desc');

        $output->writeln('任务一键托管，执行完成');
        
    }

}