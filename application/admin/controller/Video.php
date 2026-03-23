<?php

namespace app\admin\controller;

use app\admin\exception\AdminException;
use app\common\entity\Export;
use think\Db;
use think\Request;
use app\common\entity\User;

class Video extends Admin {

    #|绑定帐号步骤
    public function index(){
        $list = Db::table('video_list')->select();
        return $this->render('index',[
            'list' => $list
        ]);
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


    #内容管理|图片编辑
    public function edit(Request $request){
        $id = $request->param('id');
        $list = Db::table('video_list')->where('id',$id)->find();

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
        $url = $request->post('url');
        $data = [
            'image' => $photo,
            'url' => $url,
            'title' => $title,
        ];

        $updphoto = Db::table('video_list')->where('id',$id)->update($data);
        if ($updphoto){

            return json(['code' => 0, 'message' => '修改成功','toUrl'=>url('index')]);

        }

        return json(['code' => 1, 'message' => '修改失败']);

    }

    #内容管理|图片添加
    public function add(){
        return $this->render('imageedit');
    }

    #图片添加
    public function saveimage(Request $request){

        $image = $request->post('photo');
        $title = $request->post('title');
        $url = $request->post('url');

        $data = [
            'image' => $image,
            'title' => $title,
            'url' => $url,
            'hit'=>1,
            'create_time' => time()
        ];

        $insphoto = Db::table('video_list')->insert($data);

        if ($insphoto){

            return json(['code' => 0, 'message' => '添加成功','toUrl' => url('index')]);

        }

        return json(['code' => 1, 'message' => '添加失败']);

    }

    #图片删除
    public function del(Request $request){

        $uid = $request->param('id');

        $del = Db::table('video_list')->where('id',$uid)->delete();

        if ($del){

            return json(['code' => 0, 'message' => '删除成功']);

        }

        return json(['code' => 1, 'message' => '删除失败']);

    }



}
