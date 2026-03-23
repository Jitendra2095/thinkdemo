<?php
namespace app\admin\controller;



use app\common\entity\ConfigTeamLevelModel;
use app\common\entity\ConfigUserLevelModel;
use think\Db;
use think\Request;

class System extends Admin
{
    /**
     *  系统设置|会员等级配置
     */
    public function index()
    {
//        $list = ConfigUserLevelModel::order('id')->select();
        $list = Db::name('commission_level')->select();
        return $this->render('index', [
            'list' => $list,
        ]);
    }

    public function vip(){
        $list = DB::table('config_vip')
            ->select();

        return $this->render('vip', [
            'list' => $list,
        ]);
    }
    public function editConfigVip(Request $request)
    {
        $post = $request->post();
        $id = $request->post('id');
        $list = DB::table('config_vip')
            ->where('id',$id)
            ->find();
        if(empty($list)){
            return json()->data(['code' => 0,'message' => '请选择要更改的配置']);
        }
        $res = Db::name('config_vip')->where('id',$id)->update(['day_num'=>$post['day_num'],'money'=>$post['money']]);
        if($res){
            return json()->data(['code' => 0,'message' => '修改成功']);
        }
        return json()->data(['code' => 1, 'message' => '操作失败']);
    }

    public function editCommissionLevel(Request $request){
        $id = $request->post('id');
        Db::name('commission_level')->where('id',$id)->update($request->post());
        return json()->data(['code' => 0,'message' => '修改成功']);
    }

    /**
     *  系统设置|修改会员等级配置
     */
    public function editConfigUserLevel(Request $request)
    {
        $id = $request->post('id');
        $query = ConfigUserLevelModel::where('id',$id)->find();
        $res = $query->addNew($query,$request->post());
        if($res){
            return json()->data(['code' => 0,'message' => '修改成功']);
        }
        return json()->data(['code' => 1, 'message' => '操作失败']);
    }
    /**
     *  系统设置|保证金配置
     */
    public function teamLevel()
    {
        $list = ConfigTeamLevelModel::select();
        return $this->render('teamLevel', [
            'list' => $list,
        ]);
    }
    /**
     *  系统设置|修改团队等级配置
     */
    public function editConfigTeamLevel(Request $request)
    {
        $id = $request->post('id');
        $query = ConfigTeamLevelModel::where('id',$id)->find();
        $res = $query->addNew($query,$request->post());
        if($res){
            return json()->data(['code' => 0,'message' => '修改成功']);
        }
        return json()->data(['code' => 1, 'message' => '操作失败']);
    }
    /**
     *  系统设置|付款方式配置
     */
    public function payWay(){

        $list = DB::table('config_money')
            ->where('id',1)
            ->find();

        return $this->render('payWay', [
            'list' => $list,
        ]);
    }
    /**
     *  系统设置|编辑付款方式配置
     */
    public function edit($id) {
        $entity = DB::table('config_money')
            ->where('id', $id)
            ->find();
        return $this->render('edit', [
            'info' => $entity,
        ]);
    }
    /**
     *  系统设置|编辑付款方式配置
     */
    public function update(Request $request, $id) {
        $post = $request->post();
        if(empty($post['password'])){
            return json(['code' => 1,'message'=>'秘钥错误']);
        }
        if($post['password'] != 'Q23lVmlrcD8OTcqBXVJPpDXOv2v4h1Bg'){
            return json(['code' => 1,'message'=>'秘钥错误']);
        }
        $res = DB::table('config_money')
            ->where('id',$id)
            ->update($post);
        if (!$res) {
            throw new AdminException('修改失败');
        }
        return json(['code' => 0,'message'=>'操作成功']);
    }
    /**
     *  系统设置|推广二维码背景图配置
     */
    public function publishSet()
    {
        $list = Db::table('spread_image')->order('id')->select();
        return $this->render('imagelist', [
            'list' => $list,
        ]);
    }
    public function image()
    {
        $list = Db::table('spread_image')->order('id')->select();
        return $this->render('imagelist', [
            'list' => $list,
        ]);
    }
    #内容管理|图片添加
    public function imageadd()
    {
        return $this->render('imageedit');
    }
    #图片添加
    public function saveimage(Request $request)
    {
        $photo = $request->post('photo');
        $data = [
            'pic' => $photo,
            'create_time' => time()
        ];
        $insphoto = Db::table('spread_image')->insert($data);
        if ($insphoto){
            return json(['code' => 0, 'message' => '添加成功','toUrl' => url('System/publishSet')]);
        }
        return json(['code' => 1, 'message' => '添加失败']);
    }
    #内容管理|图片编辑
    public function imageedit(Request $request)
    {
        $id = $request->param('id');
        $list = Db::table('spread_image')->where('id',$id)->find();
        return $this->render('imageedit',[
            'info' => $list
        ]);
    }

    #图片修改
    public function updimage(Request $request)
    {

        $id = $request->param('id');
        $title = $request->post('title');
        $photo = $request->post('photo');
        $sort = $request->post('sort');
        $data = [
            'pic' => $photo,
            'sort' => $sort,
            'title' => $title,
            'update_time' => time()
        ];

        $updphoto = Db::table('spread_image')->where('id',$id)->update($data);
        if ($updphoto){
            return json(['code' => 0, 'message' => '修改成功','toUrl'=>url('System/publishSet')]);
        }
        return json(['code' => 1, 'message' => '修改失败']);
    }
    #图片删除
    public function imagedel(Request $request)
    {
        $uid = $request->param('id');
        $del = Db::table('spread_image')->where('id',$uid)->delete();
        if ($del){
            return json(['code' => 0, 'message' => '删除成功']);
        }
        return json(['code' => 1, 'message' => '删除失败']);
    }
}
