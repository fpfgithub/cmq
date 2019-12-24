<?php
namespace Qcloud;

require_once 'cmq/cmq_api.php';
require_once CMQAPI_ROOT_PATH . '/account.php';
require_once CMQAPI_ROOT_PATH . '/queue.php';
require_once CMQAPI_ROOT_PATH . '/cmq_exception.php';

class Cmq
{
    private $secretId;
    private $secretKey;
    private $endpoint;

    private $queue_name;
    private $queue;
    private $account;
    private $isenv;

    /*
     * 类初始化
     * 关于$endpoint
     * 外网https://cmq-queue-sh.api.qcloud.com
     * 内网https://cmq-queue-sh.api.tencentyun.com
     * 其中sh可以替换为gz或bj，代表区域
     */
    public function __construct($secretId, $secretKey, $endpoint, $isenv=true)
    {
        $this->secretId  = $secretId;
        $this->secretKey = $secretKey;
        $this->endpoint  = $endpoint;
        $this->isenv     = $isenv;
        $this->account   = new Account($this->endpoint, $this->secretId, $this->secretKey);
    }

    /*
     * 调用初始化
     */
    public function init($queue_name)
    {
        if (strpos($queue_name, APP_ENV) === false && $this->isenv) {
            $queue_name = $queue_name . APP_ENV;
        }
        $this->queue_name = $queue_name;
        $this->queue      = $this->account->get_queue($this->queue_name);
        return $this->queue;
    }

    /*
     * init别名
     */
    public function set_queue_name($queue_name)
    {
        return $this->init($queue_name);
    }

    /*
     * 返回正在操作的队列名字
     */
    public function get_queue_name()
    {
        return $this->queue_name;
    }

    /*
     * 创建队列
     * $queue_name，队列名称
     * $param 参数可包含除queue_name之外的其他属性，具体请参考如下文档
     * https://www.qcloud.com/document/product/406/8435
     */
    public function create_queue($queue_name, $param)
    {
        try {
            if (strpos($queue_name, APP_ENV) === false && $this->isenv) {
                $queue_name = $queue_name . APP_ENV;
            }
            $this->queue_name      = $queue_name;
            $queue_meta            = new QueueMeta();
            $queue_meta->queueName = $this->queue_name;
            if (is_array($param)) {
                foreach ($param as $key => $value) {
                    $queue_meta->$key = $value;
                }
            }
            return $this->queue->create($queue_meta);
        } catch (CMQExceptionBase $e) {
            return "Create Queue Fail! Exception: " . $e;
        }
    }

    /*
     * 设置队列属性
     * $param 参数可包含除queue_name之外的其他属性，具体请参考如下文档
     * https://www.qcloud.com/document/product/406/8435
     */
    public function set_queue_attributes($param)
    {
        $queue_meta            = new QueueMeta();
        $queue_meta->queueName = $this->queue_name;
        if (is_array($param)) {
            foreach ($param as $key => $value) {
                $queue_meta->$key = $value;
            }
        }
        return $this->queue->set_attributes($queue_meta);
    }

    /*
     * 获取队列属性
     */
    public function get_queue_attributes()
    {
        return $this->queue->get_attributes();
    }

    /*
     * 列出这个account下的队列
     */
    public function list_queue()
    {
        return $this->account->list_queue();
    }

    /*
     * 写消息
     * $msg，写入队列的数据
     * $delay_seconds，延迟写入时间
     */
    public function send_message($msg, $delay_seconds = 0)
    {
        try {
            $msg    = new Message($msg);
            $re_msg = $this->queue->send_message($msg, $delay_seconds);
            return $re_msg->msgId;
        } catch (CMQServerException $e) {
            //写入出错
            echo $e->getMessage();
            return;
        }
    }

    /*
     * 读消息
     * $wait_seconds，默认等待时间3秒
     */
    public function receive_message($wait_seconds = 3)
    {
        try {
            $recv_msg = $this->queue->receive_message($wait_seconds);
        } catch (CMQServerException $e) {
            echo $e->getMessage();
            //取不到消息，直接返回
            return;
        }
        return $recv_msg;
    }

    /*
     * receive_message别名
     */
    public function get_message($wait_seconds = 3)
    {
        return $this->receive_message($wait_seconds);
    }

    public function delete_message($receipt_handle)
    {
        return $this->queue->delete_message($receipt_handle);
    }

    /*
     * 批量写入消息
     * $msg, array
     */
    public function batch_send_message($msg)
    {
        try {
            $messages = array();
            foreach ($msg as $m) {
                $m          = new Message($m);
                $messages[] = $m;
            }
            return $this->queue->batch_send_message($messages);
        } catch (CMQServerException $e) {
            echo $e->getMessage();
            return;
        }
    }

    /*
     * 批量获取消息
     * $num，单次取出数量，默认3
     * $wait_seconds，等待时间，默认3
     */
    public function batch_receive_message($num = 3, $wait_seconds = 3)
    {
        try {
            $recv_msg_list = $this->queue->batch_receive_message($num, $wait_seconds);
            return $recv_msg_list;
        } catch (CMQServerException $e) {
            echo $e->getMessage();
            //取不到消息，直接返回
            return;
        }
    }

    /*
     * batch_receive_message别名
     */
    public function batch_get_message($num = 3, $wait_seconds = 3)
    {
        return $this->batch_receive_message($num, $wait_seconds);
    }

    /*
     * 删除队列
     * @queue_name，需要删除队列的名称
     * 注意，务必需要set_queue_name初始化后，才能进行删除操作
     * 同事，删除的时候，需要传递当前操作的队列名称，一致后才可以删除
     */
    public function delete_queue($queue_name)
    {
        if ($this->get_queue_name() == $queue_name) {
            return $this->queue->delete();
        } else {
            return false;
        }
    }

    //写消息
    public function set($queue_name, $msgtext, $delay_seconds = 0)
    {
        $trytimes = 3;
        do {
            try {
                $this->set_queue_name($queue_name);
                $msg      = new Message($msgtext);
                $re_msg   = $this->queue->send_message($msg, $delay_seconds);
                $trytimes = 0;
            } catch (CMQServerException $e) {
                if (4440 == $e->code) {
                    //队列不存在时默认新建
                    $param = array(
                        'pollingWaitSeconds' => 3, //消息接收长轮询等待时间
                    );
                    $this->create_queue($queue_name, $param);
                }
                $trytimes -= 1;
            }
            if (empty($re_msg)) {
                $trytimes = 1;
            }
        } while ($trytimes);
        return $re_msg->msgId;
    }
    //读消息
    public function get($queue_name, $wait_seconds = 3)
    {
        try {
            $this->init($queue_name);
            $msg = $this->queue->receive_message($wait_seconds);
        } catch (CMQServerException $e) {
            return false;
        } catch (CMQClientNetworkException $e) {
            return false;
        }
        return $msg;
    }
    //消息应答
    public function ack($queue_name, $receipt_handle)
    {
        try {
            $this->init($queue_name);
            $this->queue->delete_message($receipt_handle);
        } catch (CMQServerException $e) {
            return false;
        }
        return true;
    }

    //获取下队列剩余的数量
    public function count($queue_name)
    {
        try {
            $this->init($queue_name);
            $obj = $this->queue->get_attributes();
        } catch (CMQServerException $e) {
            return 0;
        }
        return $obj->activeMsgNum;
    }
}
