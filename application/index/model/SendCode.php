<?php

namespace app\index\model;

use service\AlismsService;
use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;
use think\Session;

class SendCode
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
            'market_sale' => 'market_sale_ta_',
            'buy_sell'=>'buy_sell_code',
            'forgetpassword'=>'forgetpassword_'
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

    private function sendCode1()
    {

        $restult = $this->sendAliDaYuAuthCode($this->mobile,$this->code);
        if($restult == 'OK'){
            return true;
        }else{
            return $restult;
        }
    }
    private function sendCode()
    {
        $statusStr = array(
        "0" => "短信发送成功",
        "-1" => "参数不全",
        "-2" => "服务器空间不支持,请确认支持curl或者fsocket，联系您的空间商解决或者更换空间！",
        "30" => "密码错误",
        "40" => "账号不存在",
        "41" => "余额不足",
        "42" => "帐户已过期",
        "43" => "IP地址限制",
        "50" => "内容含有敏感词"
        );
        $smsapi = "http://api.smsbao.com/";
        $user = "18060072822"; //短信平台帐号
        $pass = md5("6458643736AAAAA"); //短信平台密码
        $content='【熊猫电竞】您的验证码为:'.$this->code.',在5分钟内有效！如非本人操作请忽视本条信息。';//要发送的短信内容
        $phone = $this->mobile;//要发送短信的手机号码
        $sendurl = $smsapi."sms?u=".$user."&p=".$pass."&m=".$phone."&c=".urlencode($content);
        $result =file_get_contents($sendurl) ;
        if ($statusStr[$result]==0) {
            return true;
        }else{
            return $statusStr[$result];
        }
        /*
        $sms_setting = [
            'userid' => '66520',
            'account' => '20201226',
            'password' => 'ad4560',
        ];

        $body=array(
            'action'=>'send',
            'userid'=>$sms_setting['userid'],
            'account'=>$sms_setting['account'],
            'password'=>$sms_setting['password'],
            'mobile'=>$this->mobile,
            'content'=>'【熊猫电竞】您的验证码:'.$this->code.',如非本人操作请忽视本条信息。',
        );
        $ch=curl_init();
        curl_setopt($ch, CURLOPT_URL,"https://dx.ipyy.net/smsJson.aspx");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        $result = curl_exec($ch);
        curl_close($ch);
        $result_data = json_decode($result, true);
//        dump($result_data);
//        die;
        if (isset($result_data['returnstatus']) && $result_data['returnstatus'] === 'Success') {
            return true;
        }else{
            return $result_data;
        }*/
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
     * 集成方法：阿里云(原大鱼)发送短信验证码
     * @param string $phoneNumber 目标手机号
     * TODO 注意 accessKeyId、accessSecret、signName、templateCode 重要参数的获取配置
     */
    public function sendAliDaYuAuthCode($phoneNumber = '151xxxxxxx3',$authCodeMT)
    {
        $accessKeyId = config('aliyun_oss.access_key');
        $accessSecret = config('aliyun_oss.secret_key'); //注意不要有空格
        $signName = '友爱集团'; //配置签名
        $templateCode = 'SMS_200815024';//配置短信模板编号
        //TODO 随机生成一个6位数
//        $authCodeMT = mt_rand(100000,999999);
        //TODO 短信模板变量替换JSON串,友情提示:如果JSON中需要带换行符,请参照标准的JSON协议。
        $jsonTemplateParam = json_encode(['code'=>$authCodeMT]);

        AlibabaCloud::accessKeyClient($accessKeyId, $accessSecret)
            ->regionId('cn-hangzhou')
            ->asGlobalClient();
        try {
            $result = AlibabaCloud::rpcRequest()
                ->product('Dysmsapi')
                // ->scheme('https') // https | http
                ->version('2017-05-25')
                ->action('SendSms')
                ->method('POST')
                ->options([
                    'query' => [
                        'RegionId' => 'cn-hangzhou',
                        'PhoneNumbers' => $phoneNumber,//目标手机号
                        'SignName' => $signName,
                        'TemplateCode' => $templateCode,
                        'TemplateParam' => $jsonTemplateParam,
                    ],
                ])
                ->request();
            $opRes = $result->toArray();
//            print_r($opRes);
            if ($opRes && $opRes['Code'] == "OK"){
                //进行Cookie保存
                return $opRes['Code'];
            }else{
                return $opRes['Message'];
            }
        } catch (ClientException $e) {
            return $e->getErrorMessage() . PHP_EOL;
        } catch (ServerException $e) {
            return $e->getErrorMessage() . PHP_EOL;
        }
    }

}