<?php

namespace app\index\controller;

use app\common\entity\Config;
use app\common\entity\FundLogModel;
use app\common\entity\GoodsModel;
use app\common\entity\MyWalletLog;
use app\common\entity\PrizeLogModel;
use app\common\entity\PrizePublicTotalModel;
use app\common\entity\PrizeSeePointModel;
use app\common\entity\RechargeModel;
use app\common\entity\TaskOrderModel;
use app\common\entity\UserAddressModel;
use app\common\entity\UserInviteCode;
use app\common\entity\UserLevelConfigModel;
use app\common\entity\UserPaymentModel;
use app\common\entity\UserUpgradeModel;
use app\common\entity\UserYuncangModel;
use app\common\entity\WithdrawalModel;
use think\Console;
use think\Db;
use think\Exception;
use think\Request;


class User extends Base {
    /**
     * 用户地址|列表
     */
    public function useraddress(Request $request)
    {
        $list = $this->addresssearch($request);

        return json(['code'=>0,'msg'=>'请求成功','info'=>$list]);
    }
    /**
     * 用户地址|添加新收获地址
     */
    public function addAddress(Request $request)
    {
        $validate = $this->validate($request->post(), '\app\index\validate\UserAddress');
        if ($validate !== true) {
            return json(['code' => 1, 'msg' => $validate]);
        }
        $address = new UserAddressModel();
        if($request->post('status')){
            $userAddress = $address->where('uid',$this->userId)->select();
            foreach ($userAddress as $k => $v){
                if($v['status'] == 1){
                    $address->where('uid',$v['uid'])->update(['status'=>0]);
                }
            }
        }
        $add_data= $request->post();
        $add_data['uid'] = $this->userId;
        $result = $address->addRess($address,$add_data);
        if($result) return json(['code'=>0,'msg'=>'添加地址成功']);
        return json(['code'=>1,'msg'=>'失败']);
    }
    /**
     *用户地址|修改|设为默认
     */
    public function updateAddress (Request $request)
    {
        $id = $request->post('id');
        $query = UserAddressModel::where('id', $id)->find();

        if (!$query) {
            return json(['code' => 1, 'msg' => '地址信息不存在']);
        }
        if($request->post('status') == 1){
            $address = new UserAddressModel();
            $address
                ->where('uid',$query['uid'])
                ->where('status',1)
                ->update(['status'=>0]);
        }
        $edit_data = $request->post();
        unset($edit_data['id']);
        $result = UserAddressModel::where('id', $id)->update($edit_data);
        if(!$result) return json(['code'=>1,'msg'=>'操作失败']);
        return json(['code' => 0, 'msg' => '操作成功']);
    }
    /**
     * 用户地址|删除地址
     */
    public function delAddress (Request $request)
    {
        $id = $request->post('id');
        $query = UserAddressModel::where('id', $id)->find();
        if (!$query) {
            return json(['code' => 1, 'msg' => '地址信息不存在']);
        }
        if($query['status'] == 1){
            return json(['code' => 1, 'msg' => '默认地址不能删除']);
        }
        $result = $query->delete();
        if(!$result) return json(['code'=>1,'msg'=>'操作失败']);
        return json(['code' => 0, 'msg' => '操作成功']);
    }
    /**
     * 用户地址|地址信息查询
     */
    protected function addresssearch($request)
    {
        $query = UserAddressModel::alias('ua')->field('ua.*');
        if ($status = $request->get('status')) {
            $query->where('ua.status',$status);
            $map['ua.status'] = $status;
        }
        $page = $request->get('page')?$request->get('page'):1;
        $limit = $request->get('limit')?$request->get('limit'):15;
        $userTable = (new \app\common\entity\User())->getTable();
        $list = $query
            ->leftJoin("$userTable u", 'u.id = ua.uid')
            ->where('uid',$this->userId)
            ->where(isset($map) ? $map : [])
            ->order('ua.create_time', 'desc')
            ->page($page)
            ->limit($limit)
            ->select();
        return $list;
    }
    /**
     * 用户余额充值下单
     */
    public function moneyAdd(Request $request)
    {
        if($request->isGet()){
            $info = db('config_money')->find();
            return json(['code'=>0,'msg'=>'请求成功','info'=>$info]);
        }
        if($request->isPost()){
            $validate = $this->validate($request->post(), '\app\index\validate\UserRecharge');
            if ($validate !== true) {
                return json(['code' => 1, 'msg' => $validate]);
            }
            $data = [
                'uid' => $this->userId,
                'orderNo' => authcode('RN'),
                'types' => $request->post('types'),
                'total' => $request->post('total'),
                'proof' => $request->post('proof'),
                'level' => $request->post('level'),
                'status' => 1,
            ];
            $address = new RechargeModel();
            $result = $address->addData($address,$data);
            if($result) return json(['code'=>0,'msg'=>'用户充值下单成功','info'=>$result]);
            return json(['code'=>1,'msg'=>'用户充值下单失败']);
        }

    }
    /**
     * 用户提现
     */
    public function moneyCut(Request $request)
    {
        $user_money = \app\common\entity\MyWallet::where('uid',$this->userId)
            ->value('number');
        $user_level = DB::table('user')->where('id',$this->userId)->value('level');
        if($user_level<1){
            return json(['code' => 1, 'msg' => '等级不足']);
        }
        $con1001 = DB::table('config')->where('id',1001)->value('value');
        if($request->post('total')<$con1001){
            return json(['code' => 1, 'msg' => '起提金额不能小于'.$con1001]);

        }
        //手续费率
        $rate = Config::getValue('withdrawa_fee');
        $commission_start_time = Config::getValue('commission_start_time');
        $commission_end_time = Config::getValue('commission_end_time');
        $withdraw_base = Config::getValue('withdraw_base');
        if($request->isGet()){
            return json(['code' => 0, 'msg' => '请求成功','info'=>[
                'money' => $user_money,
                'rate' => $rate,
                'start_time' => $commission_start_time,
                'end_time' => $commission_end_time,
                'withdraw_base' => $withdraw_base,
            ]]);
        }
        if($request->isPost()){
            $types = $request->post('types');
            if(!$types)return json(['code' => 1, 'msg' => '参数错误']);

            if(time() < strtotime($commission_start_time) || time() > strtotime($commission_end_time)){
                return json(['code' => 1, 'msg' => '未开始']);
            }

            $cutNum = WithdrawalModel::where('uid',$this->userId)
                ->whereTime('create_time','today')
                ->count();

            $allNum = Config::getValue('commission_withdraw_num');
            if($cutNum > $allNum){
                return json(['code' => 1, 'msg' => '今日提现次数已用完']);
            }
            if($types == 1){//支付宝
                $validate = $this->validate($request->post(), '\app\index\validate\UserWithdrawalZfb');
                if ($validate !== true) {
                    return json(['code' => 1, 'msg' => $validate]);
                }

            }elseif ($types == 2){//银行卡
                $validate = $this->validate($request->post(), '\app\index\validate\UserWithdrawal');
                if ($validate !== true) {
                    return json(['code' => 1, 'msg' => $validate]);
                }
            }

            //用户余额
            if($user_money < $request->post('total')){
                return json(['code' => 1, 'msg' => '余额不足']);
            }
            //交易密码
            $user_Info = \app\common\entity\User::alias('u')
                ->where('u.id',$this->userId)
                ->find();
            $model = new \app\common\service\Users\Service();
            if (!$model->checkSafePassword($request->post('trad_password'), $user_Info)) {
//                return json(['code'=>1,'msg'=>'密码错误']);
            }
            $serviceCharge = $request->post('total') * $rate * 0.01;
            //提现申请
            $add_data = [
                'uid' => $this->userId,
                'money' => $request->post('total'),
                'realmoney' => $request->post('total') - $serviceCharge,
                'types' => $request->post('types'),
                'bank_user_name' => $request->post('bank_user_name',''),
                'bank_name' => $request->post('bank_name',''),
                'bank_card' => $request->post('bank_card',''),
                'alipay_name' => $request->post('alipay_name',''),
                'alipay_account' => $request->post('alipay_account',''),
                'status' => 1,
                'create_time' => time(),
            ];

            //提现流水
            $log_data = [
                'uid'  => $this->userId,
                'num'  => $request->post('total'),
                'remark'  => '用户申请提现',
            ];

            Db::startTrans();
            try {
                $model = new \app\common\entity\MyWallet();
                $model->takeMoney($model,$log_data);
                $address = new WithdrawalModel();
                $address->insert($add_data);
                Db::commit();
                return json(['code'=>0,'msg'=>'用户提现申请成功']);
            }catch (Exception $e){
                Db::rollback();
                return json(['code'=>1,'msg'=> $e]);
            }
        }
    }

    /**
     * 转账
     */
    public function moneyTransfer(Request $request)
    {
        $validate = $this->validate($request->post(), '\app\index\validate\UserTransfer');
        if ($validate !== true) {
            return json(['code' => 1, 'msg' => $validate]);
        }
        $user_money = \app\common\entity\MyWallet::where('uid',$this->userId)
            ->value('number');
        //用户余额
        if($user_money < $request->post('total')){
            return json(['code' => 1, 'msg' => '余额不足']);
        }
        //交易密码
        $user_Info = \app\common\entity\User::alias('u')
            ->where('u.id',$this->userId)
            ->find();
        $model = new \app\common\service\Users\Service();
        if (!$model->checkSafePassword($request->post('trad_password'), $user_Info)) {
            return json(['code'=>1,'msg'=>'密码错误']);
        }
        //转账流水
        $log_data = [
            'uid'  => $this->userId,
            'toUid'  => \app\common\entity\User::where('mobile',$request->post('mobile'))
                    ->value('id'),
            'num'  => $request->post('total'),
            'my_remark'  => '用户转账扣款',
            'to_remark'  => '用户转账收款',
        ];
        try {
            $model = new \app\common\entity\MyWallet();
            $model->transfer($model,$log_data);
            return json(['code'=>0,'msg'=>'转账成功']);
        }catch (Exception $e){
            return json(['code'=>1,'msg'=>'转账失败']);
        }
    }
    /**
     * 兑换
     */
    public function exchange(Request $request)
    {
        $assure_transfer_start_time = $this->getConfigValue('assure_transfer_start_time');
        $assure_transfer_end_time = $this->getConfigValue('assure_transfer_end_time');
        $data = [
            'start_time' => $assure_transfer_start_time,
            'end_time' => $assure_transfer_end_time
        ];
        if($request->isGet()) {
            return json(['code' => 0, 'msg' => '转账成功', 'info' => $data]);
        }
        if($request->isPost()) {
            $validate = $this->validate($request->post(), '\app\index\validate\Exchange');
            if ($validate !== true) {
                return json(['code' => 1, 'msg' => $validate]);
            }
            $types = $request->post('types');
            $total = $request->post('total');
            $myWallet = \app\common\entity\MyWallet::where('uid', $this->userId)
                ->find();

            if ($types == 1) {//保证金
                if ($total > $myWallet['bond']) {
                    return json(['code' => 1, 'msg' => '保证金不足']);
                }

                if (time() < strtotime($assure_transfer_start_time) || time() > strtotime($assure_transfer_end_time)) {
                    return json(['code' => 1, 'msg' => '未开始']);
                }
                $hasTask = TaskOrderModel::where('uid', $this->userId)
                    ->whereTime('receivetime', 'today')
                    ->find();
                if ($hasTask) {
                    return json(['code' => 1, 'msg' => '明日才可兑换']);
                }

            } elseif ($types == 2) {//代理账户
                if ($total > $myWallet['agent']) {
                    return json(['code' => 1, 'msg' => '代理账户金额不足']);
                }
            }
            //转账流水
            $log_data = [
                'uid' => $this->userId,
                'types' => $types,
                'num' => $request->post('total'),
                'my_remark' => '用户兑换扣款',
                'to_remark' => '用户兑换收款',
            ];
            try {
                $model = new \app\common\entity\MyWallet();
                $model->transfer($model, $log_data);
                if ($types == 1) {//保证金
                    \app\common\entity\User::where('id', $this->userId)
                        ->setField('star_level', 0);
                }
                return json(['code' => 0, 'msg' => '兑换成功']);
            } catch (Exception $e) {
                return json(['code' => 1, 'msg' => $e]);
            }
        }
    }
    /**
     * 资金流水
     */
    public function walletLog(Request $request)
    {
        $query = MyWalletLog::alias('mwl')->field('mwl.*');
        $limit = $request->post('limit')?$request->post('limit'):15;
        $page = $request->post('page')?$request->post('page'):1;
        if($types = $request->post('types')){
            $query->where('mwl.types', $types);
            $map['types'] = $types;
        }
        $list = $query
            ->where('mwl.uid',$this->userId)
            ->order('mwl.create_time','desc')
            ->page($page)
            ->paginate($limit, false, [
                'query' => isset($map) ? $map : []
            ]);
        return json(['code'=>0,'msg'=>'请求成功','info'=>$list]);
    }
    /**
     * 充值记录
     */
    public function rechargeLog(Request $request)
    {
        $query = RechargeModel::alias('r')->field('r.*');
        $limit = $request->post('limit')?$request->post('limit'):15;
        $page = $request->post('page')?$request->post('page'):1;
        if($types = $request->get('types')){
            $query->where('r.types', $types);
            $map['types'] = $types;
        }
        $list = $query
            ->where('r.uid',$this->userId)
            ->order('r.create_time','desc')
            ->page($page)
            ->paginate($limit, false, [
                'query' => isset($map) ? $map : []
            ]);
        return json(['code'=>0,'msg'=>'请求成功','info'=>$list]);
    }
    /**
     * 提现记录
     */
    public function withdrawLog(Request $request)
    {
        $query = WithdrawalModel::alias('w')->field('w.*');
        $limit = $request->post('limit')?$request->post('limit'):15;
        $page = $request->post('page')?$request->post('page'):1;
        if($types = $request->get('types')){
            $query->where('w.types', $types);
            $map['types'] = $types;
        }
        $list = $query
            ->where('w.uid',$this->userId)
            ->order('w.create_time','desc')
            ->page($page)
            ->paginate($limit, false, [
                'query' => isset($map) ? $map : []
            ]);
        foreach ($list as $v){
            $info = json_decode($v['proof']);
            if($v['types'] == 3){
                $v['bank_card'] = $info->bank_card;
                $v['bank_name'] = $info->bank_name;
                $v['bank_user'] = $info->bank_user;
            }
            unset($v['proof']);
        }
        return json(['code'=>0,'msg'=>'请求成功','info'=>$list]);
    }
    /**
     * 绑定收款信息
     */
    public function setPayment(Request $request)
    {
        if($request->isGet()){
            $types = $request->get('types');
            if(!$types) return json(['code' => 1, 'msg' => '参数错误']);
            if($types == 1){
                $field = 'alipay_name,alipay_account,pay_image_id';
            }elseif ($types ==2){
                $field = 'bank_user_name,bank_name,bank_card';
            }
            $info = UserPaymentModel::field($field)
                ->where('uid',$this->userId)
                ->find();
            return json(['code'=>0,'msg'=>'请求成功','info'=>$info]);

        }
        if($request->isPost()) {
            $types = $request->post('types');
            if (!$types) return json(['code' => 1, 'msg' => '参数错误']);
            if ($types == 1) {//支付宝
                $validate = $this->validate($request->post(), '\app\index\validate\SetPaymentZfb');
                if ($validate !== true) {
                    return json(['code' => 1, 'msg' => $validate]);
                }

            } elseif ($types == 2) {//银行卡
                $validate = $this->validate($request->post(), '\app\index\validate\SetPayment');
                if ($validate !== true) {
                    return json(['code' => 1, 'msg' => $validate]);
                }
            }
            $add_data = $request->post();
            unset($add_data['types']);
            $add_data['uid'] = $this->userId;
            $add_data['create_time'] = time();
            $add_data['update_time'] = time();
            $info = UserPaymentModel::where('uid', $this->userId)
                ->find();
            if ($info) {
                $model = $info;
            } else {
                $model = new UserPaymentModel();
            }
            $res = $model->save($add_data);
            if ($res) {
                return json(['code' => 0, 'msg' => '操作成功']);
            }
            return json(['code' => 1, 'msg' => '操作失败']);
        }
    }
    /**
     * @desc 根据两点间的经纬度计算距离
     * @param float $lat 纬度值
     * @param float $lng 经度值
     */
    function getdistance($lng1,$lat1,$lng2,$lat2)
    {
        //将角度转为狐度
        $radLat1=deg2rad($lat1);
        $radLat2=deg2rad($lat2);
        $radLng1=deg2rad($lng1);
        $radLng2=deg2rad($lng2);
        $a=$radLat1-$radLat2;//两纬度之差,纬度<90
        $b=$radLng1-$radLng2;//两经度之差纬度<180
        $s=2*asin(sqrt(pow(sin($a/2),2)+cos($radLat1)*cos($radLat2)*pow(sin($b/2),2)))*6378.137;
        return $s;
    }
    private function getConfigValue($key, $value='value')
    {
        return db('config')
            ->where('key',$key)
            ->value($value);
    }

}
