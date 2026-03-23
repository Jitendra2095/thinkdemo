<?php

namespace app\admin\controller;

use app\common\entity\Export;
use app\common\entity\OrderDetailModel;
use app\common\entity\OrderModel;
use think\Db;
use think\Request;

class Order extends Admin {
    /**
     * 订单列表
     */
    public function lists(Request $request)
    {
        $entity = OrderModel::alias('o')
            ->leftJoin('order_detail od','o.id = od.order_id')
            ->leftJoin('goods g','o.good_id = g.id')
            ->field('o.*,g.good_name,od.addressee');
        if ( $keyword = $request->get('keyword') ) {
            $type = $request->get('type');
            switch ($type) {
                case 'good_name':
                    $entity->where('g.good_name','like','%'. $keyword.'%');
                    break;
                case 'addressee':
                    $entity->where('od.addressee','like','%'. $keyword.'%');
                    break;
                case 'order_no':
                    $entity->where('o.order_no','like','%'. $keyword.'%');
                    break;
            }
            $map['type'] = $type;
            $map['keyword'] = $keyword;
        }
        if($status = $request->get('status')){
            $entity->where('o.status', $status);
            $map['status'] = $status;
        }
        $orderStr = 'o.create_time DESC';
        $list = $entity
            ->order($orderStr)
            ->paginate(15, false, [
                'query' => isset($map) ? $map : []
            ]);

        return $this->render('lists', [
            'list' => $list,
            'user' => (new \app\common\entity\User()),
            'allStatus' => OrderModel::getAllStatus(),
            'queryStr' => isset($map) ? http_build_query($map) : '',
        ]);
    }
    /**
     * 修改订单|查看详情
     */
    public function editOrder(Request $request)
    {
        if($request->isGet()){
            $id = $request->param('id');
            $types = $request->param('types');
            if(!OrderModel::checkExist($id)){
                return json()->data(['code' => 1, 'message' => '非法操作']);
            }
            $info = OrderModel::alias('o')
                ->leftJoin('order_detail od','o.id = od.order_id')
                ->leftJoin('goods g','o.good_id = g.id')
                ->field('o.*,
                        g.good_name,
                        g.good_pic,
                        od.addressee,
                        od.receive_phone,
                        od.receive_address,
                        od.receive_address_detail
                    ')
                ->where('o.id',$id)
                ->find();
            return $this->render('editOrder', [
                'info' => $info,
                'user' => (new \app\common\entity\User()),
                'allStatus' => OrderModel::getAllStatus(),
                'types' => $types,
            ]);
        }
        if($request->isPost()){
            $id = $request->param('id');
            $order = OrderModel::where('id',$id)->value('id');
            if(empty($order)){
                return json()->data(['code' => 1, 'message' => '订单不存在']);
            }
            $data = $request->post();

            $res = $this->validate($data, 'app\admin\validate\EditOrder');

            if (true !== $res) {
                return json()->data(['code' => 1, 'message' => $res]);
            }
            $editOrder['total'] = $data['total'];
            $editOrder['status'] = $data['status'];
            if($order['status'] != $data['status']){
                if($data['status'] == 2){
                    $editOrder['pay_time'] = time();
                }elseif ($data['status'] == 3){
                    $editOrder['send_time'] = time();
                }elseif ($data['status'] == 4){
                    $editOrder['receive_time'] = time();
                }elseif ($data['status'] == 5){
                    $editOrder['cancel_time'] = time();
                }
            }
            Db::startTrans();
            try{
                OrderModel::where('id',$id)->update($editOrder);
                OrderDetailModel::where('order_id',$id)->update([
                    'addressee' => $data['addressee'],
                    'receive_phone' => $data['receive_phone'],
                    'receive_address' => $data['receive_address'],
                    'receive_address_detail' => $data['receive_address_detail'],
                ]);
                Db::commit();
                return json(['code' => 0, 'toUrl' => url('/admin/Order/lists')]);
            }catch(\Exception $e)
            {
                Db::rollback();
                return json()->data(['code' => 1, 'message' => '修改失败']);
            }
        }

    }
    /**
     * 发货
     */
    public function sendOrder(Request $request)
    {
        $id = $request->param('id');
        $res = OrderModel::where('id',$id)->update([
            'status' => 3,
            'send_time' => time(),
        ]);
        if($res){
            return json(['code' => 0, 'toUrl' => url('/admin/Order/lists')]);
        }
        return json()->data(['code' => 1, 'message' => '发货失败']);
    }
    /**
     * 删除订单
     */
    public function deleteOrder(Request $request)
    {
        $id = $request->param('id');
        if(!OrderModel::checkExist($id)){
            return json()->data(['code' => 1, 'message' => '非法操作']);
        }
        try{
            OrderModel::where('id',$id)->delete();
            OrderDetailModel::where('order_id',$id)->delete();
            Db::commit();
            return json(['code' => 0, 'toUrl' => url('/admin/Order/lists')]);
        }catch(\Exception $e)
        {
            Db::rollback();
            return json()->data(['code' => 1, 'message' => '删除失败']);
        }
    }
    /**
     * 导出数据
     */
    public function exportUser(Request $request) {
        $page = $request->get('page')? $request->get('page'):0;
        $export = new Export();
        $entity = OrderModel::alias('o')
            ->leftJoin('order_detail od','o.id = od.order_id')
            ->leftJoin('goods g','o.good_id = g.id')
            ->leftJoin('user u','u.id = o.uid')
            ->field('o.*,g.good_name,od.addressee,u.nick_name,u.mobile');
        if ( $keyword = $request->get('keyword') ) {
            $type = $request->get('type');
            switch ($type) {
                case 'good_name':
                    $entity->where('g.good_name','like','%'. $keyword.'%');
                    break;
                case 'addressee':
                    $entity->where('od.addressee','like','%'. $keyword.'%');
                    break;
                case 'order_no':
                    $entity->where('o.order_no','like','%'. $keyword.'%');
                    break;
            }
            $map['type'] = $type;
            $map['keyword'] = $keyword;
        }
        if($status = $request->get('status')){
            $entity->where('o.status', $status);
            $map['status'] = $status;
        }
        $orderStr = 'o.create_time DESC';
        $list = $entity
            ->order($orderStr)
            ->page($page)
            ->paginate(15, false, [
                'query' => isset($map) ? $map : []
            ]);
        $allStatus = OrderModel::getAllStatus();
        foreach ($list as $v){
            $v['status_info'] =   $allStatus[$v->status];
        }
        $filename = '订单列表';
        $header = array('订单ID', '手机号', '订单号', '产品名称', '订单总价', '订单状态', '购买数量', '收件人', '下单时间');
        $index = array('id', 'mobile', 'order_no', 'good_name', 'total', 'status_info', 'num', 'addressee', 'create_time');
        $export->createtable($list, $filename, $header, $index);
    }
}
