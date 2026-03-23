<?php

namespace app\admin\controller;

use app\admin\exception\AdminException;
use app\common\entity\ConfigTeamLevelModel;
use app\common\entity\GoodsModel;
use app\common\entity\ManageUser;
use app\common\entity\MyWallet;
use app\common\entity\MyWalletLog;
use app\common\entity\OperationCenterModel;
use app\common\entity\User as userModel;
use app\common\entity\UserInviteCode;
use app\common\entity\UserLevelConfigModel;
use app\common\entity\UserPaymentModel;
use app\common\entity\UserYuncangModel;
use think\Db;
use think\Request;
use service\LogService;

class User extends Admin
{

    /**
     * @power 会员管理|会员列表
     * @rank 1
     * @rank 1
     */
    public function index(Request $request)
    {
        $uid = session('mysite_admin')['id'];
        $left_uid = ManageUser::where('id',$uid)->value('left_uid');
        $next_id = $this->getNext($left_uid);

        $entity = userModel::alias('u')
            ->field('u.*,mw.commission,mw.bonus,mw.credit1,mw.credit2,uic.invite_code,l.levelname');
        if ($keyword = $request->get('keyword')) {
            $type = $request->get('type');
            switch ($type) {
                case 'mobile':
                    $entity->where('u.mobile', 'like', '%' . $keyword . '%');
                    break;
                case 'nick_name':
                    $entity->where('u.nick_name', 'like', '%' . $keyword . '%');
                    break;
            }
            $map['type'] = $type;
            $map['keyword'] = $keyword;
        }
        $orderStr = 'u.register_time DESC';
        if ($order = $request->get('order')) {
            $sort = $request->get('sort', 'desc');
            $orderStr = 'u.' . $order . ' ' . $sort;
            $map['order'] = $order;
            $map['sort'] = $sort;
        }
        $startTime = $request->get('startTime');
        $endTime = $request->get('endTime');
        if($startTime){
            $entity->where('u.register_time', '>=', strtotime($startTime));
            $map['startTime'] = $startTime;
        }
        if($endTime){
            $entity->where('u.register_time', '<', strtotime($endTime));
            $map['endTime'] = $endTime;
        }
        if($left_uid){
            $entity->whereIn('u.id',$next_id);
        }
        $list = $entity
            ->leftJoin('user_wallet mw', 'mw.uid = u.id')
            ->leftJoin('user_invite_code uic', 'u.id = uic.user_id')
            ->leftJoin('commission_level l', 'u.commission_level = l.id')
            ->order($orderStr)
            ->distinct(true)
            ->paginate(15, false, [
                'query' => isset($map) ? $map : []
            ]);
        if (isset($map['sort'])) {
            $map['sort'] = $map['sort'] == 'desc' ? 'asc' : 'desc';
        }
        foreach ($list as $v) {
            $leader = \app\common\entity\User::where('id', $v['pid'])->value('nick_name');
            $next_count = \app\common\entity\User::where('pid', $v['id'])->count();
            $v['next_count'] = $next_count;
            $v['leader'] = $leader;
        }
        return $this->render('index', [
            'list' => $list,
            'queryStr' => isset($map) ? http_build_query($map) : '',
        ]);
    }

    /**
     * 查看会员详情
     * @method get
     */
    public function userDetail(Request $request)
    {
        $id = $request->param('id');
        $info = userModel::where('id', $id)->find();
        return $this->render('detail', [
            'info' => $info,
        ]);

    }

    /**
     * 激活会员
     * @method get
     */
    public function activation(Request $request)
    {
        $id = $request->param('id');
        $res = userModel::where('id', $id)->update(['status' => 1]);
//        LogService::write('会员管理', '用户激活会员');
        if ($res) {
            return json()->data(['code' => 0, 'toUrl' => url('/admin/user/index')]);
        }
        return json()->data(['code' => 1, 'message' => '操作失败']);
    }

    /**
     * 冻结会员
     * @method get
     */
    public function freeze(Request $request)
    {
        $id = $request->param('id');
        $res = userModel::where('id', $id)->update(['status' => -1, 'forbidden_time' => time()]);
//        LogService::write('会员管理', '用户冻结会员');
        if ($res) {
            return json()->data(['code' => 0, 'toUrl' => url('/admin/user/index')]);
        }
        return json()->data(['code' => 1, 'message' => '操作失败']);
    }

    /**
     * 删除会员
     * @method get
     */
    public function delete(Request $request)
    {
        $id = $request->param('id');
        $res = userModel::where('id', $id)->delete();
//        LogService::write('会员管理', '用户删除会员');
        if ($res) {
            return json()->data(['code' => 0, 'toUrl' => url('/admin/user/index')]);
        }
        return json()->data(['code' => 1, 'message' => '操作失败']);
    }


    /**
     * 修改会员数据
     * @method get
     */
    public function editUser(Request $request)
    {
        $id = $request->param('id');
        $info = userModel::alias('u')
            ->field('u.*,p.bank_user_name,p.bank_name,p.bank_card,p.alipay_name,p.alipay_account')
            ->leftJoin('user_payment p','p.uid = u.id')
            ->where('u.id', $id)
            ->find();
        $commission_level = Db::name('commission_level')->select();
        return $this->render('edit', [
            'info' => $info,
            'commission_level' => $commission_level,
        ]);
    }

    /**
     * @power 会员管理|会员列表@添加会员
     */
    public function create()
    {
        $commission_level = Db::name('commission_level')->select();

        return $this->render('edit',[
            'commission_level' => $commission_level,
        ]);
    }

    /**
     * @power 会员管理|会员列表@会员充值
     */
    public function recharge(Request $request)
    {
        $id = $request->param('id');
        $info = userModel::where('id', $id)->find();
        return $this->render('recharge', [
            'info' => $info,
        ]);
    }
    public function takeOff(Request $request)
    {
        $id = $request->param('id');
        $info = userModel::where('id', $id)->find();
        return $this->render('takeOff', [
            'info' => $info,
        ]);
    }
    public function buckle($id, Request $request)
    {
        $number = $request->post('number');
        if (!preg_match('/^[0-9]+.?[0-9]*$/', $number)) {
            return json()->data(['code' => 1, 'message' => '输入的数量必须为正整数或者小数']);
        }
        $remark1 = $request->post("remark");
        $types = $request->post('types');
        if ($types == '1') {
            $credit = 'credit1';
            $remark = $remark1 ;
        } elseif ($types == '2') {
            $credit = 'credit2';
            $remark = $remark1;
        }

        $user_model = new \app\index\model\User();
        $result = $user_model->setUserWallet($id,$credit,2,1,$number,$remark);
        if($result['code'] == 400){
            return ['code' => 1, 'message' => $result['msg']];
        }
//        if ($types == '1') {
//            $types1 = 'bond';
//            $remark = $remark1 . '保证金账户';
//            $types2 = 1;
//        } elseif ($types == '2') {
//            $types1 = 'number';
//            $remark = $remark1 . '佣金账户';
//            $types2 = 2;
//        }
//        $hasNum = MyWallet::where('uid', $id)->value($types1);
//        $wallet_data = [
//            'uid' => $id,
//            'number' => $number,
//            'old' => $hasNum,
//            'new' => $hasNum - $number,
//            'remark' => $remark,
//            'types' => 9,
//            'status' => 2,
//            'money_type' => $types2,
//        ];
//        $newNum = $hasNum - $number;
//        $my_wallet_log = new MyWalletLog();
//        $inslog = $my_wallet_log->addNew($my_wallet_log, $wallet_data);
//
//        MyWallet::where('uid', $id)->setDec($types1, $number);
//        if($types == 1){//保证金
//            $configList = ConfigTeamLevelModel::order('id')->select();
//            foreach ($configList as $k => $v){
//                if(isset($configList[$k+1]['assure_money'])){
//                    $max = $configList[$k+1]['assure_money'];
//                }else{
//                    $max = $v['assure_money'] + 1;
//                }
//                if($newNum >= $v['assure_money']  && $newNum < $max){
//                    $star_level = $v['id'];
//                }
//            }
//        }
//        if(isset($star_level)){
//            userModel::where('id',$id)->setField('star_level',$star_level);
//        }
//        if (!$inslog) {
//            return ['code' => 1, 'message' => '充值失败'];
//        }
        return ['code' => 0, 'toUrl' => url('user/index')];
    }

    /**
     * @power 会员管理|会员列表@充值
     * @method POST
     */
    public function saveRecharge($id, Request $request)
    {
        $number = $request->post('number');
        if (!preg_match('/^[0-9]+.?[0-9]*$/', $number)) {
            return json()->data(['code' => 1, 'message' => '输入的数量必须为正整数或者小数']);
        }
        $remark1 = $request->post("remark");
        $types = $request->post('types');
        if ($types == '1') {
            $credit = 'credit1';
            $remark = $remark1 ;
        } elseif ($types == '2') {
            $credit = 'credit2';
            $remark = $remark1;
        }
        $user_model = new \app\index\model\User();
        $result = $user_model->setUserWallet($id,$credit,1,2,$number,$remark);
        if($result['code'] == 400){
            return ['code' => 1, 'message' => $result['msg']];
        }
//        $hasNum = MyWallet::where('uid', $id)->value($types1);
//        $wallet_data = [
//            'uid' => $id,
//            'number' => $number,
//            'old' => $hasNum,
//            'new' => $hasNum + $number,
//            'remark' => $remark,
//            'types' => 1,
//            'status' => 1,
//            'money_type' => $types2,
//        ];
//
//        $my_wallet_log = new MyWalletLog();
//        $inslog = $my_wallet_log->addNew($my_wallet_log, $wallet_data);
//        $newNum = $hasNum + $number;
//        MyWallet::where('uid', $id)->setInc($types1, $number);
//        if($types == 1){//保证金
//            $configList = ConfigTeamLevelModel::order('id')->select();
//            foreach ($configList as $k => $v){
//                if(isset($configList[$k+1]['assure_money'])){
//                    $max = $configList[$k+1]['assure_money'];
//                }else{
//                    $max = $v['assure_money'] + 1;
//                }
//                if($newNum >= $v['assure_money']  && $newNum < $max){
//                    $star_level = $v['id'];
//
//                }
//            }
//        }
//       if(isset($star_level)){
//            userModel::where('id',$id)->setField('star_level',$star_level);
//       }
//        if (!$inslog) {
//            return ['code' => 1, 'message' => '充值失败'];
//        }
        return ['code' => 0, 'toUrl' => url('user/index')];
    }

    /**
     * @power 会员管理|会员列表@添加会员
     */
    public function save(Request $request)
    {

        $result = $this->validate($request->post(), 'app\admin\validate\UserForm');
        if (true !== $result) {
            return json()->data(['code' => 1, 'message' => $result]);
        }

        $service = new \app\common\service\Users\Service();
        if ($service->checkUser($request->post('mobile'))) {
            return json()->data(['code' => 1, 'message' => '账号已被注册,请重新填写']);
        }
        $add_data = $request->post();
        if($request->post('higher')){
            if ($pid = $service->checkHigher($request->post('higher'))) {
                $add_data['pid'] = $pid;
            } else {
                if ($request->post('higher') == 0) {

                    $add_data['pid'] = $request->post('higher');
                } else {
                    return json()->data(['code' => 1, 'message' => '推荐人账号不存在,请重新填写']);
                }
            }
        }else{
            $add_data['pid'] = 0;
        }


        Db::startTrans();
        try {
            $userId = $service->addUser($add_data);
            if (!$userId) {
                throw new \Exception('保存失败');
            }
            if(!empty($add_data['pid'])){
                $result = $this->inviteGive($add_data['pid']);
                if (!$result) {
                    throw new \Exception('保存失败');
                }
            }

            $inviteCode = new UserInviteCode();
            if (!$inviteCode->saveCode($userId)) {
                throw new \Exception('保存失败');
            }
            $wallet_data = [
                'uid' => $userId,
                'createtime' => time(),
            ];
            $wallet_model = Db('user_wallet');

            $wallet_model->insertGetId($wallet_data);
            Db::commit();
            return json(['code' => 0, 'toUrl' => url('/admin/user/index')]);
        } catch (\Exception $e) {
            Db::rollback();
            throw new AdminException($e->getMessage());
        }
    }

    public function inviteGive($uid){
        $user = Db::name('user')->where('id',$uid)->field('id,invite_count')->find();
        if(($user['invite_count'] + 1) % 3 == 0){
            $config = Db::name('config')
                ->alias('c')
                ->leftJoin('game_ticket t','t.id = c.value')
                ->where('c.key','Invite_give')
                ->field('t.id as tid,t.price')
                ->find();
            $times = time();
            if(!empty($config)){
                $data = [
                    'uid'=>$uid,
                    'ticket_id'=>$config['tid'],
                    'price'=>$config['price'],
                    'status'=>0,
                    'is_give'=>1,
                    'createtime'=>$times,
                    'orvertime'=>$times + (86400 * 30),
                ];
                $log = [
                    'uid'=>$uid,
                    'ticket_id'=>$config['tid'],
                    'num'=>1,
                    'op_type'=>2,
                    'createtime'=>time(),
                ];
                $result = Db::name('user_give_ticket_log')->insert($log);
                if(!$result){
                    return false;
                }
                $result = Db::name('user_game_ticket')->insert($data);
                if(!$result){
                    return false;
                }

            }
        }
        return true;
    }


    /**
     * @power 会员管理|会员列表@编辑会员
     */
    public function update(Request $request, $id)
    {
        $entity = $this->checkInfo($id);
        $result = $this->validate($request->post(), 'app\admin\validate\UserEditForm');
        $check_user = \app\common\entity\User::checkMobile($request->post('mobile'));
        if ($check_user) {
            if ($check_user->id != $id) {
                return json()->data(['code' => 1, 'message' => '此账号已被注册，请重新填写']);
            }
        }
        if (true !== $result) {
            return json()->data(['code' => 1, 'message' => $result]);
        }

        $service = new \app\common\service\Users\Service();
        $result = $service->updateUser($entity, $request->post());
        $payment = UserPaymentModel::where('uid',$id)->find();
        if($payment){
            UserPaymentModel::where('uid',$id)
                ->update([
                    'alipay_name' => $request->post('alipay_name'),
                    'alipay_account' => $request->post('alipay_account'),
//                    'bank_card' => $request->post('bank_card'),
                    'update_time' => time(),
                ]);
        }else{
            UserPaymentModel::insert([
                    'uid' => $id,
                    'alipay_name' => $request->post('alipay_name'),
                    'alipay_account' => $request->post('alipay_account'),
//                    'bank_user_name' => $request->post('bank_user_name'),
//                    'bank_name' => $request->post('bank_name'),
//                    'bank_card' => $request->post('bank_card'),
                    'create_time' => time(),
                ]);
        }
        if (!is_int($result)) {
            return json(['code' => 1, 'message' => url('保存失败')]);
        }
        return json(['code' => 0, 'toUrl' => url('/admin/user/index')]);
    }

    private function checkInfo($id)
    {
        $entity = userModel::where('id', $id)->find();
        if (!$entity) {
            throw new AdminException('对象不存在');
        }

        return $entity;
    }

    /**
     * 等级配置
     */
    public function levelConfig(Request $request)
    {
        $list = UserLevelConfigModel::alias('ulc')
            ->field('ulc.*')
            ->order('id')
            ->paginate(15, false, [
                'query' => $request->param() ? $request->param() : [],
            ]);
        $goods = GoodsModel::select();
        return $this->render('levelConfig', [
            'list' => $list,
            'goods' => $goods,
        ]);
    }

    /**
     * 修改等级配置
     */
    public function editLevelConfig(Request $request)
    {
        $result = $this->validate($request->post(), 'app\admin\validate\AddLevelConfig');
        if (true !== $result) {
            return json()->data(['code' => 1, 'message' => $result]);
        }
        $oldInfo = UserLevelConfigModel::checkExist($request->post('id'));
        if ($oldInfo && $request->post('id') != $request->post('oldId')) {
            return json()->data(['code' => 1, 'message' => '等级大小已存在，请重新填写']);
        }
        $updateData = $request->post();
        unset($updateData['oldId']);
        $res = UserLevelConfigModel::where('id', $request->post('oldId'))
            ->update($updateData);
        if (is_int($res)) {
            return json()->data(['code' => 0, 'message' => '修改成功']);
        }
        return json()->data(['code' => 1, 'message' => '操作失败']);
    }

    /**
     * 添加等级配置
     */
    public function addLevelConfig(Request $request)
    {
        $result = $this->validate($request->post(), 'app\admin\validate\EditLevelConfig');
        if (true !== $result) {
            return json()->data(['code' => 1, 'message' => $result]);
        }
        $query = new UserLevelConfigModel();
        $res = $query->addNew($query, $request->post());
        if ($res) {
            return json()->data(['code' => 0, 'message' => '添加成功']);
        }
        return json()->data(['code' => 1, 'message' => '操作失败']);
    }
    /**
     * 删除等级配置
     */
    public function delLevelConfig(Request $request)
    {
        $id = $request->param('id');
        if (!UserLevelConfigModel::checkExist($id)) {
            return json()->data(['code' => 1, 'message' => '非法操作']);
        }
        $allow = UserModel::where('level',$id)->find();
        if($allow){
            return json()->data(['code' => 1, 'message' => '改权益已存在会员无法删除']);
        }
        $res = UserLevelConfigModel::where('id', $id)->delete();
        if ($res) {
            return json()->data(['code' => 0, 'message' => '删除成功']);
        }
        return json()->data(['code' => 1, 'message' => '操作失败']);
    }
    /**
     * 云仓管理
     */
    public function yuncang(Request $request)
    {
        if ($request->isGet()) {
            $id = $request->param('id');
            $entity = UserYuncangModel::alias('uy')
                ->leftJoin('user u', 'u.id = uy.uid')
                ->leftJoin('goods g', 'g.id = uy.good_id')
                ->field('uy.*,u.nick_name,u.mobile,g.good_name,g.good_pic');
            if ($keyword = $request->get('keyword')) {
                $type = $request->get('type');
                switch ($type) {
                    case 'good_name':
                        $entity->where('g.good_name', 'like', '%' . $keyword . '%');
                        break;
                }
                $map['type'] = $type;
                $map['keyword'] = $keyword;
            }
            if ($id) {
                $entity = $entity->where('u.id', $id);
            }
            $orderStr = 'uy.create_time DESC';
            $list = $entity
                ->order($orderStr)
                ->paginate(15, false, [
                    'query' => isset($map) ? $map : []
                ]);
            if (isset($map['sort'])) {
                $map['sort'] = $map['sort'] == 'desc' ? 'asc' : 'desc';
            }
            return $this->render('yuncang', [
                'list' => $list,
                'queryStr' => isset($map) ? http_build_query($map) : '',
            ]);
        }

        if ($request->isPost()) {
            $uid = $request->param('uid');
            $good_id = $request->param('good_id');
            $good_num = (int)$request->post('good_num');

            if (!is_int($good_num) || $good_num < 0) {
                return json(['code' => 1, 'message' => '请输入正整数']);
            }
            $res = UserYuncangModel::where('uid', $uid)
                ->where('good_id', $good_id)
                ->setField('good_num', $good_num);
            if (is_int($res)) {
                return json(['code' => 0, 'message' => '设置成功']);
            }
            return json(['code' => 1, 'message' => '设置失败']);
        }
    }

    /**
     * 运营中心配置
     */
    public function centerConfig(Request $request)
    {
        $entity = OperationCenterModel::alias('oc')
            ->field('oc.*,u.mobile,u.nick_name');
        if ($keyword = $request->get('keyword')) {
            $type = $request->get('type');
            switch ($type) {
                case 'center_name':
                    $entity->where('oc.center_name', 'like', '%' . $keyword . '%');
                    break;
                case 'mobile':
                    $entity->where('u.mobile', 'like', '%' . $keyword . '%');
                    break;
                case 'nick_name':
                    $entity->where('u.nick_name', 'like', '%' . $keyword . '%');
                    break;
            }
            $map['type'] = $type;
            $map['keyword'] = $keyword;
        }
        $orderStr = 'oc.create_time DESC';
        $list = $entity
            ->leftJoin('user u', 'oc.uid = u.id')
            ->order($orderStr)
            ->distinct(true)
            ->paginate(15, false, [
                'query' => isset($map) ? $map : []
            ]);
        if (isset($map['sort'])) {
            $map['sort'] = $map['sort'] == 'desc' ? 'asc' : 'desc';
        }
        return $this->render('centerConfig', [
            'list' => $list,
            'queryStr' => isset($map) ? http_build_query($map) : '',
        ]);
    }
    /**
     * 修改运营中心
     */
    public function editCenter(Request $request)
    {
        if($request->isGet()){
            $id = $request->param('id');
            $centerInfo = OperationCenterModel::where('id',$id)->find();
            $uidArr = OperationCenterModel::column('uid');
            $user = userModel::field('id,nick_name,mobile')
//                ->whereNotIn('id',$uidArr)
                ->select();

            return $this->render('editCenter', [
                'info' => $centerInfo,
                'user' => $user,
            ]);
        }
        if($request->isPost()){
            $id = $request->param('id');
            $editData = $request->post();
            $editData['detail'] = htmlspecialchars_decode($request->post('detail'));
            $editData['create_time'] = time();
            $res = OperationCenterModel::where('id',$id)
                ->update($editData);
            if($res){
                return json(['code'=>0,'toUrl' => url('/admin/User/centerConfig')]);
            }
            return json(['code' => 1, 'message'=>'修改失败','toUrl' => url('/admin/user/index')]);
        }
    }
    /**
     * 删除运营中心
     */
    public function deleteCenter(Request $request)
    {
        $id = $request->param('id');
        if(!OperationCenterModel::checkExist($id)){
            return json(['code' => 1, 'message'=>'非法操作']);
        }
        $allow = UserModel::where('center_id',$id)->find();
        if($allow){
            return json(['code' => 1, 'message'=>'以关联用户，无法删除']);
        }
        $res = OperationCenterModel::where('id',$id)->delete();
        if($res){
            return json(['code'=>0,'toUrl' => url('/admin/User/centerConfig')]);
        }
        return json(['code' => 1, 'message'=>'删除失败','toUrl' => url('/admin/User/centerConfig')]);
    }
    /**
     * 新增运营中心
     */
    public function addCenter(Request $request)
    {
        if($request->isGet()){
            $uidArr = OperationCenterModel::column('uid');
            $user = userModel::field('id,nick_name,mobile')
                ->whereNotIn('id',$uidArr)
                ->select();
            return $this->render('editCenter', [
                'user' => $user,
            ]);
        }
        if($request->isPost()){
            $id = $request->param('id');
            $editData = $request->post();
            $editData['detail'] = htmlspecialchars_decode($request->post('detail'));
            $editData['create_time'] = time();
            $res = OperationCenterModel::where('id',$id)
                ->insert($editData);
            if($res){
                return json(['code'=>0,'toUrl' => url('/admin/User/centerConfig')]);
            }
            return json(['code' => 1, 'message'=>'添加失败','toUrl' => url('/admin/user/index')]);
        }
    }

}
