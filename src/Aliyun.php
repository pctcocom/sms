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
   * @param mixed    $product    产品名称
   * @return array
   **/
   public function send($itac,$phone,$template,$product = ''){
      // return $this->config;
      $result =
      $this->SendSms($phone,$this->config['config.sms.sign'],$this->config['config.sms.config']['template'][$itac][$template]['code'],'{"code":"'.$this->config['config.sms.code'].'","product":"'.$product.'"}');
      if (!empty($result['Message'])) {
         $status = $result['Message'] == 'OK' ? true : false ;
         if($status){
            $processor = new Processor();
            return $processor->success($itac,$phone,$template);
         }else{
            $isv = [
               'isv.SMS_SIGNATURE_SCENE_ILLEGAL'   =>   '签名的适用场景与短信类型不匹配。',
               'isv.EXTEND_CODE_ERROR'   =>   '发送短信时不同签名的短信使用了相同扩展码。',
               'isv.DOMESTIC_NUMBER_NOT_SUPPORTED'   =>   '国际/港澳台消息模板仅支持发送国际、港澳台地区的号码。',
               'isv.DENY_IP_RANGE'   =>   '被系统检测到源IP属于非中国大陆地区。',
               'isv.DAY_LIMIT_CONTROL'   =>   '已经达到您在控制台设置的短信日发送量限额值。',
               'isv.SMS_CONTENT_ILLEGAL'   =>   '短信内容包含禁止发送内容。',
               'isv.SMS_SIGN_ILLEGAL'   =>   '签名禁止使用。',
               'isp.RAM_PERMISSION_DENY'   =>   'RAM权限不足。',
               'isv.OUT_OF_SERVICE'   =>   '余额不足。余额不足时，套餐包中即使有短信额度也无法发送短信。',
               'isv.PRODUCT_UN_SUBSCRIPT'   =>   '该AK所属的账号尚未开通云通信的服务，包括短信、语音、流量等服务。',
               'isv.PRODUCT_UNSUBSCRIBE'   =>   '该AK所属的账号尚未开通当前接口的产品，例如仅开通了短信服务的用户调用语音接口时会产生此报错信息。',
               'isv.ACCOUNT_NOT_EXISTS'   =>   '使用了错误的账户名称或AK。',
               'isv.ACCOUNT_ABNORMAL'   =>   '账户异常。',
               'isv.SMS_TEMPLATE_ILLEGAL'   =>   '短信模板不存在，或未经审核通过。',
               'isv.SMS_SIGNATURE_ILLEGAL'   =>   '签名不存在，或未经审核通过。',
               'isv.INVALID_PARAMETERS'   =>   '参数格式不正确。',
               'isp.SYSTEM_ERROR'   =>   '系统错误。',
               'isv.MOBILE_NUMBER_ILLEGAL'   =>   '手机号码格式错误。',
               'isv.MOBILE_COUNT_OVER_LIMIT'   =>   '参数PhoneNumbers中指定的手机号码数量超出限制。',
               'isv.TEMPLATE_MISSING_PARAMETERS'   =>   '参数TemplateParam中，变量未全部赋值。',
               'isv.BUSINESS_LIMIT_CONTROL'   =>   '短信发送频率超限。',
               'isv.INVALID_JSON_PARAM'   =>   '参数格式错误，不是合法的JSON格式。',
               'isv.BLACK_KEY_CONTROL_LIMIT'   =>   '黑名单管控是指变量内容含有限制发送的内容，例如变量中不允许透传URL。',
               'isv.PARAM_LENGTH_LIMIT'   =>   '参数超出长度限制。',
               'isv.PARAM_NOT_SUPPORT_URL'   =>   '黑名单管控是指变量内容含有限制发送的内容，例如变量中不允许透传URL。',
               'isv.AMOUNT_NOT_ENOUGH'   =>   '当前账户余额不足。',
               'isv.TEMPLATE_PARAMS_ILLEGAL'   =>   '变量内容含有限制发送的内容，例如变量中不允许透传URL。',
               'SignatureDoesNotMatch'   =>   '签名（Signature）加密错误。',
               'InvalidTimeStamp.Expired'   =>   '一般由于时区差异造成时间戳错误，发出请求的时间和服务器接收到请求的时间不在15分钟内。阿里云网关使用的时间是GMT时间。',
               'SignatureNonceUsed'   =>   '唯一随机数重复，SignatureNonce为唯一随机数，用于防止网络重放攻击。',
               'InvalidVersion'   =>   '版本号（Version）错误。',
               'InvalidAction.NotFound'   =>   '参数Action中指定的接口名错误。',
               'isv.SIGN_COUNT_OVER_LIMIT'   =>   '一个自然日中申请签名数量超过限制。',
               'isv.TEMPLATE_COUNT_OVER_LIMIT'   =>   '一个自然日中申请模板数量超过限制。',
               'isv.SIGN_NAME_ILLEGAL'   =>   '签名名称不符合规范。',
               'isv.SIGN_FILE_LIMIT'   =>   '签名认证材料附件大小超过限制。',
               'isv.SIGN_OVER_LIMIT'   =>   '签名的名称或申请说明的字数超过限制。',
               'isv.TEMPLATE_OVER_LIMIT'   =>   '模板的名称、内容或申请说明的字数超过限制。'
            ];

            return [
               'headers' => 'Prompt info',
               'status'=>'info',
               'content'=>empty($isv[$result['Code']])?'ERROR':$isv[$result['Code']]
            ];
         };
      }
      return json([
         'headers' => 'Prompt info',
         'status'=>'error',
         'content'=>'获取验证码失败'
      ]);
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
