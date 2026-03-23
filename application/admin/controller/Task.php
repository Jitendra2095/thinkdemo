<?php

namespace app\admin\controller;



use app\common\entity\ConfigTeamLevelModel;
use app\common\entity\ConfigUserLevelModel;
use app\common\entity\ManageUser;
use app\common\entity\MyWallet;
use app\common\entity\MyWalletLog;
use app\common\entity\TaskModel;
use app\common\entity\TaskNeedTypeModel;
use app\common\entity\TaskOrderModel;
use app\common\entity\TaskTypeModel;
use think\Exception;
use think\Request;
use think\DB;


class Task extends Admin {
    /**
     * 任务分类
     */
    public function taskType(Request $request)
    {
        $entry = TaskTypeModel::alias('tt')
            ->field('tt.*');
        if ($keyword = $request->get('keyword')) {
            $type = $request->get('type');
            switch ($type) {
                case 'type_name':
                    $entry->where('tt.type_name', 'like','%'.$keyword.'%');
                    break;
            }
            $map['type'] = $type;
            $map['keyword'] = $keyword;
        }
        $startTime = $request->get('startTime');
        $endTime = $request->get('endTime');
        if($startTime){
            $entry->where('tt.create_time', '>=', strtotime($startTime));
            $map['startTime'] = $startTime;
        }
        if($endTime){
            $entry->where('tt.create_time', '<', strtotime($endTime));
            $map['endTime'] = $endTime;
        }
        $list = $entry
            ->order('tt.create_time','desc')
            ->paginate(15,false,[
                'query' => $request->param()?$request->param():[],
            ]);
        return $this->render('taskType',[
            'list' => $list,
        ]);
    }
    /**
     * 添加任务分类
     */
    public function createTaskType(Request $request)
    {
        if($request->isGet()){
            $id = $request->param('id');
            if(!$id){
                return $this->render('editTaskType');
            }else{
                $info = TaskTypeModel::where('id',$id)->find();
                return $this->render('editTaskType',[
                    'info' => $info,
                ]);
            }
        }
        if($request->isPost()){
            $result = $this->validate($request->post(), 'app\admin\validate\CreateTaskType');
            if (true !== $result) {
                return json()->data(['code' => 1, 'message' => $result]);
            }
            $id = $request->param('id');
            if($id){
                $model = TaskTypeModel::where('id',$id)->find();
            }else{
                $model = new TaskTypeModel();
            }
            $res = $model->addNew($model,$request->post());
            if($res){
                return json(['code' => 0, 'toUrl' => url('/admin/Task/taskType')]);
            }
            return json()->data(['code' => 1, 'message' => '操作失败']);
        }
    }
    /**
     * 删除任务分类
     */
    public function deleteTaskType(Request $request)
    {
        $id = $request->param('id');
        if(!$id) return json()->data(['code' => 1, 'message' => '操作失败']);
        $res = TaskTypeModel::where('id',$id)->delete();
        if($res){
            return json(['code' => 0, 'toUrl' => url('/admin/Task/taskType')]);
        }
        return json()->data(['code' => 1, 'message' => '操作失败']);
    }
    /**
     * 需求分类列表
     */
    public function needsType(Request $request)
    {
        $entry = TaskNeedTypeModel::alias('tt')
            ->field('tt.*');
        if ($keyword = $request->get('keyword')) {
            $type = $request->get('type');
            switch ($type) {
                case 'type_name':
                    $entry->where('tt.type_name', 'like','%'.$keyword.'%');
                    break;
            }
            $map['type'] = $type;
            $map['keyword'] = $keyword;
        }
        $startTime = $request->get('startTime');
        $endTime = $request->get('endTime');
        if($startTime){
            $entry->where('tt.create_time', '>=', strtotime($startTime));
            $map['startTime'] = $startTime;
        }
        if($endTime){
            $entry->where('tt.create_time', '<', strtotime($endTime));
            $map['endTime'] = $endTime;
        }
        $list = $entry
            ->order('tt.create_time','desc')
            ->paginate(15,false,[
                'query' => $request->param()?$request->param():[],
            ]);
        return $this->render('needsType',[
            'list' => $list,
        ]);
    }
    /**
     * 添加任务分类
     */
    public function createNeedsType(Request $request)
    {
        if($request->isGet()){
            $id = $request->param('id');
            if(!$id){
                return $this->render('editNeedsType');
            }else{
                $info = TaskNeedTypeModel::where('id',$id)->find();
                return $this->render('editNeedsType',[
                    'info' => $info,
                ]);
            }
        }
        if($request->isPost()){
            $result = $this->validate($request->post(), 'app\admin\validate\CreateNeedsType');
            if (true !== $result) {
                return json()->data(['code' => 1, 'message' => $result]);
            }
            $id = $request->param('id');
            if($id){
                $model = TaskNeedTypeModel::where('id',$id)->find();
            }else{
                $model = new TaskNeedTypeModel();
            }
            $res = $model->addNew($model,$request->post());
            if($res){
                return json(['code' => 0, 'toUrl' => url('/admin/Task/needsType')]);
            }
            return json()->data(['code' => 1, 'message' => '操作失败']);
        }
    }
    /**
     * 删除任务分类
     */
    public function deleteNeedsType(Request $request)
    {
        $id = $request->param('id');
        if(!$id) return json()->data(['code' => 1, 'message' => '操作失败']);
        $res = TaskNeedTypeModel::where('id',$id)->delete();
        if($res){
            return json(['code' => 0, 'toUrl' => url('/admin/Task/needsType')]);
        }
        return json()->data(['code' => 1, 'message' => '操作失败']);
    }
    /**
     * 任务列表
     */
    public function taskList(Request $request)
    {
        $entry = TaskModel::alias('t')
            ->field('t.*');
        if ($keyword = $request->get('keyword')) {
            $type = $request->get('type');
            switch ($type) {
                case 'type_name':
                    $entry->where('t.type_name', 'like','%'.$keyword.'%');
                    break;
            }
            $map['type'] = $type;
            $map['keyword'] = $keyword;
        }
        if ($sort_id = $request->get('sort_id')) {
            $entry->where('t.sort_id', $sort_id);
            $map['sort_id'] = $sort_id;
        }
        if ($need_type_id = $request->get('need_type_id')) {
            $entry->where('t.need_type_id', $need_type_id);
            $map['need_type_id'] = $need_type_id;
        }
        $startTime = $request->get('startTime');
        $endTime = $request->get('endTime');
        if($startTime){
            $entry->where('t.create_time', '>=', strtotime($startTime));
            $map['startTime'] = $startTime;
        }
        if($endTime){
            $entry->where('t.create_time', '<', strtotime($endTime));
            $map['endTime'] = $endTime;
        }
        $list = $entry
            ->order('t.create_time','desc')
            ->paginate(15,false,[
                'query' => $request->param()?$request->param():[],
            ]);
        foreach ($list as $key => $value) {
            $value['level'] = DB::table('config_user_level')->where('id',$value['level'])->value('level_name');
        }
        return $this->render('taskList',[
            'list' => $list,
        ]);
    }
    /**
     * 添加任务
     */
    public function createTask(Request $request)
    {
        if($request->isGet()){
            $id = $request->param('id');
            $allSort = TaskModel::getAllSort();

            $allNeedType = TaskModel::getAllNeedType();
            $level = DB::table('config_user_level')->field('id,level_name')->select();
            if(!$id){
                return $this->render('editTask',[
                    'allSort' => $allSort,
                    'allNeedType' => $allNeedType,
                    'level' => $level,

                ]);
            }else{
                $info = TaskModel::where('id',$id)->find();
                return $this->render('editTask',[
                    'info' => $info,
                    'allSort' => $allSort,
                    'allNeedType' => $allNeedType,
                    'level' => $level,
                ]);
            }
        }
        if($request->isPost()){
            $result = $this->validate($request->post(), 'app\admin\validate\CreateTask');
            if (true !== $result) {
                return json()->data(['code' => 1, 'message' => $result]);
            }
            $id = $request->param('id');
            if($id){
                $model = TaskModel::where('id',$id)->find();
            }else{
                $model = new TaskModel();
            }
            $res = $model->addNew($model,$request->post());
            if($res){
                return json(['code' => 0, 'toUrl' => url('/admin/Task/taskList')]);
            }
            return json()->data(['code' => 1, 'message' => '操作失败']);
        }
    }
    /**
     * 删除任务分类
     */
    public function deleteTask(Request $request)
    {
        $id = $request->param('id');
        if(!$id) return json()->data(['code' => 1, 'message' => '操作失败']);
        $res = TaskModel::where('id',$id)->delete();
        if($res){
            return json(['code' => 0, 'toUrl' => url('/admin/Task/taskList')]);
        }
        return json()->data(['code' => 1, 'message' => '操作失败']);
    }
    /**
     * 任务审核列表
     */
    public function taskExamine(Request $request)
    {
        $uid = session('mysite_admin')['id'];
        $left_uid = ManageUser::where('id',$uid)->value('left_uid');
        $next_id = $this->getNext($left_uid);
        $entry = TaskOrderModel::alias('to')
            ->leftJoin('user u','u.id = to.uid')
            ->leftJoin('task t','t.id = to.task_id')
            ->field('to.*,u.mobile');
        if ($keyword = $request->get('keyword')) {
            $type = $request->get('type');
            switch ($type) {
                case 'type_name':
                    $entry->where('to.type_name', 'like','%'.$keyword.'%');
                    break;
            }
            $map['type'] = $type;
            $map['keyword'] = $keyword;
        }
        $status = $request->get('status','');
        if(isset($status) && $status !== ''){
            $entry->where('to.status', $status);
            $map['status'] = $status;
        }
        $startTime = $request->get('startTime');
        $endTime = $request->get('endTime');
        if($startTime){
            $entry->where('to.receivetime', '>=', strtotime($startTime));
            $map['startTime'] = $startTime;
        }
        if($endTime){
            $entry->where('to.receivetime', '<', strtotime($endTime));
            $map['endTime'] = $endTime;
        }
        if($left_uid){
            $entry->whereIn('u.id',$next_id);
        }
        $list = $entry
            ->order('to.receivetime','desc')
            ->paginate(15,false,[
                'query' => $request->param()?$request->param():[],
            ]);
        $allStatus = TaskOrderModel::getAllStatus();
        return $this->render('taskExamine',[
            'list' => $list,
            'allStatus' => $allStatus,

        ]);
    }
    /**
     * 添加任务审核
     */
    public function editTaskExamine(Request $request)
    {
        $data = [
            'id' => $request->param('id'),
            'status' => $request->param('status'),
        ];

        if($data['status'] == 2){
            $info = TaskOrderModel::alias('to')
                ->field('mw.number,to.uid,to.realprice')
                ->leftJoin('user u','u.id = to.uid')
                ->leftJoin('my_wallet mw','mw.uid = to.uid')
                ->where('to.id',$data['id'])
                ->find();
            //任务结算自己佣金
            // $gold = $info['gold'] - round($info['realprice']);
      
            //结算佣金
            $task_money_data = [
                'number' => $info['realprice'],
                // 'gold' => round($info['realprice']),
                'uid' => $info['uid'],
            ];
            $this->pid_money($info['uid'],$info['realprice']);
            $query = new MyWallet();
            $res = $query->taskMoney($query,$task_money_data);
            if (!$res) {
                throw new Exception();
            }
           
        }
        $edit_data = [
            'status' => $data['status'],
            'examinetime' => time(),
        ];
        $res = TaskOrderModel::where('id',$data['id'])
            ->update($edit_data);

        if($res){
            $uid = TaskOrderModel::where('id',$data['id'])
                ->value('uid');
            $user = \app\common\entity\User::where('id',$uid)->find();

            if($user['level'] > 0) {
                $config = DB::table('config_user_level')->where('id', $user['level'])
                    ->find();
                //已做任务
                $has_task = TaskOrderModel::where('uid', $uid)
                    ->where('status', 2)
                    ->whereTime('examinetime', 'today')
                    ->count();
                if($has_task == $config['count']){
                    Db('reward_user')->insert([
                        'uid' => $uid,
                        'create_time' => time(),
                    ]);
                }
            }
            return json(['code' => 0, 'toUrl' => url('/admin/Task/taskExamine')]);
        }
        return json()->data(['code' => 1, 'message' => '操作失败']);
    }


    public function pid_money($uid,$money){

        $user = DB::table('user')->where('id',$uid)->find();
        // 第一层
        if($user['pid']){
            $pid = DB::table('user')->where('id',$user['pid'])->find();
            $one_money = DB::table('config_user_level')->where('id',$pid['level'])->value('one_money');//第一层
            $pid_money = DB::table('my_wallet')->where('uid',$pid['id'])->find();
            $money = $money*($one_money/100);
            $data = [
                'uid'=>$pid['id'],
                'number'=>$money,
                'old'=>$pid_money['number'],
                'new'=>$pid_money['number']+$money,
                'remark'=>'一级返利',
                'types'=>6,
                'status'=>1,
                'money_type'=>1,
                'from'=>$uid,
                'create_time'=>time(),
            ];

            DB::table('my_wallet_log')->insert($data);
            DB::table('my_wallet')->where('uid',$pid['id'])->setInc('number',$money);


            if($pid['pid']){
                $ppid = DB::table('user')->where('id',$pid['pid'])->find();
                $two_money = DB::table('config_user_level')->where('id',$ppid['level'])->value('two_money');//第一层
                $ppid_money = DB::table('my_wallet')->where('uid',$ppid['id'])->find();
                $money = $money*($two_money/100);
                $data = [
                    'uid'=>$ppid['id'],
                    'number'=>$money,
                    'old'=>$ppid_money['number'],
                    'new'=>$ppid_money['number']+$money,
                    'remark'=>'二级返利',
                    'types'=>6,
                    'status'=>1,
                    'money_type'=>1,
                    'from'=>$uid,
                    'create_time'=>time(),
                ];

                DB::table('my_wallet_log')->insert($data);
                DB::table('my_wallet')->where('uid',$ppid['id'])->setInc('number',$money);

                if($ppid['pid']){
                    $pppid = DB::table('user')->where('id',$ppid['pid'])->find();
                    $three_money = DB::table('config_user_level')->where('id',$pppid['level'])->value('three_money');//第一层
                    $pppid_money = DB::table('my_wallet')->where('uid',$pppid['id'])->find();
                    $money = $money*($three_money/100);
                    $data = [
                        'uid'=>$pppid['id'],
                        'number'=>$money,
                        'old'=>$pppid_money['number'],
                        'new'=>$pppid_money['number']+$money,
                        'remark'=>'三级返利',
                        'types'=>6,
                        'status'=>1,
                        'money_type'=>1,
                        'from'=>$uid,
                        'create_time'=>time(),
                    ];

                    DB::table('my_wallet_log')->insert($data);
                    DB::table('my_wallet')->where('uid',$pppid['id'])->setInc('number',$money);
                }
            }

        }

    }


}
