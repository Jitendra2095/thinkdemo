<?php

namespace app\admin\controller;


use app\common\entity\Export;
use app\common\entity\FundLogModel;
use app\common\entity\MyWallet;
use app\common\entity\MyWalletLog;
use app\common\entity\PrizeLogModel;
use app\common\entity\RechargeModel;
use app\common\entity\UserPaymentModel;
use app\common\entity\WithdrawalModel;
use think\Db;
use think\Request;

class Finance extends Admin {
    /**
     * 充值申请列表
     */
    public function userWalletLog(Request $request)
    {
        $entry = MyWalletLog::alias('mwl')
            ->leftJoin('user_wallet mw','mwl.uid = mw.uid')
            ->leftJoin('user u','u.id = mwl.uid')
            ->field('mwl.*,u.nick_name,u.mobile');
        if ($keyword = $request->get('keyword')) {
            $type = $request->get('type');
            switch ($type) {
                case 'nick_name':
                    $entry->where('u.nick_name', 'like','%'.$keyword.'%');
                    break;
                case 'mobile':
                    $entry->where('u.mobile', 'like','%'.$keyword.'%');
                    break;
            }
            $map['type'] = $type;
            $map['keyword'] = $keyword;
        }
        if($types = $request->get('types')){
            $entry->where('mwl.types', $types);
            $map['types'] = $types;
        }
        $startTime = $request->get('startTime');
        $endTime = $request->get('endTime');
        if($startTime){
            $entry->where('mwl.createtime', '>=', strtotime($startTime));
            $map['startTime'] = $startTime;
        }
        if($endTime){
            $entry->where('mwl.createtime', '<', strtotime($endTime));
            $map['endTime'] = $endTime;
        }
        $list = $entry
            ->order('mwl.createtime','desc')
            ->paginate(15,false,[
                'query' => $request->param()?$request->param():[],
            ]);
        return $this->render('userWalletLog',[
            'list' => $list,
            'types' => MyWalletLog::getAllTypes(),
            'user' => (new \app\common\entity\User()),
        ]);
    }
    /**
     * 导出充值申请列表
     */
    public function exportUserWalletLog(Request $request){
        $export = new Export();
        $entry = MyWalletLog::alias('mwl')
            ->leftJoin('user_wallet mw','mwl.uid = mw.uid')
            ->leftJoin('user u','u.id = mwl.uid')
            ->field('mwl.*,u.nick_name,u.mobile');
        if ($keyword = $request->get('keyword')) {
            $type = $request->get('type');
            switch ($type) {
                case 'nick_name':
                    $entry->where('u.nick_name', 'like','%'.$keyword.'%');
                    break;
                case 'mobile':
                    $entry->where('u.mobile', 'like','%'.$keyword.'%');
                    break;
            }
            $map['type'] = $type;
            $map['keyword'] = $keyword;
        }
        if($types = $request->get('types')){
            $entry->where('mwl.types', $types);
            $map['types'] = $types;
        }
        $startTime = $request->get('startTime');
        $endTime = $request->get('endTime');
        if($startTime){
            $entry->where('mwl.createtime', '>=', strtotime($startTime));
            $map['startTime'] = $startTime;
        }
        if($endTime){
            $entry->where('mwl.createtime', '<', strtotime($endTime));
            $map['endTime'] = $endTime;
        }
        $page = $request->get('page')? $request->get('page'):0;
        $list = $entry
            ->page($page)
            ->order('mwl.createtime','desc')
            ->paginate(15,false,[
                'query' => $request->param()?$request->param():[],
            ]);
        foreach ($list as $v){
            $v['types_name'] = $v->getType($v->types);
            $v['change_money'] = $v['op_type'] == 1 ? "-".$v['change_money'] : "+".$v['change_money'];
        }
        $filename = '会员财务记录';
        $header = array('ID', '流水类型', '原来的金额', '变化的金额', '变化后的金额', '备注', '时间');
        $index = array('id', 'types_name', 'original_money', 'change_money', 'after_change_money', 'remarks', 'createtime');
        $export->createtable($list, $filename, $header, $index);
    }
    /**
     * 奖金记录
     */
    public function prizetLog(Request $request)
    {
        $entry = PrizeLogModel::alias('pl')
            ->field('pl.*');
        if ($keyword = $request->get('keyword')) {
            $type = $request->get('type');
            switch ($type) {
                case 'user_id':
                    $entry
                        ->leftJoin('user u','u.id = pl.user_id')
                        ->where('u.nick_name|u.mobile', 'like','%'.$keyword.'%');
                    break;
                case 'from_user':
                    $entry
                        ->leftJoin('user u','u.id = pl.from_user')
                        ->where('u.nick_name|u.mobile', 'like','%'.$keyword.'%');
                    break;
            }
            $map['type'] = $type;
            $map['keyword'] = $keyword;
        }
        if($types = $request->get('types')){
            $entry->where('pl.types', $types);
            $map['types'] = $types;
        }
        if($status = $request->get('status')){
            $entry->where('pl.status', $status);
            $map['status'] = $status;
        }
        $startTime = $request->get('startTime');
        $endTime = $request->get('endTime');
        if($startTime){
            $entry->where('pl.create_time', '>=', strtotime($startTime));
            $map['startTime'] = $startTime;
        }
        if($endTime){
            $entry->where('pl.create_time', '<', strtotime($endTime));
            $map['endTime'] = $endTime;
        }
        $list = $entry
            ->order('pl.create_time','desc')
            ->paginate(15,false,[
                'query' => $request->param()?$request->param():[],
            ]);
        return $this->render('prizetLog',[
            'list' => $list,
            'types' => PrizeLogModel::getAllTypes(),
            'status' => PrizeLogModel::getAllStatus(),
            'user' => (new \app\common\entity\User()),
        ]);
    }
    /**
     * 导出奖金记录
     */
    public function exportPrizetLog(Request $request)
    {
        $export = new Export();
        $entry = PrizeLogModel::alias('pl')
            ->field('pl.*');
        if ($keyword = $request->get('keyword')) {
            $type = $request->get('type');
            switch ($type) {
                case 'user_id':
                    $entry
                        ->leftJoin('user u','u.id = pl.user_id')
                        ->where('u.nick_name|u.mobile', 'like','%'.$keyword.'%');
                    break;
                case 'from_user':
                    $entry
                        ->leftJoin('user u','u.id = pl.from_user')
                        ->where('u.nick_name|u.mobile', 'like','%'.$keyword.'%');
                    break;
            }
            $map['type'] = $type;
            $map['keyword'] = $keyword;
        }
        if($types = $request->get('types')){
            $entry->where('pl.types', $types);
            $map['types'] = $types;
        }
        if($status = $request->get('status')){
            $entry->where('pl.status', $status);
            $map['status'] = $status;
        }
        $startTime = $request->get('startTime');
        $endTime = $request->get('endTime');
        if($startTime){
            $entry->where('pl.create_time', '>=', strtotime($startTime));
            $map['startTime'] = $startTime;
        }
        if($endTime){
            $entry->where('pl.create_time', '<', strtotime($endTime));
            $map['endTime'] = $endTime;
        }
        $page = $request->get('page')? $request->get('page'):0;
        $list = $entry
            ->page($page)
            ->order('pl.create_time','desc')
            ->paginate(15,false,[
                'query' => $request->param()?$request->param():[],
            ]);
        $types = PrizeLogModel::getAllTypes();
        $status = PrizeLogModel::getAllStatus();
        $user = new \app\common\entity\User();
        foreach ($list as $v){
            $v['user'] = $user->getUserInfo($v->user_id)['mobile'];
            $v['from'] = $user->getUserInfo($v->from_user)['mobile'];
            $v['types_name'] = $v->getType($v->types);
            $v['status_name'] = $v->getStatus($v->status);
            if($v['status'] == 1){
                $v['update'] = '未发放';
            }else{
                $v['update'] = $v->update_time;
            }

        }
        $filename = '奖金记录';
        $header = array('ID', '奖励人', '来自', '金额', '类型', '状态', '备注', '创建时间', '发放时间');
        $index = array('id', 'user', 'from', 'total', 'types_name', 'status_name', 'remarks', 'create_time', 'update_time');
        $export->createtable($list, $filename, $header, $index);
    }
    /**
     * 公益基金记录
     */
    public function fundLog(Request $request)
    {
        $entry = FundLogModel::alias('fl')
            ->field('fl.*,u.id as user_id,u.nick_name,u.mobile')
            ->leftJoin('user u','u.id = fl.from_user');
        if ($keyword = $request->get('keyword')) {
            $type = $request->get('type');
            switch ($type) {
                case 'user_id':
                    $entry
                        ->where('u.nick_name|u.mobile', 'like','%'.$keyword.'%');
                    break;
            }
            $map['type'] = $type;
            $map['keyword'] = $keyword;
        }
        $startTime = $request->get('startTime');
        $endTime = $request->get('endTime');
        if($startTime){
            $entry->where('fl.create_time', '>=', strtotime($startTime));
            $map['startTime'] = $startTime;
        }
        if($endTime){
            $entry->where('fl.create_time', '<', strtotime($endTime));
            $map['endTime'] = $endTime;
        }
        $list = $entry
            ->order('fl.create_time','desc')
            ->paginate(15,false,[
                'query' => $request->param()?$request->param():[],
            ]);
        return $this->render('fundLog',[
            'list' => $list,
            'user' => (new \app\common\entity\User()),
        ]);
    }
    public function exportFundLog(Request $request)
    {
        $export = new Export();
        $entry = FundLogModel::alias('fl')
            ->field('fl.*,u.id as user_id,u.nick_name,u.mobile')
            ->leftJoin('user u','u.id = fl.from_user');
        if ($keyword = $request->get('keyword')) {
            $type = $request->get('type');
            switch ($type) {
                case 'user_id':
                    $entry
                        ->where('u.nick_name|u.mobile', 'like','%'.$keyword.'%');
                    break;
            }
            $map['type'] = $type;
            $map['keyword'] = $keyword;
        }
        $startTime = $request->get('startTime');
        $endTime = $request->get('endTime');
        if($startTime){
            $entry->where('fl.create_time', '>=', strtotime($startTime));
            $map['startTime'] = $startTime;
        }
        if($endTime){
            $entry->where('fl.create_time', '<', strtotime($endTime));
            $map['endTime'] = $endTime;
        }
        $page = $request->get('page')? $request->get('page'):0;
        $list = $entry
            ->page($page)
            ->order('fl.create_time','desc')
            ->paginate(15,false,[
                'query' => $request->param()?$request->param():[],
            ]);
        $user = new \app\common\entity\User();
        foreach ($list as $v){
            $v['user'] = $user->getUserInfo($v->from_user)['mobile'];
        }
        $filename = '公益基金记录';
        $header = array('ID', '奖励人', '总消费金额', '贡献公益基金',  '备注', '创建时间');
        $index = array('id', 'user', 'all_total', 'fund_total', 'remarks', 'create_time');
        $export->createtable($list, $filename, $header, $index);
    }
}
