<?php

namespace app\admin\controller;

use app\admin\exception\AdminException;
use app\common\entity\Export;
use app\common\entity\ServiceInfo;
use think\Db;
use think\Request;
use app\common\entity\User;

class Article extends Admin
{

    #内容管理|图片列表
    public function image(){
        $list = Db::table('image')->select();
        return $this->render('imagelist',[
            'list' => $list
        ]);
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
        $content = $request->post('content');

        $data = [
            'pic' => $photo,
            'title' => $title,
            'content' => $content,
            'sort' => $sort,
            'create_time' => time()
        ];

        $insphoto = Db::table('image')->insert($data);

        if ($insphoto){

            return json(['code' => 0, 'message' => '添加成功','toUrl' => url('article/image')]);

        }

        return json(['code' => 1, 'message' => '添加失败']);

    }
    #内容管理|图片编辑
    public function imageedit(Request $request){
        $id = $request->param('id');
        $list = Db::table('image')->where('id',$id)->find();
        return $this->render('imageedit',[
            'info' => $list
        ]);
    }

    #图片修改
    public function updimage(Request $request)
    {
        $id = $request->param('id');
        $title = $request->post('title');
        $content = $request->post('content');
        $photo = $request->post('photo');
        $sort = $request->post('sort');

        $data = [
            'pic' => $photo,
            'sort' => $sort,
            'content' => $content,
            'title' => $title,
            'update_time' => time()
        ];

        $updphoto = Db::table('image')->where('id',$id)->update($data);
        if ($updphoto){

            return json(['code' => 0, 'message' => '修改成功','toUrl'=>url('article/image')]);

        }

        return json(['code' => 1, 'message' => '修改失败']);

    }
    #图片删除
    public function imagedel(Request $request){

        $uid = $request->param('id');

        $del = Db::table('image')->where('id',$uid)->delete();

        if ($del){

            return json(['code' => 0, 'message' => '删除成功']);

        }

        return json(['code' => 1, 'message' => '删除失败']);

    }
}
