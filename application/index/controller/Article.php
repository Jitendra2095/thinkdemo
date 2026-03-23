<?php

namespace app\index\controller;

use app\common\entity\Article as ArticleModel;
use app\common\entity\WalletRatio;
use think\Image;
use think\Request;

class Article extends Base
{
    /**
     * 公告
     */
    public function articleList(Request $request)
    {
        $limit = $request->get('limit') ? $request->get('limit') : 15;
        $page = $request->get('page') ? $request->get('page') : 1;
        $category = $request->get('category') ? $request->get('category') : 1;
        $article = new ArticleModel;
        $list = $article
            ->where('status', 1)
            ->where('category', $category)
            ->order('sort')
            ->page($page)
            ->limit($limit)
            ->paginate();
        if ($list) {
            return json(['code' => 0, 'msg' => '获取成功', 'info' => $list]);
        }
        return json(['code' => 1, 'msg' => '获取失败']);
    }

    /**
     * 公告详情
     */
    public function articleDetail(Request $request)
    {
        $article_id = $request->post('article_id');
        if (!$article_id) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }
        $article = new \app\common\entity\Article();
        $list = $article->getDetail($article_id);
        if ($list) {
            return json(['code' => 0, 'msg' => '获取成功', 'info' => $list]);
        }
        return json(['code' => 1, 'msg' => '获取失败']);
    }

    /**
     * 项目说明
     */
    public function projectDetail()
    {
        $info = ArticleModel::where('category', 3)->where('status', 1)->find();
        return json(['code' => 0, 'msg' => '获取成功', 'info' => $info]);
    }

    /**
     * 轮播图
     */
    public function image(Request $request)
    {
        $limit = $request->get('limit') ? $request->get('limit') : 15;
        $page = $request->get('page') ? $request->get('page') : 1;
        $list = \app\common\entity\Image::field('')
            ->page($page)
            ->limit($limit)
            ->paginate();
        return json()->data(['code' => 0, 'msg' => '请求成功', 'info' => $list]);
    }

    /**
     * 首页行情
     */
    public function indexRatio()
    {
        $list = WalletRatio::select();
        if ($list) {
            return json(['code' => 0, 'msg' => '获取成功', 'info' => $list]);
        }
        return json(['code' => 1, 'msg' => '获取失败']);
    }

}