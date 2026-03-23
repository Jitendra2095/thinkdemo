<?php

namespace app\admin\controller;

use app\admin\exception\AdminException;
use app\common\entity\Export;
use think\Db;
use think\Request;
use think\Route;
use app\common\entity\User;

class Roomclassify extends Admin {

    /**
     * @power 资讯管理|资讯列表
     * @rank 5
     */
    public function index(Request $request) {
        $route = $request->route();
        $render = $route['render'];
//        $entity = \app\common\entity\Article::field('*');

//        if ($cate = $request->get('type')) {
//            $entity->where('category', $cate);
//            $map['cate'] = $cate;
//        }
        $optype = $this->getOptype($render);
        $list = Db::name('roomclassify')->where(['op_type'=>$optype,'deleted'=>0])->order('op_type desc,id desc')->paginate(10);
        $items = $list->items();
        foreach ($items as $key=>&$item){
            $item['parent_name'] = Db::name('roomclassify')->where('id',$item['parent_id'])->value('title');
        }
        unset($item);

        return $this->render('index', [
            'list' => $list,
            'items' => $items,
            'render' => $render,
        ]);
    }


    /**
     * @power 内容管理|文章管理@添加文章
     */
    public function create(Request $request) {
        $route = $request->route();
        $render = $route['render'];
        $optype = $this->getOptype($render);
        $parent_optype = $optype - 1;
        if($parent_optype < 0){
            $parent_optype = 1;
        }
        $parent_class = Db::name('roomclassify')->where(['op_type'=>$parent_optype,'deleted'=>0])->field('id,op_type,title')->select();
        return $this->render('edit', [
            'cate' => \app\common\entity\Article::getAllCate(),
            'render' => $render,
            'parent_class' => $parent_class
        ]);
    }

    /**
     * @power 内容管理|文章管理@编辑文章
     */
    public function edit($id) {
        $request = \request();
        $route = $request->route();
        $render = $route['render'];
        $info = Db::name('roomclassify')->where('id',$id)->find();
        $optype = $this->getOptype($render);
        $parent_optype = $optype - 1;
        if($parent_optype < 0){
            $parent_optype = 1;
        }
        $parent_class = Db::name('roomclassify')->where(['op_type'=>$parent_optype,'deleted'=>0])->field('id,op_type,title')->select();

        return $this->render('edit', [
            'render' => $render,
            'info' => $info,
            'parent_class' => $parent_class,
        ]);
    }

    /**
     * @power 内容管理|文章管理@添加文章
     */
    public function save(Request $request) {
        $res = $this->validate($request->post(), 'app\admin\validate\Roomclassify');

        if (true !== $res) {
            return json()->data(['code' => 1, 'message' => $res]);
        }
        $post = $request->post();
        $optype = $this->getOptype($post['render']);
        if($optype == 2){
            if(empty($post['parent_id'])){
                return json(['code' => 1,'message'=>'请选择父类']);
            }
        }
        empty($post['parent_id']) ? $parent_id = 0 : $parent_id = $post['parent_id'];
        $data = [
            'title'=>$post['title'],
            'displayorder'=>$post['displayorder'],
            'status'=>$post['status'],
            'op_type'=>$optype,
            'image'=>isset($post['image']) ? $post['image'] : "",
            'parent_id'=>$parent_id,
            'vip_type'=>$post['vip_type'],
            'createtime'=>time(),
        ];
        $result = Db::table('roomclassify')->insert($data);
        if (!$result) {
            throw new AdminException('保存失败');
        }
        //添加用户提醒
//        if($request->post('category')==1 && $request->post('status')==1){
//            User::update(['roomclassify'=>1],['roomclassify' => 0]);
//        }
        return json(['code' => 0, 'toUrl' => url('/admin/roomclassify/index',['render'=>$post['render']])]);
    }

    /**
     * @power 内容管理|文章管理@编辑文章
     */
    public function update(Request $request, $id) {
        $res = $this->validate($request->post(), 'app\admin\validate\Roomclassify');
        if (true !== $res) {
            return json()->data(['code' => 1, 'message' => $res]);
        }
        $entity = $this->checkInfo($id);
        if(empty($entity)){
            return json(['code' => 1,'message'=>'该分类不存在']);
        }
        $post = $request->post();
        $optype = $this->getOptype($post['render']);
        if($optype == 2){
            if(empty($post['parent_id'])){
                return json(['code' => 1,'message'=>'请选择父类']);
            }
        }
        empty($post['parent_id']) ? $parent_id = 0 : $parent_id = $post['parent_id'];
        $data = [
            'title'=>$post['title'],
            'displayorder'=>$post['displayorder'],
            'status'=>$post['status'],
            'op_type'=>$optype,
            'image'=>isset($post['image']) ? $post['image'] : "",
            'vip_type'=>$post['vip_type'],
            'parent_id'=>$parent_id,
        ];
        $result = Db::table('roomclassify')->where('id',$id)->update($data);

        return json(['code' => 0, 'toUrl' => url('/admin/roomclassify/index',['render'=>$post['render']])]);
    }

    /**
     * 导出留言
     */
    public function exportMessage(Request $request) {
        $export = new Export();
        $entity = \app\common\entity\Message::field('m.*,u.mobile, u.nick_name')->alias('m');
        if ($keyword = $request->get('keyword')) {
            $type = $request->get('type');
            switch ($type) {
                case 'mobile':
                    $entity->where('u.mobile', $keyword);
                    break;
                case 'nick_name':
                    $entity->where('u.nick_name', $keyword);
                    break;
            }
            $map['type'] = $type;
            $map['keyword'] = $keyword;
        }
        $list = $entity->leftJoin("user u", 'm.user_id = u.id')
            ->order('m.create_time', 'desc')
            ->select();
        $filename = '留言列表';
        $header = array('会员昵称', '会员账号', '内容', '提交时间');
        $index = array('nick_name', 'mobile', 'content', 'create_time');
        $export->createtable($list, $filename, $header, $index);
    }

    /**
     * @power 内容管理|文章管理@删除文章
     */
    public function delete(Request $request, $id) {
        $entity = $this->checkInfo($id);

        $result = Db::name('roomclassify')->where('id',$id)->update(['deleted'=>1]);
        if(!$result){
            throw new AdminException('删除失败');
        }

        return json(['code' => 0, 'message' => 'success']);
    }

    private function checkInfo($id) {
        $entity = Db::name('roomclassify')->where('id',$id)->find();
        if (!$entity) {
            throw new AdminException('对象不存在');
        }

        return $entity;
    }

    /**
     * 视频添加
     */
    public function videoadd()
    {
        $info = Video::find();
        return $this->render('videoadd',[
            'info' => $info,
        ]);
    }
    /**
     * 视频保存
     */
    public function videoSave(Request $request)
    {
        $photo = $request->post('photo');
        $add_data = [
            'src' => $photo,
            'create_time' => time(),
        ];
        if(!$photo) return json(['code' => 1, 'message' => '请选择视频']);
        $list = Video::select();
        foreach ($list as $v){

            if( file_exists('.'.$v['src'])){
                unlink('.'.$v['src']);
            }
            Video::where('id',$v['id'])->delete();
        }

        $res = Video::insert($add_data);
        if($res){
            return json(['code' => 0, 'message' => '添加成功']);
        }
        return json(['code' => 1, 'message' => '添加失败']);

    }
    #内容管理|图片列表
    public function image(){
        $list = Db::table('spread_image')->select();
        return $this->render('imagelist',[
            'list' => $list
        ]);
    }

    #内容管理|图片编辑
    public function imageedit(Request $request){
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

            return json(['code' => 0, 'message' => '修改成功','toUrl'=>url('image')]);

        }

        return json(['code' => 1, 'message' => '修改失败']);

    }

    #内容管理|图片添加
    public function imageadd(){
        return $this->render('imageedit');
    }

    #图片添加
    public function saveimage(Request $request){

        $photo = $request->post('photo');
        $title = $request->post('title');
        $sort = $request->post('sort');

        $data = [
            'pic' => $photo,
            'title' => $title,
            'sort' => $sort,
            'create_time' => time()
        ];

        $insphoto = Db::table('spread_image')->insert($data);

        if ($insphoto){

            return json(['code' => 0, 'message' => '添加成功','toUrl' => url('image')]);

        }

        return json(['code' => 1, 'message' => '添加失败']);

    }

    #图片删除
    public function imagedel(Request $request){

        $uid = $request->param('id');

        $del = Db::table('spread_image')->where('id',$uid)->delete();

        if ($del){

            return json(['code' => 0, 'message' => '删除成功']);

        }

        return json(['code' => 1, 'message' => '删除失败']);

    }

    public function getOptype($render){
        $optype = 0;
        switch ($render){
            case "game":
                $optype = 1;
                break;
            case "match":
                $optype = 2;
                break;
            case "mobile":
                $optype = 3;
                break;
            case "roomgame":
                $optype = 4;
                break;
        }
        return $optype;
    }



}
