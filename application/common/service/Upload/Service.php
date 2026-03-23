<?php
namespace app\common\service\Upload;

class Service
{
    public $name;

    protected $validate = [
        'ext' => 'jpg,png,gif,jpeg,mp4,rmvb,flv,avi'
    ];

    public $fileName;

    public $error;

    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * 单文件上传
     */
    public function upload()
    {
        $file = request()->file($this->name);
        $info = $file->validate($this->validate)->move('uploads');
        $a = str_replace('\\', '/', $info->getSaveName());
//        print_r();
        if ($info) {
            $this->fileName = '/uploads/' . $a;
            return true;
        } else {
            $this->error = $file->getError();
            return false;
        }
    }

    /**
     * 多文件上传
     */
    public function uploadAll()
    {

    }
}
