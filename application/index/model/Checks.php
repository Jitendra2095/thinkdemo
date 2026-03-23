<?php
namespace app\index\model;

use think\Db;
use app\common\entity\User;
use app\common\entity\UserMagicLog;
use app\index\controller\Check;
use service\GoogleAuthenticator;


class Checks
{

	
	
	// protected $type = [
 //        'balance'    =>  'float',
 //    ];

    public static function getmagic(){
    	
	        
	    	$user = User::field('trade_address,id')->select();
	    	// echo '<pre>';
	    	// print_r($user);
// 	    	echo date('Y-m-d H:i:s',1538277450);
// echo date('Y-m-d H:i:s',1538277442);
// exit;
	    	foreach ($user as $key => $row) {
	    		// echo $key;
	    		if($row['trade_address'] == NULL || $row['trade_address'] == "" ){
	    			continue;
	    		}
	    		// echo $row['trade_address'];
	    		$url = 'http://47.52.129.217/index/transfer';
	    		// $url = 'http://lll.weixqq4.top/web3/index/GetBalance?address=0x73a473fef74813f2f3136f08d236e54f47fe8eef';
		        
	    		$post_data = array(
	    			'address' => $row['trade_address'],
	    			);
		        $ch = curl_init();
		        curl_setopt($ch, CURLOPT_URL, $url);
		        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		        curl_setopt($ch, CURLOPT_POST, 1);
		        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
		        $arr = curl_exec($ch); // 已经获取到内容，没有输出到页面上。
		        curl_close($ch);
		        echo '<pre>';
		        print_r($arr);
		        echo '<pre>';
		        // echo gettype($arr);
		        $content = json_decode($arr,true);
		        // echo '<pre>';
		        // print_r($content);
		        // echo '<pre>';
		        // echo gettype($content);

		        if($content['code'] == 1){

		        	Db::table('magic_log_list1')->insert(['fromaddress' => $content['fromaddress'] , 'toaddress' => $content['address'] , 'val' => $content['balance'] , 'hash' => $content['transactionHash'] , 'user_id' => $row['id']]);

		        }else{

		        	continue;

		        }

	    	}
	    	
	}    	
	/**
     * 验证谷歌验证码
     * $oneCode 验证码
     */
    public function check_google($oneCode,$userid)
    {
        $ga = new GoogleAuthenticator();
        $secret = User::where('id',$userid)->value('SecretKey');
        $checkResult = $ga->verifyCode($secret, $oneCode, 2);
        if ($checkResult) {
            return true;
        } else {
            return false;
        }
    }




}