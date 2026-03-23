<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

// [ 应用入口文件 ]

// 定义应用目录
define('APP_PATH', __DIR__ . '/../application/');
define('ROOT_URL', 'http://' . $_SERVER['SERVER_NAME']);
define('UPLOADS', __DIR__.'/uploads/');
define('IMG',   __DIR__.'/uploads/img/');
define('IMG_DIR', '/uploads/img/');

define('VIDEO',   __DIR__.'/uploads/video/');
define('VIDEO_DIR',   '/uploads/video/');

define('LOG',  __DIR__.'/logs/');
define('API',  '/app/');
define('ADMIN_C', __DIR__ . '/application/admin/controller/');
define('ADMIN_V', __DIR__ . '/application/admin/view/');
define('MODEL_C', __DIR__ . '/application/common/model/');
define('INDEX_C', __DIR__ . '/application/index/controller/');
ini_set('opcache.revalidate_freq',0);

// 加载框架引导文件
require __DIR__ . '/../thinkphp/start.php';
