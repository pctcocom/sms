<?php
namespace Pctco\Sms;
class BaiDu{
   private $config;
   private $client;
   /**
   * @access 配置
   * @return
   **/
   function __construct($config){
      $this->config = $config;
      $this->client = new \GuzzleHttp\Client([
         'base_uri' => $this->config['config.sms.config']['access']['domain']
      ]);
   }
   public function send($itac,$phone,$template,$product = ''){
      $proxy = $this->client->request('POST','/api/v3/sendSms',[
         'mobile' => $phone,
         'template'   =>   $this->config['config.sms.config']['template'][$itac][$template]['code'],
         'signatureId'   =>   $this->config['config.sms.config']['access']['signatureId'],
         'contentVar'   =>   '{"param1":"123","param2":"abc"}'
      ]);
      if ($proxy->getStatusCode() == 200) {
         $proxy->getHeaderLine('application/json; charset=utf8');
         $proxy = json_decode($proxy->getBody());
      }
      return $proxy;
   }
}
