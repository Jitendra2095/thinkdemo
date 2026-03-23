<?php

namespace app\index\controller;


use app\common\entity\Config;
use app\common\entity\ConfigTeamLevelModel;
use app\common\entity\TaskModel;
use app\common\entity\TaskOrderModel;
use app\common\service\Task\Service;
use think\Request;
use think\Db;

class Task extends Base
{
    /**
     * 任务列表
     */
    public function index(Request $request)
    {   $level = $request->post('level')??0;
        $limit = $request->post('limit') ? $request->post('limit') : 15;
        $page = $request->post('page') ? $request->post('page') : 1;
        $query = TaskModel::alias('t')
            ->field('t.id,t.task_url,t.demand_side,t.task_num,t.task_price,t.level');
        //本人已领取任务ID
        $received_task = TaskOrderModel::where('uid',$this->userId)
            ->whereIn('status',[0,1])
            ->column('task_id');
        if($received_task){
            $query->whereNotIn('t.id',$received_task);
        }
        $list = $query->where('status',1)
            ->where('t.level',$level)
            ->page($page)
            ->paginate($limit);

        return json(['code' => 0, 'msg' => '获取成功', 'info' => $list]);
    }
    /**
     * 领取任务
     */
    public function createTaskOrder(Request $request)
    {
        $id = $request->post('id');
        if(!$id){
            return json(['code' => 1, 'msg' => '非法操作']);
        }
        $is_use = Db('deposit')->where('uid', $this->userId)
            ->whereTime('create_time','today')
            ->find();
        if($is_use){
            return json(['code' => 1, 'msg' => '托管中，无法领取']);
        }
//        $task_start_time = $this->getConfigValue('task_start_time');
//        $task_end_time = $this->getConfigValue('task_end_time');
//        if(time() < strtotime($task_start_time) || time() > strtotime($task_end_time)){
//            return json(['code' => 1, 'msg' => '未开始']);
//        }
        $res = TaskOrderModel::where('task_id',$id)
            ->where('uid',$this->userId)
            ->whereIn('status',[0,1])
            ->find();
        if($res)  return json(['code' => 1, 'msg' => '已领取']);
        $info = TaskModel::where('id',$id)->find();
        if($info['task_num'] < 1){
            return json(['code' => 1, 'msg' => '任务数量不足']);
        }
        //一共可领取任务数
        $level = \app\common\entity\User::where('id',$this->userId)
            ->value('level');
        // if($level > 0){
            $tasks_num = DB::table('config_user_level')->where('id',$level)
                ->value('count');
        // }else{
        //     $tasks_num = Config::where('key','free_task_num')
        //         ->value('value');
        // }
        //已领取任务数
        $has_task_num = TaskOrderModel::where('uid',$this->userId)
            ->whereTime('receivetime','today')
            ->count();

        if($has_task_num >= $tasks_num){
            return json(['code' => 1, 'msg' => '可接任务数量不足']);
        }
        $user = \app\common\entity\User::where('id',$this->userId)
            ->find();
        // if($user['tiktok_status'] != 3){
            // return json(['code' => 1, 'msg' => '请绑定抖音账号','toUrl'=>1]);
        // }
//        if ($user['kwaifu_status'] != 3){
//            return json(['code' => 1, 'msg' => '请绑定快手账号','toUrl'=>2]);
//        }
        $data = [
            'task_id' => $id,
            'uid' => $this->userId,
            'realprice' => $info['task_price'],
            'status' => 0,
            'receivetime' => time(),
        ];
        $model = new TaskOrderModel();
        $result = $model->addNew($model,$data);
        if ($result) {
            TaskModel::where('id',$id)->setDec('task_num');
            return json(['code' => 0, 'msg' => '领取成功']);
        }
        return json(['code' => 1, 'msg' => '领取失败']);
    }
    /**
     * 已接任务列表
     */
    public function receivedTaskList(Request $request)
    {
        $user = DB::table('user')->where('id',$this->userId)->find();
        $limit = $request->post('limit',15) ;
        $page = $request->post('page',1);
        $status = $request->post('status');
        $query = new TaskOrderModel();
        if(isset($status)){
            $query->where('to.status',$status);
        }
        $list = $query->alias('to')
            ->field('to.id,to.realprice,to.status,t.demand_side,examinetime,t.task_url,t.level')
            ->leftJoin('task t','t.id = to.task_id')
            ->where('to.uid',$this->userId)
            ->order('to.receivetime','desc')
            ->page($page)
            ->paginate($limit);
        $today = DB::table('task_order')->where('uid',$this->userId)->whereTime('receivetime','today')->count();
        $level_day = DB::table('config_user_level')->where('id',$user['level'])->value('count');
        $surplus_num = $level_day- $today;
        foreach ($list as $v){
            if($v['examinetime']){
                $v['examinetime'] = date('Y-m-d H:i:s',$v['examinetime']);
            }
        }

        return json(['code' => 0, 'msg' => '获取成功', 'info' => $list,'surplus_num'=>$surplus_num]);
    }
    /**
     * 提交任务
     */
    public function submitTask(Request $request)
    {
        $id = $request->post('id');
        $image_id = $request->post('image_id');
        if(!$id || !$image_id){
            return json(['code' => 1, 'msg' => '非法操作']);
        }
        $task_info = TaskOrderModel::where('id',$id)->find();
        if($task_info['status'] !== 0 ){
            return json(['code' => 1, 'msg' => '请勿重复操作']);
        }
        if($task_info['uid'] != $this->userId){
            return json(['code' => 1, 'msg' => '非法操作']);
        }
        $res = TaskOrderModel::where('id',$id)
            ->update([
                'status' => 1,
                'image_id' => $image_id,
                'submittime' => time(),
            ]);
        if ($res) {
            return json(['code' => 0, 'msg' => '提交成功']);
        }
        return json(['code' => 1, 'msg' => '提交失败']);
    }
    /**
     * 一键托管
     */
    public function deposit(Request $request)
    {
        if($request->isGet()){
            $user_info = \app\common\entity\User::where('id', $this->userId)
                ->find();
            $is_deposit = Db('deposit')->where('uid', $this->userId)
                ->where('status', 1)
                ->find();
            $min = Config::where('key', 'deposit_space')
                ->value('value');
            $all = ConfigTeamLevelModel::where('id', $user_info['star_level'])
                ->value('task_num');
            $deposit_cost = ConfigTeamLevelModel::where('id', $user_info['star_level'])
                ->value('deposit_cost');

            if ($is_deposit) {
                $now = strtotime($is_deposit['create_time']);
                $add = ceil($all / $min);
                $info = $add * 60 + $now;
                return json([
                    'code' => 0,
                    'msg' => '托管中',
                    'info' => $info,
                    'status' => 1,
                    'deposit_cost' => $deposit_cost,
                ]);
            }else{
                return json(['code' => 0, 'msg' => '未托管', 'deposit_cost' => $deposit_cost,]);
            }
        }
        if($request->isPost()) {
            $user_info = \app\common\entity\User::where('id', $this->userId)
                ->find();
            if ($user_info['level'] < 3) {
                return json(['code' => 1, 'msg' => '无权限使用改功能']);
            }
            $is_use = Db('deposit')->where('uid', $this->userId)
                ->whereTime('create_time','today')
                ->find();
            if($is_use){
                return json(['code' => 1, 'msg' => '今日已托管']);
            }

            // if($user_info['tiktok_status'] != 3){
            //     return json(['code' => 1, 'msg' => '请绑定抖音账号','toUrl'=>1]);
            // }
            $config = DB::table('config_user_level')->where('id', $user_info['level'])
                ->value('deposit_cost');
            $is_deposit = Db('deposit')->where('uid', $this->userId)
                ->where('status', 1)
                ->find();
            $min = Config::where('key', 'deposit_space')
                ->value('value');
            $all = ConfigTeamLevelModel::where('id', $user_info['star_level'])
                ->value('task_num');
            if ($is_deposit) {
                $now = strtotime($is_deposit['create_time']);
                $add = ceil($all / $min);
                $info = $add * 60 + $now;
                return json(['code' => 0, 'msg' => '托管中', 'info' => $info, 'status' => 1]);
            } else {
                $add_data = [
                    'uid' => $this->userId,
                    'status' => 1,
                    'total' => $config,
                    'create_time' => date("Y-m-d H:i", strtotime("+1 minute")),
                ];
                $res = Db('deposit')->insertGetId($add_data);
                if ($res) {

                    $now = strtotime(date("Y-m-d H:i", strtotime("+1 minute")));
                    $add = ceil($all / $min);

                    $info = $add * 60 + $now;
                    return json(['code' => 0, 'msg' => '托管成功', 'info' => $info]);
                }
            }
        }

    }
    private function getConfigValue($key, $value='value')
    {
        return db('config')
            ->where('key',$key)
            ->value($value);
    }
    public function test()
    {
        for ($x=1; $x<=7; $x++) {
            dump($x);
        }

//        $aa = $query->doFirst(1877);

    }


}
