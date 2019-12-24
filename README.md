# cmq
腾讯云CMQ

## 使用
```
require 'vendor/autoload.php';

define('APP_ENV', 'test');//根据不同的环境 区分同名队列
$isenv = true; //是否需要拼接环境后缀APP_ENV,默认true
$secretId = ""; //"云 API 密钥 SecretId";
$secretKey = ""; //"云 API 密钥 SecretKey";
$endPoint = 'https://cmq-queue-gz.api.qcloud.com';//endPoint
$cmq =  new  Qcloud\Cmq($secretId, $secretKey, $endPoint, $isenv);

$queueName = 'test-queue';

//入队列
for ($i=0; $i < 10; $i++) { 
	$f = $cmq->set($queueName, $i);
	echo $f.PHP_EOL;
}

//读队列
do {
	$msg = $cmq->get($queueName);
	if ($msg) {
		$body = $msg->msgBody;
		$cmq->ack($queueName, $msg->receiptHandle);//应答 从队列中删除消息
		echo $body.PHP_EOL;
	}
} while ($msg);

//count
$count = $cmq->count($queueName);
echo 'count=>'.$count.PHP_EOL;

//删除队列
$cmq->set_queue_name($queueName.APP_ENV);
$cmq->delete_queue($queueName.APP_ENV);
```
