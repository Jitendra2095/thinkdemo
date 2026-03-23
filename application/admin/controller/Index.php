<?php
namespace app\admin\controller;

use app\admin\exception\AdminException;
use app\admin\service\rbac\Users\Service;
use app\common\entity\Dynamic_Log;
use app\common\entity\Linelist;
use app\common\entity\ManageUser;
use app\common\entity\Orders;
use app\common\entity\RechargeModel;
use app\common\entity\StoreLog;
use app\common\entity\TaskOrderModel;
use app\common\entity\User;
use app\common\entity\UserDetail;
use app\common\entity\UserLevelConfigModel;
use app\common\entity\UserProduct;
use app\common\entity\WithdrawalModel;
use service\LogService;
use think\Db;
use think\Session;
use think\Request;

class Index extends Admin
{
    public function one()
    {
        return $this->render('one');
    }
    public function index(Request $request)
    {
        $user['total'] = User::count();
        $user['today'] = User::whereTime('register_time', 'today')->count();
        $user['yesterday'] = User::whereTime('register_time', 'yesterday')->count();
        $user['level'] = User::where('level','>',0)
            ->count();
        $task['all'] = TaskOrderModel::count();
        $task['not_finished'] = TaskOrderModel::where('status',0)
            ->count();
        $task['not_check'] = TaskOrderModel::where('status',1)
            ->count();
        $task['pass'] = TaskOrderModel::where('status',2)
            ->count();
        $task['not_pass'] = TaskOrderModel::where('status',-1)
            ->count();
        $money['recharge'] = RechargeModel::where('status',1)
            ->sum('money');
        $money['withdrawal'] = WithdrawalModel::where('status',1)
            ->sum('money');
        $money['today_recharge'] = RechargeModel::whereTime('createtime', 'today')
            ->where('status',1)
            ->sum('money');
        $money['today_withdrawal'] = WithdrawalModel::whereTime('examinetime', 'today')
            ->where('status',1)
            ->sum('money');
        $money['yesterday_withdrawal'] = WithdrawalModel::whereTime('examinetime', 'yesterday')
            ->where('status',1)
            ->sum('money');

        $total_bonus = Db::name('bounty_list')->whereTime('createtime','today')->sum('bonus');

        $participate_game_num = Db::name('user_participate_game_list')->whereTime('createtime',"today")->count();

        $money['remind'] = RechargeModel::where('status',1)
            ->count();
        $level = json_encode(['Lv1', 'Lv2', 'Lv3', 'Lv4', 'Lv5','Lv6']);
        return $this->render('index',[
            'user' => $user,
            'level' => $level,
            'task' => $task,
            'money' => $money,
            'participate_game_num' => $participate_game_num,
            'total_bonus' => $total_bonus,
        ]);
    }
    public function remind()
    {
        $remind = RechargeModel::where('status',1)
            ->count();
        if($remind > 0){
            return json(['code'=>0]);
        }else{
            return json(['code'=>1]);
        }
    }
    //统计功能 会员等级处理
    protected function getLevel()
    {
        $model = new User();
        $userTable = $model->getTable();
        $sql = <<<SQL
SELECT count(*) as total,`level` FROM {$userTable} GROUP BY `level`
SQL;
        $userLevel = Db::query($sql);
        $data = [];
        foreach ($userLevel as $item) {
            $data[$item['level']] = $item['total'];
        }
        return $data;
    }

    //修改密码
    public function updateInfo(Request $request)
    {
        if ($request->isPost()) {
            $validate = $this->validate($request->post(), '\app\admin\validate\ChangePassword');

            if ($validate !== true) {
                throw new AdminException($validate);
            }

            //判断原密码是否相等
            $model = new \app\admin\service\rbac\Users\Service();
            $user = ManageUser::where('id', $model->getManageId())->find();
            $oldPassword = $model->checkPassword($request->post('old_password'), $user);
            if (!$oldPassword) {
                throw new AdminException('原密码错误');
            }

            $user->password = $model->getPassword($request->post('password'), $user->getPasswordSalt());

            if ($user->save() === false) {
                throw new AdminException('修改失败');
            }
//            LogService::write('后台首页|修改密码','修改个人登录密码');
            return json(['code' => 0, 'message' => '修改成功', 'toUrl' => url('login/index')]);
        }
        return $this->render('change');
    }


    //退出系统
    public function logout()
    {
        $service = new Service();
        $service->logout();

        $this->redirect('admin/Login/index');
    }

    public function clear()
    {
        //清除所有session
        Session::destroy();
    }




}