<?php

use Royalcms\Component\Support\Arr;
use Royalcms\Component\Sms\Sms;
use Royalcms\Component\Sms\Contracts\SmsAgent;

class EcjiaSmsAgent extends Sms implements SmsAgent
{
    
    const SEND      = 'sms/send';
    const BALANCE   = 'sms/balance';
    
    private $appKey;
    private $appSecret;
    
    public function __construct($config)
    {
        $this->config = $config;
        $this->transformConfig();
    }
    
    public function transformConfig()
    {
        $credentials = Arr::pull($this->config, 'credentials');
        $this->appKey = Arr::pull($credentials, 'appKey');
        $this->appSecret = Arr::pull($credentials, 'appSecret');
    }
    
    protected function authParams()
    {
        return [
            'app_key'   => $this->appKey,
            'app_secret'  => $this->appSecret,
        ];
    }
    
    /**
     * 发送信息
     * 
     * @see \Royalcms\Component\Sms\Contracts\SmsAgent::send()
     */
    public function send($mobile)
    {
        $requestParams = array(
            'content' => $this->content,
            'mobile' => $mobile,
        );

        $requestParams = array_merge($this->authParams(), $requestParams);

        $cloud = ecjia_cloud::instance()->api(self::SEND)->data($requestParams)->run();

        $result = $this->transformerResponse($cloud);

        return $result;
    }
    
    /**
     * 查询账户余额
     */
    public function balance()
    {
        $requestParams = $this->authParams();
        
        $cloud = ecjia_cloud::instance()->api(self::BALANCE)->data($requestParams)->run();

        if ($cloud->getStatus() == ecjia_cloud::STATUS_ERROR) {
            return $cloud->getError();
        }
        
        $result = $cloud->getReturnData();
        
        return array('data' => array(
        	'num' => $result['balance']
        ));
    }
    
    /**
     * 转换返回的信息处理
     * @param ecjia_cloud $cloud
     * @return array $result
     * @return int $result[].code 返回0则成功，返回其它则错误
     * @return string $result[].msg 返回消息
     * @return string $result[].raw 接口返回的原生信息
     * @return array $result[].data 数据信息
     */
    public function transformerResponse($cloud)
    {
        $data = array();
        
        if (is_ecjia_error($cloud->getError())) {
            $data['msgid'] = '0';
            $code = $cloud->getError()->get_error_code();
            $description = $cloud->getError()->get_error_message();
            $raw = '';
        }
        else {
            $data['msgid'] = array_get($cloud->getReturnData(), 'msgid');
            $code = 0;
            $description = 'OK';
            $raw = array_get($cloud->getResponse(), 'body');
        }

        $result = [
        	'raw' => $raw,
            'data' => $data,
            'code' => $code,
            'description' => $description,
        ];
        
        if ($code !== 0) {
            return new ecjia_error('ecjia_sms_send_error', $result['description'], $result);
        }
        
        return $result;
    }
    
}