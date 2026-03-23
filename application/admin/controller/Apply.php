<?php

namespace app\admin\controller;


use app\common\entity\ConfigTeamLevelModel;
use app\common\entity\LevelUpLogModel;
use app\common\entity\ManageUser;
use app\common\entity\MyWallet;
use app\common\entity\RechargeModel;
use app\common\entity\UserOtherModel;
use app\common\entity\WithdrawalModel;
use app\common\service\Task\Service;
use app\common\entity\Export;
use think\Db;
use think\Request;

class Apply extends Admin {
    /**
     * 充值申请列表
     */
    public function recharge(Request $request)
    {
        $uid = session('mysite_admin')['id'];
        $left_uid = ManageUser::where('id',$uid)->value('left_uid');
        $next_id = $this->getNext($left_uid);
        $entity = RechargeModel::alias('r')
            ->leftJoin('user u','r.uid = u.id')
            ->field('r.*,u.nick_name,u.mobile');
        if ( $keyword = $request->get('keyword') ) {
            $type = $request->get('type');
            switch ($type) {
                case 'nick_name':
                    $entity->where('u.nick_name','like','%'. $keyword.'%');
                    break;
                case 'mobile':
                    $entity->where('u.mobile','like','%'. $keyword.'%');
                    break;
                case 'ordersn':
                    $entity->where('r.ordersn','like','%'. $keyword.'%');
                    break;
            }
            $map['type'] = $type;
            $map['keyword'] = $keyword;
        }
        $status = $request->get('status');
        if($status || $status != ""){
            $entity->where('r.status', $status);
            $map['status'] = $status;
        }
        if($vip = $request->get('vip')){
            if($vip == 2){
                $vip = 0;
            }
            $entity->where('u.vip', $vip);
            $map['vip'] = $vip;
        }
//        if($types = $request->get('types')){
//            $entity->where('r.types', $types);
//            $map['types'] = $types;
//        }
        $startTime = $request->get('startTime');
        $endTime = $request->get('endTime');
        if($startTime){
            $entity->where('r.createtime', '>=', strtotime($startTime));
            $map['startTime'] = $startTime;
        }
        if($endTime){
            $entity->where('r.createtime', '<', strtotime($endTime));
            $map['endTime'] = $endTime;
        }
        if($left_uid){
            $entity->whereIn('u.id',$next_id);
        }
        $orderStr = 'r.createtime DESC';
        $list = $entity
            ->order($orderStr)
            ->paginate(15, false, [
                'query' => isset($map) ? $map : []
            ]);

        return $this->render('recharge', [
            'list' => $list,
            'allStatus' => RechargeModel::getAllStatus(),
            'allTypes' => RechargeModel::getAllTypes(),
            'queryStr' => isset($map) ? http_build_query($map) : '',
        ]);
    }
    public function exportRecharge(Request $request)
    {
        $page = $request->get('page')? $request->get('page'):0;
        $export = new Export();
        $entity = RechargeModel::alias('r')
            ->leftJoin('user u','r.uid = u.id')
            ->field('r.*,u.nick_name,u.mobile');
        if ( $keyword = $request->get('keyword') ) {
            $type = $request->get('type');
            switch ($type) {
                case 'nick_name':
                    $entity->where('u.nick_name','like','%'. $keyword.'%');
                    break;
                case 'mobile':
                    $entity->where('u.mobile','like','%'. $keyword.'%');
                    break;
                case 'ordersn':
                    $entity->where('r.ordersn','like','%'. $keyword.'%');
                    break;
            }
            $map['type'] = $type;
            $map['keyword'] = $keyword;
        }
        if($status = $request->get('status')){
            $entity->where('r.status', $status);
            $map['status'] = $status;
        }
        if($types = $request->get('types')){
            $entity->where('r.types', $types);
            $map['types'] = $types;
        }
        $orderStr = 'r.create_time DESC';
        $list = $entity
            ->page($page)
            ->order($orderStr)
            ->paginate(15, false, [
                'query' => isset($map) ? $map : []
            ]);
        $allStatus = RechargeModel::getAllStatus();
        $allTypes = RechargeModel::getAllTypes();
        $url = $request->domain();
        foreach ($list as $v){
            $v['status_info'] =   $allStatus[$v->status];
            $v['types_info'] =   $allTypes[$v->types];
            $v['proof_info'] =   $url.$v->proof;
        }
        $filename = '充值申请列表';
        $header = array('ID', '手机号', '订单号', '充值方式', '充值金额', '充值凭证', '审核状态', '时间');
        $index = array('id', 'mobile', 'orderNo', 'types_info', 'total', 'proof_info', 'status_info', 'create_time');
        $export->createtable($list, $filename, $header, $index);
    }
    /**
     * 通过充值申请|拒绝充值申请
     */
    public function examineRecharge(Request $request)
    {
        $id = $request->param('id');
        $types = $request->param('types');
        if(!RechargeModel::checkExist($id)){
            return json()->data(['code' => 1, 'message' => '非法操作']);
        }
        if($types == 'pass'){//通过申请

            $info = RechargeModel::where('id',$id)->find();
            $this->pid_money($info['uid'],$info['total']);
            $way = RechargeModel::getAllTypes()[$info['types']];
            Db::startTrans();
            try {

                $data = [
                    'num'  => $info['total'],
                    'uid'  => $info['uid'],
                    'remark'  => '会员'.$way.'充值',
                ];
                $query = new MyWallet();
                $res = $query->RechargeLog($query,$data);
                if(!$res){
                    Db::rollback();
                    return json()->data(['code' => 1, 'message' => '审核失败']);
                }

                RechargeModel::where('id',$id)->update([
                    'status' => 2,
                    'update_time' => time(),
                ]);
                $userModel = new \app\common\entity\User();
                $all_user = $userModel->getParents($info['uid'],3);
                $all_user[] = $info['uid'];
                $service = new Service();
                $service->upgrade($all_user);
                DB::table('user')->where('id',$info['uid'])->update(['level'=>$info['level']]);
                // $entry = new LevelUpLogModel();

                // $star_level = ConfigTeamLevelModel::where('assure_money',$info['total'])
                //     ->value('id');
                //     dump($star_level);die;
                // $entry->addNew($entry,[
                //     'uid' => $info['uid'],
                //     'level' => $star_level,
                //     'status' => 1,
                // ]);
                Db::commit();
                return json(['code' => 0, 'toUrl' => url('/admin/Apply/recharge')]);
            }catch (\Exception $e){

                Db::rollback();
                return json()->data(['code' => 1, 'message' => '审核失败']);
            }
        }elseif ($types == 'refuse'){//拒绝申请
            $res = RechargeModel::where('id',$id)->update([
                'status' => 3,
                'update_time' => time(),
            ]);
            if($res){
                return json(['code' => 0, 'toUrl' => url('/admin/Apply/recharge')]);
            }
            return json()->data(['code' => 1, 'message' => '审核失败']);
        }
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

    /**
     * 删除产品
     */
    public function deleteRecharge(Request $request)
    {
        $id = $request->param('id');
        if(!RechargeModel::checkExist($id)){
            return json()->data(['code' => 1, 'message' => '非法操作']);
        }
        $res = RechargeModel::where('id',$id)->delete();
        if($res){
            return json(['code' => 0, 'toUrl' => url('/admin/Apply/recharge')]);
        }
        return json()->data(['code' => 1, 'message' => '删除失败']);
    }
    /**
     * 提现申请列表
     */
    public function withdrawal(Request $request)
    {
        $uid = session('mysite_admin')['id'];
        $left_uid = ManageUser::where('id',$uid)->value('left_uid');
        $next_id = $this->getNext($left_uid);
        $entity = WithdrawalModel::alias('w')
            ->leftJoin('user u','w.uid = u.id')
            ->field('w.*,u.nick_name,u.mobile');
        if ( $keyword = $request->get('keyword') ) {
            $type = $request->get('type');
            switch ($type) {
                case 'nick_name':
                    $entity->where('u.nick_name','like','%'. $keyword.'%');
                    break;
                case 'mobile':
                    $entity->where('u.mobile','like','%'. $keyword.'%');
                    break;
                case 'orderNo':
                    $entity->where('w.orderNo','like','%'. $keyword.'%');
                    break;
            }
            $map['type'] = $type;
            $map['keyword'] = $keyword;
        }
        $status = $request->get('status');
        if($status || $status != ""){
            $entity->where('w.status', $status);
            $map['status'] = $status;
        }
        if($types = $request->get('types')){
            $entity->where('w.types', $types);
            $map['types'] = $types;
        }
        if($vip = $request->get('vip')){
            if($vip == 2){
                $vip = 0;
            }
            $entity->where('u.vip', $vip);
            $map['vip'] = $vip;
        }

        if($op_type = $request->get('op_type')){
            $entity->where('w.op_type', $op_type);
            $map['op_type'] = $op_type;
        }
        $startTime = $request->get('startTime');
        $endTime = $request->get('endTime');
        if($startTime){
            $entity->where('w.createtime', '>=', strtotime($startTime));
            $map['startTime'] = $startTime;
        }
        if($endTime){
            $entity->where('w.createtime', '<', strtotime($endTime));
            $map['endTime'] = $endTime;
        }
        if($left_uid){
            $entity->whereIn('u.id',$next_id);
        }
        $orderStr = 'w.createtime DESC';
        $list = $entity
            ->order($orderStr)
            ->paginate(15, false, [
                'query' => isset($map) ? $map : []
            ]);

        return $this->render('withdrawal', [
            'list' => $list,
            'allStatus' => WithdrawalModel::getAllStatus(),
            'allTypes' => WithdrawalModel::getAllTypes(),
            'allOpTypes' => WithdrawalModel::getAllOpTypes(),
            'queryStr' => isset($map) ? http_build_query($map) : '',
        ]);
    }
    public function exportWithdrawal(Request $request){
        $page = $request->get('page',0);
        $export = new Export();
        $entity = WithdrawalModel::alias('w')
            ->leftJoin('user u','w.uid = u.id')
            ->field('w.*,u.mobile');
        if ( $keyword = $request->get('keyword') ) {
            $type = $request->get('type');
            switch ($type) {
                case 'nick_name':
                    $entity->where('u.nick_name','like','%'. $keyword.'%');
                    break;
                case 'mobile':
                    $entity->where('u.mobile','like','%'. $keyword.'%');
                    break;
                case 'orderNo':
                    $entity->where('w.orderNo','like','%'. $keyword.'%');
                    break;
            }
            $map['type'] = $type;
            $map['keyword'] = $keyword;
        }
        if($status = $request->get('status')){
            $entity->where('w.status', $status);
            $map['status'] = $status;
        }
        if($types = $request->get('types')){
            $entity->where('w.types', $types);
            $map['types'] = $types;
        }
        $orderStr = 'w.create_time DESC';
        $list = $entity
            ->order($orderStr)
            ->page($page)
            ->paginate(15, false, [
                'query' => isset($map) ? $map : []
            ])
            ->toArray();
        $allTypes = WithdrawalModel::getAllTypes();
        $allStatus = WithdrawalModel::getAllStatus();
        $data =[];
        foreach ($list['data'] as $k=>$v){
            $data[$k]['id'] = $v['id'];
            $data[$k]['mobile'] = $v['mobile'];
            $data[$k]['types_info'] = $allTypes[$v['types']];
            $data[$k]['money'] = $v['money'];
            $data[$k]['realmoney'] = $v['realmoney'];
//            $data[$k]['bank_user_name'] = $v['bank_user_name'];
//            $data[$k]['bank_name'] = $v['bank_name'];
//            $data[$k]['bank_card'] = $v['bank_card'];
            $data[$k]['alipay_name'] = $v['alipay_name'];
            $data[$k]['alipay_account'] = $v['alipay_account'];
            $data[$k]['status_info'] = $allStatus[$v['status']];
            $data[$k]['create_time'] = $v['create_time'];
        }
        $filename = '提现申请列表';
        $header = array('ID', '手机号', '提现方式', '提现金额', '实际到账金额', '开户人', '开户行','银行卡号','支付宝姓名','支付宝账号','审核状态','时间');
        $export->exportExcel($header,$data,'Sheet1',$filename);
    }
    /**
     * 通过提现申请|拒绝提现申请
     */
    public function examineWithdrawal(Request $request)
    {
        $id = $request->param('id');
        $types = $request->param('types');
        if(!WithdrawalModel::checkExist($id)){
            return json()->data(['code' => 1, 'message' => '非法操作']);
        }
        $info = WithdrawalModel::where('id',$id)->find();
        if($types == 'pass'){//通过申请
            $res = WithdrawalModel::where('id',$id)->update([
                'status' => 1,
                'examinetime' => time(),
            ]);
            if($res) {
                return json(['code' => 0, 'toUrl' => url('/admin/Apply/withdrawal')]);
            }
            return json()->data(['code' => 1, 'message' => '审核失败']);
        }elseif ($types == 'refuse'){//拒绝申请
            $way = WithdrawalModel::getAllOpTypes()[$info['op_type']];
            Db::startTrans();
            try {
                if($info['op_type'] == 1){
                    $credit = "commission";
                }elseif ($info['op_type'] == 2){
                    $credit = "bonus";
                }
                $result = Db::name('user_wallet')->where('uid',$info['uid'])->setInc($credit,$info['money']);
                if(!$result){
                    Db::rollback();
                    return json()->data(['code' => 1, 'message' => "拒绝失败"]);
                }
                $user = Db::name('user_wallet')->where('uid',$info['uid'])->find();
                $data = [
                    'uid'=>$info['uid'],
                    'credit'=>$credit,
                    'change_money'=>$info['money'],
                    'original_money'=>$user[$credit],
                    'after_change_money'=>$user[$credit] + $info['money'],
                    'types'=>4,
                    'remarks'=>'拒绝会员'.$way.'，退还',
                    'op_type'=>2,
                    'createtime'=>time(),
                ];

                $result = Db::name('user_wallet_log')->insert($data);
                if(!$result){
                    Db::rollback();
                    return json()->data(['code' => 1, 'message' => "拒绝失败"]);
                }
//                $data = [
//                    'num'  => $info['money'],
//                    'uid'  => $info['uid'],
//                    'remark'  => '拒绝会员'.$way.'提现，退还',
//                ];
//                $query = new MyWallet();
//                $res = $query->refuseWithdrawal($query,$data);
//
//                if(!$res){
//                    Db::rollback();
//                    return json()->data(['code' => 1, 'message' => $res]);
//                }
                WithdrawalModel::where('id',$id)->update([
                    'status' => -1,
                    'examinetime' => time(),
                ]);
                Db::commit();
                return json(['code' => 0, 'toUrl' => url('/admin/Apply/withdrawal')]);
            }catch (\Exception $e){
                Db::rollback();
                return json()->data(['code' => 1, 'message' => '审核失败']);
            }
        }
    }
    /**
     * 删除提现申请
     */
    public function deleteWithdrawal(Request $request)
    {
        $id = $request->param('id');
        if(!WithdrawalModel::checkExist($id)){
            return json()->data(['code' => 1, 'message' => '非法操作']);
        }
        $res = WithdrawalModel::where('id',$id)->delete();
        if($res){
            return json(['code' => 0, 'toUrl' => url('/admin/Apply/withdrawal')]);
        }
        return json()->data(['code' => 1, 'message' => '删除失败']);
    }
    /**
     * 抖音快手审核
     */
    public function other(Request $request)
    {
        $uid = session('mysite_admin')['id'];
        $left_uid = ManageUser::where('id',$uid)->value('left_uid');
        $next_id = $this->getNext($left_uid);
        $entity = UserOtherModel::alias('uo')
            ->leftJoin('user u','uo.uid = u.id')
            ->field('uo.*,u.nick_name,u.mobile');
        if ( $keyword = $request->get('keyword') ) {
            $type = $request->get('type');
            switch ($type) {
                case 'nick_name':
                    $entity->where('u.nick_name','like','%'. $keyword.'%');
                    break;
                case 'mobile':
                    $entity->where('u.mobile','like','%'. $keyword.'%');
                    break;
            }
            $map['type'] = $type;
            $map['keyword'] = $keyword;
        }
        if($status = $request->get('status')){
            $entity->where('uo.status', $status);
            $map['status'] = $status;
        }
        if($types = $request->get('types')){
            $entity->where('uo.types', $types);
            $map['types'] = $types;
        }
        $startTime = $request->get('startTime');
        $endTime = $request->get('endTime');
        if($startTime){
            $entity->where('uo.create_time', '>=', strtotime($startTime));
            $map['startTime'] = $startTime;
        }
        if($endTime){
            $entity->where('uo.create_time', '<', strtotime($endTime));
            $map['endTime'] = $endTime;
        }
        if($left_uid){
            $entity->whereIn('u.id',$next_id);
        }
        $orderStr = 'uo.create_time DESC';
        $list = $entity
            ->order($orderStr)
            ->paginate(15, false, [
                'query' => isset($map) ? $map : []
            ]);
        return $this->render('other', [
            'list' => $list,
            'allStatus' => UserOtherModel::getAllStatus(),
            'allTypes' => UserOtherModel::getAllTypes(),
            'queryStr' => isset($map) ? http_build_query($map) : '',
        ]);
    }
    /**
     * 抖音快手审核
     */
    public function examineOther(Request $request)
    {
        $id = $request->param('id');
        $types = $request->param('types');
        if(!UserOtherModel::checkExist($id)){
            return json()->data(['code' => 1, 'message' => '非法操作']);
        }
        $otherInfo = UserOtherModel::where('id',$id)->find();
        if($otherInfo['types'] == 1){//抖音
            $field = 'tiktok_status';
        }elseif ($otherInfo['types'] == 2){//快手
            $field = 'kwaifu_status';
        }
        if($types == 'pass'){//通过申请
            $res = UserOtherModel::where('id',$id)->update([
                'status' => 2,
                'examine_time' => time(),
            ]);
            if(!$res) {
                return json()->data(['code' => 1, 'message' => '审核失败']);
            }
            $res = \app\common\entity\User::where('id',$otherInfo['uid'])
                ->setField($field,3);
            if(!$res) {
                return json()->data(['code' => 1, 'message' => '审核失败']);
            }
            return json(['code' => 0, 'toUrl' => url('/admin/Apply/other')]);
        }elseif ($types == 'refuse'){//拒绝申请
            $res = UserOtherModel::where('id',$id)->update([
                'status' => 3,
                'examine_time' => time(),
            ]);
            if(!$res) {
                return json()->data(['code' => 1, 'message' => '审核失败']);
            }
            $res = \app\common\entity\User::where('id',$otherInfo['uid'])
                ->setField($field,1);
            if(!$res) {
                return json()->data(['code' => 1, 'message' => '审核失败']);
            }
            return json(['code' => 0, 'toUrl' => url('/admin/Apply/other')]);
        }
    }
}
