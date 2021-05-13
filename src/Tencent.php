<?php
namespace Pctco\Sms;
use TencentCloud\Common\Credential;
use TencentCloud\Common\Profile\ClientProfile;
use TencentCloud\Common\Profile\HttpProfile;
use TencentCloud\Common\Exception\TencentCloudSDKException;
use TencentCloud\Sms\V20190711\SmsClient;
use TencentCloud\Sms\V20190711\Models\SendSmsRequest;
use think\facade\Db;
use Pctco\Sms\Processor;
class Tencent{
   private $config;
   /**
   * @access 配置
   * @return
   **/
   function __construct($config){
      $this->config = $config;
   }
   public function send($itac,$phone,$template,$product = ''){
      try {
         $cred = new Credential($this->config['config.sms.config']['access']['accessKeyId'],$this->config['config.sms.config']['access']['accessKeySecret']);
         $httpProfile = new HttpProfile();
         $httpProfile->setEndpoint("sms.tencentcloudapi.com");

         $clientProfile = new ClientProfile();
         $clientProfile->setHttpProfile($httpProfile);
         $client = new SmsClient($cred, "", $clientProfile);

         $req = new SendSmsRequest();

         $params = array(
            'PhoneNumberSet'   =>   [$itac.$phone],
            'TemplateID'   =>   $this->config['config.sms.config']['template'][$itac][$template]['code'],
            'Sign'   =>   $this->config['config.sms.sign'],
            'TemplateParamSet'   =>   [$this->config['config.sms.code']],
            'SmsSdkAppid'   =>   $this->config['config.sms.config']['access']['SmsSdkAppid']
         );
         $req->fromJsonString(json_encode($params));

         $resp = $client->SendSms($req);

         $resp = json_decode($resp->toJsonString(),true);
         $processor = new Processor();
         if ($resp['SendStatusSet'][0]['Code'] == 'Ok') {
            return $processor->success($itac,$phone,$template);
         }

         return [
             'headers' => 'Prompt info',
             'status'=>'info',
             'content'=>   $resp['SendStatusSet'][0]['Message'],
             'sub' => $processor->ErrorCode($resp['SendStatusSet'][0]['Code'])
         ];
      }
      catch(TencentCloudSDKException $e) {
          return $e;
      }
   }
}
