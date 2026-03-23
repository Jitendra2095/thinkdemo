<?php

namespace app\index\model;

use think\Session;

class SendCode1
{
    public $mobile;
    public $code;
    public $type;

    public function __construct($mobile, $type)
    {
        $this->mobile = $mobile;
        $this->type = $type;
    }

    public function send()
    {
        $this->setCode();
        //调用发生接口
        if ($this->sendCode()) {
            $this->saveCode(); //保存code值到session值
            return true;
        }
        return false;
    }

    public function getSessionName()
    {
        $sessionNames = [
            'register' => 'register_code_',
            'change-password' => 'change-password_',
            'market' => 'market_sale_',
            'market_sale' => 'market_sale_ta_'
        ];

        return $sessionNames[$this->type] . $this->mobile;
    }

    private function getCode()
    {
        return Session::get($this->getSessionName());
    }

    private function setCode()
    {
        $this->code = mt_rand(100000, 999999);
    }

    private function saveCode()
    {
        Session::set($this->getSessionName(), $this->code);
    }

    // private function sendCode()
    // {
    //     $mobile = $this->mobile;
    //     $code = $this->code;
    //     header("Content-Type:text/html;charset=utf-8");
    //     $uid = 'cyl99999';
    //     $key = "d41d8cd98f00b204e980";
    //     $text = urlencode("您的验证码：{$code}");
    //     $url = "http://utf8.api.smschinese.cn/?Uid={$uid}&Key={$key}&smsMob={$mobile}&smsText={$text}";
    //     file_get_contents($url);
    //     return true;
    // }
    private function sendCode()
    {
        //初始化必填
        //填写在开发者控制台首页上的Account Sid
        $options['accountsid']='b86440d1d8d3a021ac7d0fd4df536433';
        //填写在开发者控制台首页上的Auth Token
        $options['token']='5e8f85ced909d204a879e723ff6be7fb';

        //初始化 $options必填
        $mobile = $this->mobile;
        $code = $this->code;
        
        $appid = "49826dcf4dbc4ed380f15fb93f059884"; //应用的ID，可在开发者控制台内的短信产品下查看
        $templateid = "418537";    //可在后台短信产品→选择接入的应用→短信模板-模板ID，查看该模板ID
        $param = $code; //多个参数使用英文逗号隔开（如：param=“a,b,c”），如为参数则留空
        $mobile = $mobile;
        $uid = "";

        //70字内（含70字）计一条，超过70字，按67字/条计费，超过长度短信平台将会自动分割为多条发送。分割后的多条短信将按照具体占用条数计费。

        $this->SendSms($appid,$templateid,$param,$mobile,$uid);
        return true;
    }
    public function tocurl($url, $header,$content){
        $ch = curl_init();
        if(substr($url,0,5)=='https'){
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 跳过证书检查
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);  // 从证书中检查SSL加密算法是否存在
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        $response = curl_exec($ch);
        if($error=curl_error($ch)){
            die($error);
        }
        curl_close($ch);
      //var_dump($response);
        return $response;
    }
    public function checkCode($code)
    {
        $trueCode = $this->getCode();

        if ($trueCode == $code) {
            Session::delete($this->getSessionName());
            return true;
        }

        return false;
    }
    private function getResult($url, $body = null, $method)
    {
        $data = $this->connection($url,$body,$method);
        if (isset($data) && !empty($data)) {
            $result = $data;
        } else {
            $result = '没有返回数据';
        }
        return $result;
    }

    /**
     * @param $url    请求链接
     * @param $body   post数据
     * @param $method post或get
     * @return mixed|string
     */
     
    private function connection($url, $body,$method)
    {
        if (function_exists("curl_init")) {
            $header = array(
                'Accept:application/json',
                'Content-Type:application/json;charset=utf-8',
            );
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            if($method == 'post'){
                curl_setopt($ch,CURLOPT_POST,1);
                curl_setopt($ch,CURLOPT_POSTFIELDS,$body);
            }
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $result = curl_exec($ch);
            curl_close($ch);
        } else {
            $opts = array();
            $opts['http'] = array();
            $headers = array(
                "method" => strtoupper($method),
            );
            $headers[]= 'Accept:application/json';
            $headers['header'] = array();
            $headers['header'][]= 'Content-Type:application/json;charset=utf-8';

            if(!empty($body)) {
                $headers['header'][]= 'Content-Length:'.strlen($body);
                $headers['content']= $body;
            }

            $opts['http'] = $headers;
            $result = file_get_contents($url, false, stream_context_create($opts));
        }
        return $result;
    }

    /**
    单条发送短信的function，适用于注册/找回密码/认证/操作提醒等单个用户单条短信的发送场景
     * @param $appid        应用ID
     * @param $mobile       接收短信的手机号码
     * @param $templateid   短信模板，可在后台短信产品→选择接入的应用→短信模板-模板ID，查看该模板ID
     * @param null $param   变量参数，多个参数使用英文逗号隔开（如：param=“a,b,c”）
     * @param $uid          用于贵司标识短信的参数，按需选填。
     * @return mixed|string 
     * @throws Exception
     */
    public function SendSms($appid,$templateid,$param=null,$mobile,$uid){
        $url = 'https://open.ucpaas.com/ol/sms/' . 'sendsms';
        $body_json = array(
            'sid'=>'b86440d1d8d3a021ac7d0fd4df536433',
            'token'=>'5e8f85ced909d204a879e723ff6be7fb',
            'appid'=>$appid,
            'templateid'=>$templateid,
            'param'=>$param,
            'mobile'=>$mobile,
            'uid'=>$uid,
        );
        $body = json_encode($body_json);
        $data = $this->getResult($url, $body,'post');
        return $data;
    }
    
     /**
     群发送短信的function，适用于运营/告警/批量通知等多用户的发送场景
     * @param $appid        应用ID
     * @param $mobileList   接收短信的手机号码，多个号码将用英文逗号隔开，如“18088888888,15055555555,13100000000”
     * @param $templateid   短信模板，可在后台短信产品→选择接入的应用→短信模板-模板ID，查看该模板ID
     * @param null $param   变量参数，多个参数使用英文逗号隔开（如：param=“a,b,c”）
     * @param $uid          用于贵司标识短信的参数，按需选填。
     * @return mixed|string 
     * @throws Exception
     */
    public function SendSms_Batch($appid,$templateid,$param=null,$mobileList,$uid){
        $url = 'https://open.ucpaas.com/ol/sms/' . 'sendsms_batch';
        $body_json = array(
            'sid'=>'b86440d1d8d3a021ac7d0fd4df536433',
            'token'=>'5e8f85ced909d204a879e723ff6be7fb',
            'appid'=>$appid,
            'templateid'=>$templateid,
            'param'=>$param,
            'mobile'=>$mobileList,
            'uid'=>$uid,
        );
        $body = json_encode($body_json);
        $data = $this->getResult($url, $body,'post');
        return $data;
    }
    // public function checkCode($code)
    // {
    //     $trueCode = $this->getCode();

    //     if ($trueCode == $code) {
    //         Session::delete($this->getSessionName());
    //         return true;
    //     }

    //     return false;
    // }

}