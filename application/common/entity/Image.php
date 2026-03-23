<?php
namespace app\common\entity;

use think\Model;

class Image extends Model
{


    /**
     * @var string 对应的数据表名
     */
    protected $table = 'image';

    protected $autoWriteTimestamp = false;


}