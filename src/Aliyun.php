<?php
namespace Pctco\Sms;
use think\facade\Db;
use think\facade\Cache;
use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;
class Aliyun{
   private $config;
   /**
   * @access 配置
   * @return
   **/
   function __construct($config){
      $this->config = $config;
   }
   /**
   * @access 发送短信
   * @param mixed    $itac       国际电话区号
   * @param mixed    $phone      手机号码
   * @param mixed    $template   模版 根据后台  应用编号填写 04 = SMS_172575229
   * @param mixed    $abridge   US、CN
   * @param mixed    $product    产品名称
   * @return array
   **/
   public function send($itac,$phone,$template,$abridge,$product = ''){
      // return $this->config;
      $result =
      $this->SendSms($phone,$this->config['sms']['sign'],$this->config['config.sms.config']['template'][$abridge][$template]['code'],'{"code":"'.$this->config['config.sms.code'].'","product":"'.$product.'"}');
      if (!empty($result['Message'])) {
         $status = $result['Message'] == 'OK' ? true : false ;
         $processor = new Processor();
         if($status){
            return $processor->success($itac,$phone,$template,$this->config['config.sms.code']);
         }else{
            return [
               'status'=>  'danger',
               'tips'   => 'Danger '.$result['Code'],
               'message'   => $processor->ErrorCode($result['Code'])
            ];
         };
      }
      return [
         'status' => 'warning',
         'tips'   => 'Warning',
         'message'   => '获取验证码失败'
      ];
   }
   /*

   *   阿里云接口配置

   */

   /**
   * @access 发送短信
   * @param mixed $PhoneNumbers 手机号码
   * @param mixed $SignName 签名 如：注册验证
   * @param mixed $TemplateCode 模版code 如：SMS_16686167
   * @param mixed $TemplateParam 模版参数
   *              如模版 验证码${code}，您正在注册成为${product}用户，感谢您的支持！
   *              TemplateParam = {"code":"1112","product":"淘宝网"}
   * @example Sms::SendSms('13671771274','注册验证','SMS_16686167','{"code":"1112","product":"淘宝网"}')
   * @return ["Message" => "OK","RequestId" => "8708544C-9768-475C-A13E-4E2080AC6829","BizId" => "669223065424991525^0","Code" => "OK"]
   **/
   public function SendSms(
      $PhoneNumbers,
      $SignName,
      $TemplateCode,
      $TemplateParam
   ){
      $accessKeyId = trim($this->config['config.sms.config']['access']['accessKeyId']);
      $accessKeySecret = trim($this->config['config.sms.config']['access']['accessKeySecret']);
      AlibabaCloud::accessKeyClient($accessKeyId,$accessKeySecret)
      ->regionId($this->config['config.sms.config']['access']['regionId'])
      ->asDefaultClient();
      try {
         $result =
         AlibabaCloud::rpc()
         ->product('Dysmsapi')
         ->version('2017-05-25')
         ->action('SendSms')
         ->method('POST')
         ->host('dysmsapi.aliyuncs.com')
         ->options([
            'query' => [
               'RegionId' => $this->config['config.sms.config']['access']['regionId'],
               'PhoneNumbers' => $PhoneNumbers,
               'SignName' => $SignName,
               'TemplateCode' => $TemplateCode,
               'TemplateParam'   => $TemplateParam
            ],
         ])->request();
         return $result->toArray();
      } catch (ClientException $e) {
         // return false;
         return $e->getErrorMessage() . PHP_EOL;
      } catch (ServerException $e) {
         // return false;
         return $e->getErrorMessage() . PHP_EOL;
      }
   }
}
