<?php
namespace app\index\controller;

use app\common\entity\Orders;
use think\Request;

class Upload
{

    public function uploadEditor()
    {
        $uploadModel = new \app\common\service\Upload\Service('image');
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


    public function uploadImg() {
        $uploadModel = new \app\common\service\Upload\Service('file');
        if ($uploadModel->upload()) {
            $img_data = getimagesize("." . $uploadModel->fileName);
            $uploaded_type = $img_data['mime'];
            $savename = date('Ymd') . '/' . md5(microtime(true));
            if ($uploaded_type == 'image/jpeg') {
                $img = imagecreatefromjpeg("." . $uploadModel->fileName);
                imagejpeg($img, "./uploads/" . $savename . ".jpg", 100);
                $atype = ".jpg";
            } else {
                $img = imagecreatefrompng("." . $uploadModel->fileName);
                imagepng($img, "./uploads/" . $savename . ".png", 9);
                $atype = ".png";
            }
            unlink("." . $uploadModel->fileName);
            if (!file_exists("./uploads/" . $savename . $atype)) {
                return _result(false,'上传失败，请稍后再试');
//                return json([
//                    'code' => 1,
//                    'fail' => "上传失败，请稍后再试"
//                ]);
            }
            return _result(true,'success',['path'=>"/uploads/" . $savename . $atype]);

//            return json([
//                'code' => 0,
//                'data' => "/uploads/" . $savename . $atype
//            ]);
        }
        return _result(false, $uploadModel->error);

//        return json([
//            'code' => 1, 'fail' => $uploadModel->error
//        ]);

    }
    public function uploadVideo() {
        $uploadModel = new \app\common\service\Upload\Service('file');
        if ($uploadModel->upload()) {
            return json([
                'errno' => 0,
                'data' => $uploadModel->fileName
            ]);
        }
        return json([
            'errno' => 1,
            'fail ' => $uploadModel->error
        ]);
    }
}