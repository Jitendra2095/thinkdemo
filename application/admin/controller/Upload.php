<?php

namespace app\admin\controller;

use service\LogService;

class Upload extends Admin {

    public function uploadEditor() {
        $uploadModel = new \app\common\service\Upload\Service('image');
        if ($uploadModel->upload()) {
            return json([
                'errno' => 0,
                'data' => ['http://'.$_SERVER['HTTP_HOST'].$uploadModel->fileName]
            ]);
        }
        return json([
            'errno' => 1,
            'fail ' => $uploadModel->error
        ]);
    }

    public function uploadImg() {
        $uploadModel = new \app\common\service\Upload\Service('file');
        if ($uploadModel->upload()) {
            return json([
                'errno' => 0,
                'data' => [$uploadModel->fileName]
            ]);
        }
        LogService::write('文件管理|上传文件','上传图片：<img src="'.$uploadModel->fileName.'">');
        return json([
            'errno' => 1,
            'fail ' => $uploadModel->error
        ]);
    }
    public function uploadVideo() {
        $uploadModel = new \app\common\service\Upload\Service('file');
        if ($uploadModel->upload()) {
            return json([
                'errno' => 0,
                'data' => [$uploadModel->fileName]
            ]);
        }
        return json([
            'errno' => 1,
            'fail ' => $uploadModel->error
        ]);
    }

}
