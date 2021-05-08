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
      $config['config.sms.config'] = json_decode($config['config.sms.'.$config['config.sms.api']],true);
      $config['config.sms.minute'] = ((int)$config['config.sms.cycle'])/60;

      /**
      * @name 随机验证码
      **/
      $Az = ['A','b','C','D','E','F','g','H','i','J','K','L','M','n','O','P','Q','R','s','T','U','v','W','X','y','z'];
      $Num = ['0','1','2','3','4','5','6','7','8','9'];
      $AzNum = array_merge($Az,$Num);
      $code = '';
      $length = (int)$config['config.sms.length'];
      switch ($config['config.sms.code_type']) {
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
      switch ($this->config['config.sms.api']) {
         case 'AliyunSms':
            $this->client = new \Pctco\Sms\Aliyun($this->config);
            break;
         case 'BaiDuCloudSms':
            $this->client = new \Pctco\Sms\BaiDu($this->config);
            break;
         case 'TencentCloudSms':
            $this->client = new \Pctco\Sms\Tencent($this->config);
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
   * @param mixed    $product    产品名称
   * @example        Sms::send('86','13677777777','04');
   * @return array
   **/
   public function send($itac,$phone,$template,$product = ''){
      return $this->client->send($itac,$phone,$template,$product = '');
   }
   /**
   * @access 判断发送验证码是否正确
   * @param mixed    $itac   国际电话号码区号
   * @param mixed    $phone  手机号码
   * @param mixed    $template 短信模板  01,02,03
   * @param mixed    $code 短信验证码
   * @return
   **/
   public function check(){
      $cycle = $this->config['config.sms.cycle'];
      $sms =
      Db::name('temporary')
      ->order('time desc')
      ->field('n4,time')
      ->where([
         'n1'     =>  $itac,
         'n2'    =>  $phone,
         'n3' =>  $template,
         'type'   =>   'sms'
      ])->find();
      if ($sms['time'] < (time() - $cycle)) {
         return json([
             'status'=>'info',
             'prompt'=>'当前验证代码已过期。请重新获取验证码！',
             'url'=>'static',
             'field'   =>  ''
         ]);
      }
      if ($sms['n4'] != $code) {
         return json([
             'status'=>'info',
             'prompt'=>'验证码不正确。请重新进入！',
             'url'=>'static',
             'field'   =>  ''
         ]);
      }

      Db::name('temporary')
      ->order('time desc')
      ->where([
         'n1'     =>  $itac,
         'n2'    =>  $phone,
         'n3' =>  $template,
         'type'   =>   'sms'
      ])->delete();

      Db::name('temporary')
      ->where('type','sms')
      ->where('time','<',time() - $cycle)
      ->delete();

      return true;
   }
   /**
   * @name success
   * @describe 发送成功
   * @return Array
   **/
   public function success($itac,$phone,$template){
      Db::name('temporary')->insert([
         'n1'     =>  $itac,
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
         'length'      =>   $this->config['config.sms.length']
      ];
   }

}
