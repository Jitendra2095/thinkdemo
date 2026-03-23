<?php

namespace app\common\service\Task;


use app\common\entity\Config;
use app\common\entity\ConfigTeamLevelModel;
use app\common\entity\ConfigUserLevelModel;
use app\common\entity\ManageUser;
use app\common\entity\MyWallet;
use app\common\entity\MyWalletLog;
use app\common\entity\TaskModel;
use app\common\entity\TaskOrderModel;
use app\common\entity\User;
use think\Db;
use think\Exception;

class Service
{
    /**
     * 完成任务
     */
    public function doTask($uid)
    {
        if(!$uid) return false;
        $this->doFirst($uid);
    }
    public function doFirst($uid)
    {
        //一共可领取任务数
        $level = \app\common\entity\User::where('id',$uid)
            ->value('star_level');
        if($level > 0){
            $tasks_num = ConfigTeamLevelModel::where('id',$level)
                ->value('task_num');
        }else{
            $tasks_num = 0;
        }
        //已领取任务数
        $has_task_num = TaskOrderModel::where('uid',$uid)
            ->whereTime('receivetime','today')
            ->count();
//        dump($uid);
//        dump($has_task_num);
//        dump($tasks_num);
        if($has_task_num >= $tasks_num){
            $deposit =  Db('deposit')
                ->where('uid',$uid)
                ->where('status',1)
                ->find();
            //修改托管状态
            Db('deposit')
                ->where('uid',$uid)
                ->where('status',1)
                ->update([
                'status' => 2
            ]);
            //结算任务佣金
            $autoTaskTotal = TaskOrderModel::where('uid',$uid)
                ->whereTime('receivetime','today')
                ->where('types',2)
                ->where('status',2)
                ->sum('realprice');
            $this->sendTaskTotal($uid,$autoTaskTotal);
            //扣除托管费用
            $deposit_cost = ConfigTeamLevelModel::alias('c')
                ->leftJoin('user u','u.star_level = c.id')
                ->where('u.id',$uid)
                ->value('c.deposit_cost');
            $this->deposit_cost($uid,$deposit_cost);
            //记录分佣表
            Db('reward_user')->insert([
                'uid' => $uid,
                'create_time' => time(),
            ]);
            return json(['code' => 1, 'msg' => '可接任务数量不足']);
        }
        $surplus = $tasks_num - $has_task_num;
        $number = (int)Config::where('key','deposit_space')
            ->value('value');
        if($number > $surplus){
            $realNum = $surplus;
        }else{
            $realNum = $number;
        }

        for ($x=1; $x<=$realNum; $x++) {
            $data[$x] = $this->doOther($uid);
        }
        $model = new TaskOrderModel();
        $model->insertAll($data);

    }

    /**
     * 建立任务数据
     */
    public function doOther($uid)
    {
        $task_info = TaskModel::where('status',1)
            ->where('task_num','>',0)
            ->orderRaw('rand()')
            ->find();
        $data = [
            'task_id' => $task_info['id'],
            'uid' => $uid,
            'realprice' => $task_info['task_price'],
            'status' => 2,
            'types' => 2,
            'receivetime' => time(),
            'submittime' => time(),
            'examinetime' => time(),
        ];
        return $data;
    }

    /**
     * 结算任务佣金
     */
    public function sendTaskTotal($uid,$autoTaskTotal)
    {
        $model = new MyWallet();
        $data = [
            'uid' => $uid,
            'number' => $autoTaskTotal,
        ];
        $model->taskMoney($model,$data);
    }
    public function addLog($task_info,$uid)
    {
        $user = MyWallet::where('uid',$uid)->find();
        $total_money = $task_info['task_price'] + $user['number'];
        $insert = [];
        $insert['uid'] = $uid;
        $insert['number'] = $task_info['task_price'];
        $insert['old'] = $user['number'];
        $insert['new'] = $total_money;
        $insert['remark'] = '任务佣金';
        $insert['types'] = 5;
        $insert['status'] = 1;
        $insert['money_type'] = 2;
        $insert['create_time'] = time();
        $result = Db('my_wallet_log')->insertGetId($insert);
    }
    /**
     * 扣除托管费用
     */
    public function deposit_cost($uid,$number)
    {
        $user = MyWallet::where('uid',$uid)->find();
        $edit_data['number'] = $user['number'] - $number;
        $edit_data['update_time']  = time();
        $res = MyWallet::where('uid',$uid)->update($edit_data);
        $insert = [];
        $insert['uid'] = $uid;
        $insert['number'] = $number;
        $insert['old'] = $user['number'];
        $insert['new'] = $edit_data['number'];
        $insert['remark'] = '扣除托管费用';
        $insert['types'] = 9;
        $insert['status'] = 2;
        $insert['money_type'] = 2;
        $insert['create_time'] = time();
        $result = Db('my_wallet_log')->insertGetId($insert);
    }
    /**
     * 分销
     */
    public function retailStore($uid,$id)
    {
        $user = User::where('id',$uid)->find();
//        dump($user);
        if($user){
            if($user['star_level'] > 0){
                $config = ConfigTeamLevelModel::where('id',$user['star_level'])
                    ->find();

                //已做任务
                $has_task = TaskOrderModel::where('uid',$uid)
                    ->where('status',2)
                    ->whereTime('examinetime','today')
                    ->count();

                if($has_task == $config['task_num']){
                    //三级分销

                   $prizeData = $this->findPrize($uid,$has_task);

                   if($prizeData){
                       foreach ($prizeData as $v){
                           if($v['prize'] > 0) {
                               $data = [
                                   'number' => $v['prize'],
                                   'uid' => $v['uid'],
                                   'from' => $uid,
                               ];
                               $this->sendRetailStore($data);
                           }
                       }
                   }
                   //代理佣金
                    $agentData = $this->findAgent($uid,$has_task);
                    if($agentData){
                        foreach ($agentData as $val){
                            if($val['prize'] > 0) {
                                $data = [
                                    'number' => $val['prize'],
                                    'uid' => $val['uid'],
                                    'from' => $uid,
                                ];
                                $this->sendAgentStore($data);
                            }
                        }
                    }
                    Db('reward_user')
                        ->where('id',$id)
                        ->update([
                            'count_time' => time(),
                            'status' => 2
                        ]);
                }else{
                    return '今日任务未全部完成';
                }
            }
        }else{
            return '未购买保证金套餐';
        }
    }
    /**
     * 查询奖励人和奖励金额
     */
    public function findPrize($uid,$money)
    {
        //查询上三级
        $model = new User();
        $upIdArr = [];
        $model->get_superiors($uid,$upIdArr);
//        dump($upIdArr);
//        die;
        if($upIdArr){
            $prize = [];
            foreach ($upIdArr as $k=>$v){
                if($v['star_level'] > 0){
                    if($k == 0){
                        $prize[] = [
                            'prize' => $money * $v['one_level'] * 0.01,
                            'uid' => $v['id'],
                        ];
                    }
                    if($k == 1){
                        $prize[] = [
                            'prize' => $money * $v['two_level'] * 0.01,
                            'uid' => $v['id'],
                        ];
                    }
                    if($k == 2){
                        $prize[] = [
                            'prize' => $money * $v['three_level'] * 0.01,
                            'uid' => $v['id'],
                        ];
                    }
                }
            }
            return $prize;
        }
    }
    /**
     * 查询奖励代理人和奖励金额
     */
    public function findAgent($uid,$money=100)
    {
        //查询上十二层级
        $model = new User();
        $upIdArr = $model->getParents($uid,12);
        $rate = Config::where('key','agent_profit')
            ->value('value');
//        dump($upIdArr);
        $prize = [];
        foreach ($upIdArr as $v){
            $is_agent = ManageUser::where('left_uid',$v)->find();
            if($is_agent){
                $prize[] = [
                    'prize' => $money * $rate * 0.01,
                    'uid' => $v,
                ];
            }
        }
        return $prize;
    }

    /**
     *  发放下级任务佣金
     */
    public function sendRetailStore($data)
    {
        $oldInfo = MyWallet::where('uid',$data['uid'])->find();
        Db::startTrans();
        try {
            $edit_data['number'] = $oldInfo['number'] + $data['number'];
            $old_number = $oldInfo['number'];
            $edit_data['update_time']  = time();
            $res = MyWallet::where('uid',$data['uid'])->update($edit_data);
            if (!$res) {
                throw new Exception();
            }
            $create_data = [
                'uid' => $data['uid'],
                'number' => $data['number'],
                'old' => $old_number,
                'new' => $old_number + $data['number'],
                'remark' => '下级任务佣金',
                'from' => $data['from'],
                'types' => 6,
                'status' => 1,
                'money_type' => 2,
                'create_time' => time(),
            ];
            $res = MyWalletLog::insert($create_data);
            if (!$res) {
                throw new Exception();
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
        }
    }

    public function sendAgentStore($data)
    {
        $oldInfo = MyWallet::where('uid',$data['uid'])->find();
        Db::startTrans();
        try {
            $edit_data['agent'] = $oldInfo['agent'] + $data['number'];
            $old_number = $oldInfo['number'];
            $edit_data['update_time']  = time();
            $res = MyWallet::where('uid',$data['uid'])->update($edit_data);
            if (!$res) {
                throw new Exception();
            }
            $create_data = [
                'uid' => $data['uid'],
                'number' => $data['number'],
                'old' => $old_number,
                'new' => $old_number + $data['number'],
                'remark' => '代理任务佣金',
                'from' => $data['from'],
                'types' => 7,
                'status' => 1,
                'money_type' => 3,
                'create_time' => time(),
            ];
            $res = MyWalletLog::insert($create_data);
            if (!$res) {
                throw new Exception();
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
        }
    }
    /**
     * 升级会员等级
     */
    public function upgrade($all_user)
    {
        $PlatformSettingLogic = new ConfigUserLevelModel();
        $mse = $PlatformSettingLogic;
        foreach ($all_user as $item) {

            $push = 0;
            $team = false;
            $query = new User();
            //团队有效人数
            $teamRealNum = $query->getChildsRealNum($item,3);
            for ($x=1; $x<=7; $x++) {
                $lv_push = $mse
                    ->where('id',$x)
                    ->value('valid_num');
                if($teamRealNum >= $lv_push){
                    $push = $x;
                }
            }
            if($push){
                //团队人数
                $teamNum = $query->getChildsInfoNum($item,3);
                $lv_push = $mse
                    ->where('id',$push)
                    ->value('team_num');
                if($teamNum >= $lv_push){
                    $team = true;
                }
                if(!$team && $push > 1){
                    for ($y=1;$y<=$push-1;$y++){
                        $push = $push - $y;
                        $lv_push = $mse
                            ->where('id',$push)
                            ->value('team_num');
                        if($teamNum >= $lv_push){
                            $team = true;
                            break;
                        }
                    }
                }
            }
            if($team){
                $star_level = Db('user')
                    ->where('id',$item)
                    ->value('star_level');
                if($star_level > 0){
                    Db('user')
                        ->where('id',$item)
                        ->update([
                            'level' => $push
                        ]);
                }

            }else{
//                Db('user')
//                    ->where('id',$item)
//                    ->update([
//                        'level' => 0
//                    ]);
            }
            Db('user')
                ->where('id',$item)
                ->update([
                    'star_upgrade_time' => time()
                ]);
//            dump($item['id'].'完成');
        }
    }
}