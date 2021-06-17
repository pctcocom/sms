<?php
namespace Pctco\Sms;
use think\facade\Db;
use think\facade\Cache;
use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;
use Nahid\JsonQ\Jsonq;
class Processor{
   private $client;
   private $config;
   /**
   * @access 配置
   * @return
   **/
   function __construct(){
      $config = Cache::store('config')->get(md5('app\admin\controller\Config\sms\var'));
      $config['config.sms.config'] = json_decode($config['sms'][$config['sms']['api']],true);
      $config['config.sms.minute'] = ((int)$config['sms']['cycle'])/60;

      /**
      * @name 随机验证码
      **/
      $Az = ['A','b','C','D','E','F','g','H','i','J','K','L','M','n','O','P','Q','R','s','T','U','v','W','X','y','z'];
      $Num = ['0','1','2','3','4','5','6','7','8','9'];
      $AzNum = array_merge($Az,$Num);
      $code = '';
      $length = (int)$config['sms']['length'];
      switch ($config['sms']['code_type']) {
         case '2':
            for ($i=0; $i < $length; $i++) $code = $code.$Az[rand(0, count($Az)-1)];
            break;
         case '3':
            for ($i=0; $i < $length; $i++) $code = $code.$AzNum[rand(0, count($AzNum)-1)];
            break;
         default:
            for ($i=0; $i < $length; $i++) $code = $code.$Num[rand(0, count($Num)-1)];
            break;
      }
      $config['config.sms.code'] = $code;

      $this->config = $config;
      switch ($config['sms']['api']) {
         case 'AliyunSms':
            $this->client = new \Pctco\Sms\Aliyun($config);
            break;
         case 'BaiDuCloudSms':
            $this->client = new \Pctco\Sms\BaiDu($config);
            break;
         case 'TencentCloudSms':
            $this->client = new \Pctco\Sms\Tencent($config);
            break;

         default:
            // code...
            break;
      }
   }
   /**
   * @access 发送短信
   * @param mixed    $itac       国际电话区号
   * @param mixed    $phone      手机号码
   * @param mixed    $template   模版 根据后台  应用编号填写 04 = SMS_172575229
   * @param mixed    $abridge   US、CN
   * @param mixed    $product    产品名称
   * @example        Sms::send('86','13677777777','04');
   * @return array
   **/
   public function send($itac,$phone,$template,$abridge,$product = ''){
      return $this->client->send($itac,$phone,$template,$abridge,$product = '');
   }
   /**
   * @access 判断发送验证码是否正确
   * @param mixed    $itac   国际电话号码区号
   * @param mixed    $phone  手机号码
   * @param mixed    $template 短信模板  01,02,03
   * @param mixed    $code(sms) 短信验证码
   * @return
   **/
   public function check($data){
      $cycle = (int)$this->config['sms']['cycle'];

      $where = [
         'n1'     =>  (int)$data['countries']['itac'],
         'n2'    =>  $data['phone'],
         'n3' =>  $data['countries']['template'],
         'type'   =>   'sms'
      ];

      Db::name('temporary')
      ->where('type','sms')
      ->where('time','<',time() - $cycle)
      ->delete();


      $sms =
      Db::name('temporary')
      ->order('time desc')
      ->field('n4,time')
      ->where($where)->find();
      if (empty($sms)) {
         return json([
            'headers' => 'Prompt info',
            'status'=>'info',
            'content'=>'验证码已失效',
            'sub' => '当前验证代码已过期。请重新获取验证码！'
         ]);
      }
      if ($sms['n4'] != $data['sms']) {
         return json([
            'headers' => 'Prompt info',
            'status'=>'info',
            'content'=>'验证码错误',
            'sub' => '验证码不正确。请重新进入！'
         ]);
      }

      Db::name('temporary')
      ->order('time desc')
      ->where($where)->delete();

      return true;
   }
   /**
   * @name success
   * @describe 发送成功
   * @return Array
   **/
   public function success($itac,$phone,$template){
      Db::name('temporary')->insert([
         'n1'     =>  (int)$itac,
         'n2'    =>  $phone,
         'n3' =>  $template,
         'n4'     =>  $this->config['config.sms.code'],
         'type'   =>   'sms',
         'time'     =>  time()
      ]);
      return [
         'headers' => 'Prompt info',
         'status'=>'success',
         'content'=>'短信发送成功，短信有效期为 '.$this->config['config.sms.minute'].' 分钟',
         'sub' => 'Please do not send SMS messages to anyone!',
         // 短信实际有效时间(分钟)
         'minute'   =>   $this->config['config.sms.minute'],
         'second'   =>   60,  // 倒计时秒数
         'length'      =>   $this->config['sms']['length']
      ];
   }

   /**
   * @name error
   * @describe 错误码
   * isv = aliyun
   * @return String
   **/
   public function ErrorCode($code){
      switch ($code) {
         case 'isv.SMS_SIGNATURE_SCENE_ILLEGAL':
            return '签名的适用场景与短信类型不匹配。';
            break;
         case 'isv.EXTEND_CODE_ERROR':
            return '发送短信时不同签名的短信使用了相同扩展码。';
            break;
         case 'isv.DOMESTIC_NUMBER_NOT_SUPPORTED':
            return '国际/港澳台消息模板仅支持发送国际、港澳台地区的号码。';
            break;
         case 'isv.DENY_IP_RANGE':
            return '被系统检测到源IP属于非中国大陆地区。';
            break;
         case 'isv.DAY_LIMIT_CONTROL':
            return '已经达到您在控制台设置的短信日发送量限额值。';
            break;
         case 'isv.SMS_CONTENT_ILLEGAL': case 'FailedOperation.ContainSensitiveWord':
            return '短信内容包含禁止发送内容。';
            break;
         case 'isv.SMS_SIGN_ILLEGAL':
            return '签名禁止使用。';
            break;
         case 'isv.RAM_PERMISSION_DENY':
            return 'RAM权限不足。';
            break;
         case 'isv.OUT_OF_SERVICE': case 'FailedOperation.InsufficientBalanceInSmsPackage':
            return '余额不足。余额不足时，套餐包中即使有短信额度也无法发送短信。';
            break;
         case 'isv.PRODUCT_UN_SUBSCRIPT':
            return '该AK所属的账号尚未开通云通信的服务，包括短信、语音、流量等服务。';
            break;
         case 'isv.PRODUCT_UNSUBSCRIBE':
            return '该AK所属的账号尚未开通当前接口的产品，例如仅开通了短信服务的用户调用语音接口时会产生此报错信息。';
            break;
         case 'isv.ACCOUNT_NOT_EXISTS':
            return '使用了错误的账户名称或AK。';
            break;
         case 'isv.ACCOUNT_ABNORMAL':
            return '账户异常。';
            break;
         case 'isv.SMS_TEMPLATE_ILLEGAL':
            return '短信模板不存在，或未经审核通过。';
            break;
         case 'isv.SMS_SIGNATURE_ILLEGAL': case 'InvalidParameterValue.MissingSignatureList':
            return '签名不存在，或未经审核通过。';
            break;
         case 'isv.INVALID_PARAMETERS':
            return '参数格式不正确。';
            break;
         case 'isv.SYSTEM_ERROR':
            return '系统错误。';
            break;
         case 'isv.MOBILE_NUMBER_ILLEGAL':
            return '手机号码格式错误。';
            break;
         case 'isv.MOBILE_COUNT_OVER_LIMIT':
            return '参数PhoneNumbers中指定的手机号码数量超出限制。';
            break;
         case 'isv.TEMPLATE_MISSING_PARAMETERS':
            return '参数TemplateParam中，变量未全部赋值。';
            break;
         case 'isv.BUSINESS_LIMIT_CONTROL':
            return '短信发送频率超限。';
            break;
         case 'isv.INVALID_JSON_PARAM':
            return '参数格式错误，不是合法的JSON格式。';
            break;
         case 'isv.BLACK_KEY_CONTROL_LIMIT':
            return '黑名单管控是指变量内容含有限制发送的内容，例如变量中不允许透传URL。';
            break;
         case 'isv.PARAM_LENGTH_LIMIT':
            return '参数超出长度限制。';
            break;
         case 'isv.PARAM_NOT_SUPPORT_URL':
            return '黑名单管控是指变量内容含有限制发送的内容，例如变量中不允许透传URL。';
            break;
         case 'isv.AMOUNT_NOT_ENOUGH': case 'UnauthorizedOperation.SerivceSuspendDueToArrears':
            return '当前账户余额不足。';
            break;
         case 'isv.TEMPLATE_PARAMS_ILLEGAL': case 'InvalidParameterValue.ProhibitedUseUrlInTemplateParameter':
            return '变量内容含有限制发送的内容，例如变量中不允许透传URL。';
            break;
         case 'SignatureDoesNotMatch':
            return '签名（Signature）加密错误。';
            break;
         case 'InvalidTimeStamp.Expired':
            return '一般由于时区差异造成时间戳错误，发出请求的时间和服务器接收到请求的时间不在15分钟内。阿里云网关使用的时间是GMT时间。';
            break;
         case 'SignatureNonceUsed':
            return '唯一随机数重复，SignatureNonce为唯一随机数，用于防止网络重放攻击。';
            break;
         case 'InvalidVersion':
            return '版本号（Version）错误。';
            break;
         case 'InvalidAction.NotFound':
            return '参数Action中指定的接口名错误。';
            break;
         case 'isv.SIGN_COUNT_OVER_LIMIT':
            return '一个自然日中申请签名数量超过限制。';
            break;
         case 'isv.TEMPLATE_COUNT_OVER_LIMIT':
            return '一个自然日中申请模板数量超过限制。';
            break;
         case 'isv.SIGN_NAME_ILLEGAL':
            return '签名名称不符合规范。';
            break;
         case 'isv.SIGN_FILE_LIMIT':
            return '签名认证材料附件大小超过限制。';
            break;
         case 'isv.SIGN_OVER_LIMIT':
            return '签名的名称或申请说明的字数超过限制。';
            break;
         case 'isv.TEMPLATE_OVER_LIMIT':
            return '模板的名称、内容或申请说明的字数超过限制。';
            break;
         case '':
            return '';
            break;
         case 'FailedOperation.FailResolvePacket':
            return '请求包解析失败，通常情况下是由于没有遵守 API 接口说明规范导致的，请参考 请求包体解析1004错误详解。';
            break;
         case 'FailedOperation.JsonParseFail':
            return '解析请求包体时候失败。';
            break;
         case 'FailedOperation.MarketingSendTimeConstraint':
            return '营销短信发送时间限制，为避免骚扰用户，营销短信只允许在8点到22点发送。';
            break;
         case 'FailedOperation.MissingSignature':
            return '没有申请签名之前，无法申请模板，请根据 创建签名 申请完成之后再次申请。';
            break;
         case 'FailedOperation.MissingSignatureToModify':
            return '此签名 ID 未提交申请或不存在，不能进行修改操作，请检查您的 SignId 是否填写正确。';
            break;
         case 'FailedOperation.MissingTemplateToModify':
            return '此模板 ID 未提交申请或不存在，不能进行修改操作，请检查您的 TemplateId是否填写正确。';
            break;
         case 'FailedOperation.NotEnterpriseCertification':
            return '非企业认证无法使用签名及模版相关接口，您可以 变更实名认证模式，变更为企业认证用户后，约1小时左右生效。';
            break;
         case 'FailedOperation.OtherError':
            return '其他错误，一般是由于参数携带不符合要求导致';
            break;
         case 'FailedOperation.PhoneNumberInBlacklist': case 'FailedOperation.PhoneNumberOnBlacklist':
            return '手机号在黑名单库中，通常是用户退订或者命中运营商黑名单导致';
            break;
         case 'FailedOperation.SignatureIncorrectOrUnapproved':
            return '手签名格式错误或者签名未审批，签名只能由中英文、数字组成，要求2 - 12个字。如果符合签名格式规范，请核查签名是否已审批。';
            break;
         case 'FailedOperation.TemplateAlreadyPassedCheck':
            return '此模板已经通过审核，无法再次进行修改。';
            break;
         case 'FailedOperation.TemplateIncorrectOrUnapproved':
            return '模版未审批或请求的内容与审核通过的模版内容不匹配';
            break;
         case 'InternalError.RequestTimeException':
            return '请求发起时间不正常，通常是由于您的服务器时间与腾讯云服务器时间差异超过10分钟导致的，请核对服务器时间及 API 接口中的时间字段是否正常。';
            break;
         case 'InternalError.RestApiInterfaceNotExist':
            return '不存在该 RESTAPI 接口，请核查 REST API 接口说明。';
            break;
         case 'InternalError.SendAndRecvFail':
            return '接口超时或后短信收发包超时，请检查您的网络是否有波动';
            break;
         case 'InternalError.SigFieldMissing':
            return '后端包体中请求包体没有 Sig 字段或 Sig 为空。';
            break;
         case 'InternalError.SigVerificationFail':
            return '后端校验 Sig 失败。';
            break;
         case 'InternalError.Timeout':
            return '请求下发短信超时，请参考 60008错误详解。';
            break;
         case 'InvalidParameter.AppidAndBizId':
            return '账号与应用id不匹配。';
            break;
         case 'InvalidParameter.InvalidParameters':
            return 'International 或者 SmsType 参数有误';
            break;
         case 'InvalidParameterValue.ContentLengthLimit':
            return '请求的短信内容太长，短信长度规则请参考 国内短信内容长度计算规则。';
            break;
         case 'InvalidParameterValue.ImageInvalid':
            return '上传的转码图片格式错误，请参照 API 接口说明中对改字段的说明';
            break;
         case 'InvalidParameterValue.IncorrectPhoneNumber':
            return '手机号格式错误，请参考 1016错误详解。';
            break;
         case 'InvalidParameterValue.InvalidDocumentType':
            return 'DocumentType 字段校验错误，请参照 API 接口说明中对改字段的说明';
            break;
         case 'InvalidParameterValue.InvalidInternational':
            return 'International 字段校验错误，请参照 API 接口说明中对改字段的说明';
            break;
         case 'InvalidParameterValue.InvalidStartTime':
            return '无效的拉取起始/截止时间，具体原因可能是请求的 SendDateTime 大于 EndDateTime。';
            break;
         case 'InvalidParameterValue.InvalidUsedMethod':
            return 'UsedMethod 字段校验错误，请参照 API 接口说明中对改字段的说明';
            break;
         case 'InvalidParameterValue.SdkAppidNotExist':
            return 'SdkAppid 不存在。';
            break;
         case 'InvalidParameterValue.SignAlreadyPassedCheck':
            return '此签名已经通过审核，无法再次进行修改。';
            break;
         case 'InvalidParameterValue.TemplateParameterFormatError':
            return '验证码模板参数格式错误，验证码类模版，模版变量只能传入0 - 6位（包括6位）纯数字。';
            break;
         case 'InvalidParameterValue.TemplateParameterLengthLimit':
            return '单个模板变量字符数超过12个，企业认证用户不限制单个变量值字数，您可以 变更实名认证模式，变更为企业认证用户后，该限制变更约1小时左右生效。';
            break;
         case 'LimitExceeded.AppDailyLimit':
            return '业务短信日下发条数超过设定的上限 ，可自行到控制台调整短信频率限制策略。';
            break;
         case 'LimitExceeded.DailyLimit':
            return '短信日下发条数超过设定的上限 (国际/港澳台)，如需调整限制';
            break;
         case 'LimitExceeded.DeliveryFrequencyLimit':
            return '下发短信命中了频率限制策略，可自行到控制台调整短信频率限制策略';
            break;
         case 'LimitExceeded.PhoneNumberCountLimit':
            return '调用短信发送 API 接口单次提交的手机号个数超过200个';
            break;
         case 'LimitExceeded.PhoneNumberDailyLimit':
            return '单个手机号日下发短信条数超过设定的上限，可自行到控制台调整短信频率限制策略。';
            break;
         case 'LimitExceeded.PhoneNumberOneHourLimit':
            return '单个手机号1小时内下发短信条数超过设定的上限，可自行到控制台调整短信频率限制策略。';
            break;
         case 'LimitExceeded.PhoneNumberSameContentDailyLimit':
            return '单个手机号下发相同内容超过设定的上限，可自行到控制台调整短信频率限制策略。';
            break;
         case 'LimitExceeded.PhoneNumberThirtySecondLimit':
            return '单个手机号30秒内下发短信条数超过设定的上限，可自行到控制台调整短信频率限制策略。';
            break;
         case 'MissingParameter.EmptyPhoneNumberSet':
            return '传入的号码列表为空，请确认您的参数中是否传入号码。';
            break;
         case 'UnauthorizedOperation.IndividualUserMarketingSmsPermissionDeny':
            return '个人用户没有发营销短信的权限';
            break;
         case 'UnauthorizedOperation.RequestIpNotInWhitelist':
            return '请求 IP 不在白名单中，您配置了校验请求来源 IP，但是检测到当前请求 IP 不在配置列表中';
            break;
         case 'UnauthorizedOperation.RequestPermissionDeny':
            return '请求没有权限';
            break;
         case 'UnauthorizedOperation.SdkAppidIsDisabled':
            return '此 sdkappid 禁止提供服务';
            break;
         case 'UnauthorizedOperation.SmsSdkAppidVerifyFail':
            return 'SmsSdkAppid 校验失败。';
            break;
         case 'UnsupportedOperation.':
            return '不支持该请求。';
            break;
         case 'UnsupportedOperation.ContainDomesticAndInternationalPhoneNumber':
            return '群发请求里既有国内手机号也有国际手机号。';
            break;
         case 'UnsupportedOperation.UnsuportedRegion':
            return '不支持该地区短信下发。';
            break;

         default:
            return '未知错误类型。';
            break;
      }
   }

}
