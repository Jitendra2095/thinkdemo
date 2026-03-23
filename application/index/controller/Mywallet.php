<?php


namespace app\index\controller;

use app\common\entity\ActiveApply;
use app\common\entity\ActiveConfig;
use app\common\entity\Config;
use app\common\entity\MyWalletLog;
use app\common\entity\PackageList;
use app\common\entity\PersonService;
use app\common\entity\Proportion;
use app\common\entity\Recharge;
use app\common\entity\RechargeList;
use app\common\entity\RechargeLog;
use app\common\entity\Transfer;
use app\common\entity\User;
use app\common\entity\UserCard;
use app\common\entity\UserTransfer;
use app\common\entity\UserWalletAddress;
use app\common\entity\WalletAddressConfig;
use app\common\entity\WalletRatio;
use app\index\validate\RegisterForm;
use think\Exception;
use think\Request;
use think\Session;

class Mywallet extends Base
{

    #钱包信息
    public function mywalletInfo()
    {
        $uid = $this->userId;
        $list = \app\common\entity\MyWallet::where('uid', $uid)->find();
        $userInfo = User::where('id', $this->userId)->find();
        $is_certification = $userInfo['is_certification'];
        $usdt_cny = Config::getOneValue('usdt_cny');
        $tve_cny = Proportion::order('date desc')->where('date', '<=', date('Y-m-d H:i:s', time()))->value('ratio');

        $list['tve_usdt_ratio'] = Config::getOneValue('tve_usdt_ratio');
        $list['usdt_cny'] = bcmul($list['number'], $usdt_cny, 5);
        $list['safe_cny'] = bcmul($list['safe_num'], $tve_cny, 5);
        $pLModel = new PackageList();

        $list['tve'] = bcadd($list['freeze_num'],$list['safe_num'],5);
        $list['tve_cny'] = bcmul(($list['freeze_num'] + $list['safe_num']), $tve_cny, 5);

        if ($is_certification == -1) {
            $msg = '请先实名认证';
        } else {
            $msg = '获取成功';
        }

        return json([
            'code' => 0,
            'msg' => $msg,
            'info' => $list,
            'real' => $is_certification,
        ]);
    }


    /**
     * 充币(不用)
     */
    public function recharge(Request $request)
    {
        $uid = $this->userId;
        if ($request->isGet()) {
            $info = User::field('money_address')->where('id', $uid)->find();
            return json()->data(['code' => 0, 'info' => $info]);
        }
        if ($request->isPost()) {
            $validate = $this->validate($request->post(), '\app\index\validate\Recharge');
            if ($validate !== true) {
                return json(['code' => 1, 'msg' => $validate]);
            }
            $add_data = $request->post();
            $add_data['uid'] = $this->userId;
            $model = new Recharge();
            $res = $model->addNew($model, $add_data);
            if ($res) {
                return json()->data(['code' => 0, 'msg' => '操作成功']);
            }
            return json()->data(['code' => 1, 'msg' => '操作失败']);
        }
    }

    /**
     * 充币记录(不用)
     */
    public function rechargeLog1(Request $request)
    {
        $limit = $request->get('limit') ? $request->get('limit') : 15;
        $page = $request->get('page') ? $request->get('page') : 1;
        $list = Recharge::field('')
            ->where('uid', $this->userId)
            ->order('create_time', 'desc')
            ->page($page)
            ->paginate($limit, false, [
                'query' => $request->param() ? $request->param() : []
            ]);
        return json()->data(['code' => 0, 'msg' => '请求成功', 'info' => $list]);
    }

    #是否登录
    public function is_login()
    {
        $uid = $this->userId;
        $userInfo = User::where('id', $uid)->find();
        $login_time = $userInfo['login_time'];
        $session_login_time = Session::get('login_time');

        if ($login_time != $session_login_time) {
            return json(['code' => 1, 'msg' => '账号在别处登录,强制退出', 'url' => 'login']);
        }
        if ($uid) {
            return json(['code' => 0, 'msg' => 'yes', 'info' => $uid]);
        }
        return json(['code' => 1, 'msg' => '暂未登录', 'url' => 'login']);

    }


    /**
     * 申诉
     */
    public function appeal(Request $request)
    {
        $validate = $this->validate($request->post(), '\app\index\validate\Appeal');
        if ($validate !== true) {
            return json(['code' => 1, 'msg' => $validate]);
        }
        $model = new PersonService();
        $add_data = $request->post();
        $add_data['uid'] = $this->userId;
        $res = $model->addNew($model, $add_data);
        if ($res) {
            return json()->data(['code' => 0, 'msg' => '操作成功']);
        }
        return json()->data(['code' => 1, 'msg' => '操作失败']);
    }

    /**
     * 申诉记录
     */
    public function appealLog(Request $request)
    {
        $limit = $request->get('limit') ? $request->get('limit') : 15;
        $page = $request->get('page') ? $request->get('page') : 1;
        $list = PersonService::where('uid', $this->userId)
            ->order('create_time', 'desc')
            ->page($page)
            ->paginate($limit, false, [
                'query' => $request->param() ? $request->param() : []
            ]);
        return json()->data(['code' => 0, 'msg' => '请求成功', 'info' => $list]);
    }


    #提币
    public function addWithdrawList(Request $request)
    {
        $reg_ratio = Config::getOneValue('reg_ratio');
        $reg_limit = Config::getOneValue('reg_limit');
        if ($request->isPost()) {


            $num = $request->post('num');
            $trad_password = $request->post('trad_password');
            $address = $request->post('address');
            $isChinese = new \app\index\validate\UserWalletAddress();
            $res = $isChinese->checkChinese($address);
            if ($res !== true) {
                return json(['code' => 1, 'msg' => $res]);
            }
            if (!$num || !$trad_password || !$address) {
                return json(['code' => 1, 'msg' => '缺少参数']);
            }

            if ($num < $reg_limit) {
                return json(['code' => 1, 'msg' => '数量少于最低提币数量']);
            }
            $mywalletModel = new \app\common\entity\MyWallet();

            $isEthAddr = $mywalletModel->isEthAddr($address);
            if ($isEthAddr !== true) {
                return json(['code' => 1, 'msg' => '请输入正确ETH钱包地址']);
            }

            $mywalletInfo = $mywalletModel->where('uid', $this->userId)->find();
            $user = User::where('id', $this->userId)->find();
            $service = new \app\common\service\Users\Service();
            $result = $service->checkSafePassword($trad_password, $user);
            if (!$result) {
                return json(['code' => 1, 'msg' => '支付密码输入错误']);
            }
            $form = new RegisterForm();
            if (!$form->checkCode($request->post('code'), $user['mobile'])) {
                return json(['code' => 1, 'msg' => '验证码输入错误']);
            }
            if ($num > 40) {

                $ratio_total = $num * $reg_ratio / 100;//手续费 提币低于40usdt收取2usdt的手续费，超过按照5%usdt手续费。
            } else {
                $ratio_total = 2;//手续费
            }
            $total = $num + $ratio_total;//提10的9

            if ($mywalletInfo['number'] < $total) {
                return json(['code' => 1, 'msg' => 'USDT余额不足']);

            }

            $rechargeList = new RechargeList();
            $mywalletLogModel = new MyWalletLog();

            $rechargeList->startTrans();
            $mywalletLogModel->startTrans();
            $mywalletModel->startTrans();

            $data = $request->post();
            $data['uid'] = $this->userId;
            $data['types'] = 2;
            $data['status'] = 1;
            $data['ratio'] = $ratio_total;

            try {

                $rechargeList->add($rechargeList, $data);
                $mywalletLogModel->addLog($this->userId, $total, 1, '提币(手续费:' . $ratio_total . ')', 1, 2);
                $mywalletModel->where('uid', $this->userId)->setDec('number', $total);

                $rechargeList->commit();
                $mywalletLogModel->commit();
                $mywalletModel->commit();
                return json(['code' => 0, 'msg' => '提交成功']);

            } catch (\Exception $e) {

                $rechargeList->rollback();
                $mywalletLogModel->rollback();
                $mywalletModel->rollback();

                return json(['code' => 1, 'msg' => '提交失败']);
            }
        }
        if ($request->isGet()) {
            $list = \app\common\entity\MyWallet::where('uid', $this->userId)->find();
            $list['reg_ratio'] = $reg_ratio;
            $list['reg_limit'] = $reg_limit;
            return json(['code' => 0, 'msg' => '获取成功', 'info' => $list]);

        }


    }

    #提币充币记录
    public function rechargeLog(Request $request)
    {
        $limit = $request->post('limit') ? $request->post('limit') : 15;
        $page = $request->post('page') ? $request->post('page') : 1;
        $types = $request->post('types');
        $entity = RechargeList::where('uid', $this->userId);
        if ($types) {
            $entity->where('types', $types);
        }
        $list = $entity->page($page)
            ->order('create_time desc')
            ->paginate($limit, false, [
                'query' => $request->param() ? $request->param() : []
            ]);

        if ($list) {
            return json(['code' => 0, 'msg' => '获取成功', 'info' => $list]);
        }
        return json(['code' => 1, 'msg' => '暂无数据']);

    }


    #闪兑
    public function transfer(Request $request)
    {
        $uid = $this->userId;
        $validate = $this->validate($request->post(), '\app\index\validate\Transfer');
        if ($validate !== true) {
            return json(['code' => 1, 'msg' => $validate]);
        }
        $types = $request->post('types');
        $num = $request->post('num');

        $userInfo = User::where('id', $uid)->find();

        $model = new \app\common\service\Users\Service();
        if (!$model->checkSafePassword($request->post('trad_password'), $userInfo)) {
            return json()->data(['code' => 1, 'msg' => '交易密码错误']);
        }


//        $model = new Transfer();
        $mywawlletModel = new \app\common\entity\MyWallet();
        $mywalletlogModel = new MyWalletLog();

//        $model->startTrans();
        $mywawlletModel->startTrans();
        $mywalletlogModel->startTrans();

        $mywalletInfo = $mywawlletModel->where('uid', $uid)->find();
        try {

            if ($types == 1) {
                if ($num > $mywalletInfo['number']) {
//                    $model->rollback();
                    $mywawlletModel->rollback();
                    $mywalletlogModel->rollback();
                    return json(['code' => 1, 'msg' => 'USDT数量不足']);
                }//bcmul
                $ratio = $mywawlletModel->usdtToTve(1);
                $tveCanGet = bcmul($num, $ratio, 5);
                $mywalletlogModel->addLog($uid, $num, 1, 'USDT闪兑到TVE', 3, 2);
                $mywawlletModel->where('uid', $uid)->setDec('number', $num);
//                $mywalletlogModel->addLog($uid, $num, 2, 'USDT闪兑到TVE', 3, 1);
                $mywalletlogModel->addLog($uid, $tveCanGet, 3, 'USDT闪兑到TVE', 3, 1);
                $mywawlletModel->where('uid', $uid)->setInc('freeze_num', $tveCanGet);
                $msg = '闪兑成功';
            } else {
                $tve_usdt_ratio = Config::getOneValue('tve_usdt_ratio');
                $ratio_num = $num * $tve_usdt_ratio;//手续费
                $num_ratio = $num + $ratio_num;
                if ($num_ratio > $mywalletInfo['safe_num']) {
//                    $model->rollback();
                    $mywawlletModel->rollback();
                    $mywalletlogModel->rollback();
                    return json(['code' => 1, 'msg' => 'TVE数量不足']);
                }
                $ratio = $mywawlletModel->usdtToTve(2);
                $UsdtCanGet = bcmul($num, $ratio, 5);
                $mywalletlogModel->addLog($uid, $num_ratio, 2, 'TVE闪兑到USDT(手续费:' . $ratio_num . ')', 4, 2);
                $mywawlletModel->where('uid', $uid)->setDec('safe_num', $num_ratio);
                $mywalletlogModel->addLog($uid, $UsdtCanGet, 1, '闪兑成功转入', 4, 1);
                $mywawlletModel->where('uid', $uid)->setInc('number', $UsdtCanGet);
//                $msg = '申请成功,请耐心等待审核';
                $msg = '闪兑成功';

            }

//            $data = $request->post();
//            $data['uid'] = $uid;
//
//            $model->addNew($model, $data);
//            $model->commit();
            $mywawlletModel->commit();
            $mywalletlogModel->commit();
            return json(['code' => 0, 'msg' => $msg]);

        } catch (\Exception $e) {
//            $model->rollback();
            $mywawlletModel->rollback();
            $mywalletlogModel->rollback();
            return json(['code' => 1, 'msg' => '提交失败']);

        }

    }


    #闪兑记录
    public function transferLog(Request $request)
    {
        $uid = $this->userId;

        $limit = $request->post('limit') ? $request->post('limit') : 15;
        $page = $request->post('page') ? $request->post('page') : 1;

        $list = Transfer::where('uid', $uid)
            ->page($page)
            ->order('create_time desc')
            ->paginate($limit, false, [
                'query' => $request->param() ? $request->param() : []
            ]);
        if ($list) {
            return json(['code' => 0, 'msg' => '获取成功', 'info' => $list]);
        }
        return json(['code' => 1, 'msg' => '暂无数据']);
    }


    #账变列表
    public function mywalletLog(Request $request)
    {
        $uid = $this->userId;
        $limit = $request->post('limit') ? $request->post('limit') : 15;
        $page = $request->post('page') ? $request->post('page') : 1;
        $money_types = $request->post('money_types');
        if ($money_types == 2) {
            $money_types = [2, 3];
        }

        $model = MyWalletLog::where('uid', $uid)
            ->whereIn('money_types', $money_types);
        $list = $model->order('create_time desc')
            ->page($page)
            ->paginate($limit, false, [
                'query' => $request->param() ? $request->param() : []
            ]);
        if ($money_types == 4) {
            $hash = Config::getOneValue('hash_tve_ratio');
            foreach ($list as &$v) {
                $v['number'] = $v['number'] / $hash;
                $v['old'] = $v['old'] / $hash;
                $v['new'] = $v['new'] / $hash;
            }
        }

        if ($list) {
            return json(['code' => 0, 'msg' => '获取成功', 'info' => $list,]);
        }
        return json(['code' => 1, 'msg' => '暂无数据']);
    }

    #互转
    public function userToUser(Request $request)
    {
        $user_ratio = Config::getOneValue('user_ratio');
        if ($request->isPost()) {
            $address = $request->post('address');
            $num = $request->post('num');

            $to_num = $num + ($num * $user_ratio);

            $validate = $this->validate($request->post(), '\app\index\validate\UserTransfer');
            if ($validate !== true) {
                return json(['code' => 1, 'msg' => $validate]);
            }

            $userModel = new User();
            $forUserInfo = $userModel->where('mobile', $address)->find();
            if (!$forUserInfo) {
                return json(['code' => 1, 'msg' => '账号不存在']);
            }
            $uid = $this->userId;
            $mywalletModel = new \app\common\entity\MyWallet();
            $mywalletLogModel = new MyWalletLog();
            $userTransfer = new UserTransfer();

            $mywalletInfo = $mywalletModel->where('uid', $uid)->find();
            if ($mywalletInfo['number'] < $to_num) {

                return json(['code' => 1, 'msg' => 'USDT钱包余额不足']);
            }
            $userInfo = $userModel->where('id', $uid)->find();

            if ($userInfo['is_shop'] != $forUserInfo['is_shop']) {
                return json(['code' => 1, 'msg' => '双方身份不一致']);
            }

            $model = new \app\common\service\Users\Service();
            if (!$model->checkSafePassword($request->post('trad_password'), $userInfo)) {
                return json()->data(['code' => 1, 'msg' => '交易密码错误']);
            }

            $form = new RegisterForm();
            if (!$form->checkCode($request->post('code'), $userInfo['mobile'])) {
//                return json(['code' => 1, 'msg' => '验证码输入错误']);
            }

            $data = $request->post();
            $data['for_uid'] = $forUserInfo['id'];
            $data['uid'] = $uid;

            $mywalletModel->startTrans();
            $mywalletLogModel->startTrans();
            $userTransfer->startTrans();

            try {

                $mywalletLogModel->addLog($uid, $to_num, 1, '会员互转(' . $forUserInfo['mobile'] . ')', 10, 2);
                $mywalletModel->where('uid', $uid)->setDec('number', $to_num);
                $mywalletLogModel->addLog($forUserInfo['id'], $num, 1, '会员互转(' . $userInfo['mobile'] . ')', 10, 1);
                $mywalletModel->where('uid', $forUserInfo['id'])->setInc('number', $num);
                $userTransfer->addNew($userTransfer, $data);

                $mywalletModel->commit();
                $mywalletLogModel->commit();
                $userTransfer->commit();

                return json(['code' => 0, 'msg' => '互转成功']);

            } catch (\Exception $e) {
                $mywalletModel->rollback();
                $mywalletLogModel->rollback();
                $userTransfer->rollback();
                return json(['code' => 1, 'msg' => '互转失败']);
            }


        }

        if ($request->isGet()) {
            return json(['code' => 0, 'msg' => '获取成功', 'info' => $user_ratio * 100]);

        }

    }


    #互转记录
    public function userTransferLog(Request $request)
    {
        $limit = $request->post('limit') ? $request->post('limit') : 15;
        $page = $request->post('page') ? $request->post('page') : 1;
        $list = MyWalletLog::where('uid', $this->userId)
            ->where('types', 10)
            ->page($page)
            ->order('create_time desc')
            ->paginate($limit, false, [
                'query' => $request->param() ? $request->param() : []
            ]);

        if ($list) {
            return json(['code' => 0, 'msg' => '获取成功', 'info' => $list]);
        }
        return json(['code' => 1, 'msg' => '暂无数据']);

    }

    #奖励记录
    public function rewardLog()
    {
        $uid = $this->userId;
        $mwlModel = new MyWalletLog();
        $list = $mwlModel->where('uid', $uid)
            ->whereIn('types', [6, 8])
            ->select();
        if ($list) {
            return json(['code' => 0, 'msg' => '获取成功', 'info' => $list]);
        }
        return json(['code' => 1, 'msg' => '暂无数据']);

    }

}