<?php

namespace app\index\controller;
use think\Image;

class Img extends Base
{
    //图片上传接口
    public function apiUploadImg()
    {
        $image = Image::open(request()->file('img'));
        $dir = date('Ymd');
        if (!is_dir(IMG . $dir)) {
            mkdir(IMG . $dir, 0777, true);  //创建多级目录
        }
        $file_name = autoOrder('IMG') . '.png';
        $save_path = IMG . $dir .'/'. $file_name;
        $return_path = IMG_DIR . $dir .'/'. $file_name;

        if ($image->save($save_path)) {
            return autoJson(0, ['path'=>$return_path]);
        }
        return autoJson(-1, '', '上传失败');
    }
    //图片压缩上传
    public function upload()
    {
        $file = $this->request->file('file');
        // 移动路径
        $upload_path = 'uploads/';
        //缩略保存路径
        $save_path = 'uploads/';
        //移动至  $upload_path
        $info = $file->validate(['ext' => 'jpg,gif,png,bmp,jpeg,JPG'])->move($upload_path);

        //打开移动的图片
        $image = Image::open($save_path.$info->getSaveName());

        //压缩图片
        $image->thumb(200, 200)->save($save_path.$info->getSaveName());

        if($info){

            $result['code'] = 1;
            $result['info'] = '图片上传成功!';
            $path=str_replace('\\','/',$info->getSaveName());
            //保存到数据库路径
            $result['url'] = '/uploads/'. $path;
            return json($result);
        }else{
            // 上传失败获取错误信息
            $result['code'] =0;
            $result['info'] = $file->getError();
            $result['url'] = '';
            return json($result);
        }
    }
    // 多图上传接口
    public function multi()
    {
        // 获取表单上传文件
        $dir = date('Ymd');
        if (!is_dir(IMG . $dir)) {
            mkdir(IMG . $dir, 0777, true);  //创建多级目录
        }
        $arr = [];

        $files = request()->file('img');

        if (count($files) >9) {
            return autoError('最多上传9张图片');
        }

        foreach($files as $file){

            $image = Image::open($file);
            $file_name = autoOrder('IMG') . '.png';
            $save_path = IMG . $dir .'/'. $file_name;
            $return_path = IMG_DIR . $dir .'/'. $file_name;

            if ($image->save($save_path)) {
                $arr[] =  $return_path;
            }
        }

        return autoJson(0, ['path'=>$arr]);
    }
    /**
     * 视频上传
     */
    public function video()
    {
        $video = request()->file('video');
        if ($info = $video->move(VIDEO)) {
            $file_name = $info->getSaveName();
            $return_path = VIDEO_DIR . DIRECTORY_SEPARATOR . $file_name;
            return autoJson(0, ['path'=>API . $return_path]);

        }
        return autoJson(-1, '', '上传失败');
    }

}