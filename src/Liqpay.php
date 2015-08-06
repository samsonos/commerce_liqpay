<?php
/**
 * Created by Vitaly Iegorov <egorov@samsonos.com>
 * on 13.09.14 at 18:35
 */
namespace samsonos\commerce\liqpay;

use samson\core\CompressableService;
use samsonphp\event\Event;
use samsonos\commerce\Payment;
use samsonos\commerce\PaymentLog;
use samsonos\commerce\Order;
use Symfony\Component\Config\Definition\Exception\Exception;

/**
 * SamsonPHP Liqpay module
 * @author Vitaly Egorov <egorov@samsonos.com>
 * @copyright 2014 SamsonOS
 */
class Liqpay extends CompressableService
{
    public $id = 'liqpay';

    /** @var  string this is the public key for payment */
    public $publicKey;

    /** @var  string this is the private key for payment */
    public $privateKey;

    /** @var  string this is the result url which will use for redirect */
    public $resultUrl;

    /** @var int is the user id */
    public $userId = 1;

    /** @var \LiqPay api instance */
    private $gate;

    /**
     * Init module
     * @param array $params
     */
    public function init(array $params = array())
    {
        parent::init($params);

        //Save LiqPay library instance
        $this->gate = new \LiqPay($this->publicKey, $this->privateKey);

        //Base part of back url
        if (!isset($this->resultUrl)) {
            $this->resultUrl = url_build('/');
        }

        //Call event
        Event::fire('commerce.gateinited',array(& $this));
    }

    /**
     * Create form
     * @param $Payment Payment
     * @return string html
     */
    public function createForm($Payment)
    {
        return $this->gate->cnb_form(array(
            'version'        => '3',
            'amount'         => $Payment->Amount,
            'currency'       => $Payment->Currency,
            'description'    => t("Оплата за заказ - ", true).' '.$Payment->OrderId,
            'order_id'       => $Payment->OrderId,
            'result_url'     => $this->resultUrl,
            //'server_url'     => url_build('liqpay','status')
            //TODO change it for production
            'server_url'     => 'http://molodyko.yourtour.local.samsonos.com/liqpay/status',
            'sandbox'        => 1
        ));
    }

    /**
     * Save income payment data
     * @param $result object with data from payment service
     * @return Payment \samsonos\commerce\Payment
     */
    public function savePayment($result){

        //Get status
        list($status,$comment) = $this->convertStatus($result->status);

        $order = null;

        //If order exists
        if(dbQuery('order')->id($result->order_id)->first($order)){

            //Save payment
            $payment = new Payment($order,$this->id,$result->amount);
            $payment->Status = $status;
            $payment->Phone = $result->sender_phone;
            $payment->Description = $comment;
            $payment->Response = json_encode($result);
            $payment->save();

            //Save paymnetLog
            $log = new PaymentLog(false);
            $log->PaymentId = $payment->id;
            $log->Comment = $comment;
            $log->Status = $status;
            $log->save();

            return $payment;
        }
        new Exception('Order not found');
    }

    /**
     * Get right form of status and comment
     * @param $status
     * @param $comment
     * @return array status and comment
     */
    public function convertStatus($status){

        //Get status
        switch ((string)$status){
            case 'success':
                $status = Payment::STATUS_SUCCESS;
                $comment = t('Оплата прошла успешно',true);
                break;
            case 'wait_secure':
                $status = Payment::STATUS_WAIT_SECURE;
                $comment = t('Wait Secure',true);
                break;
            case 'sandbox':
                $status = Payment::STATUS_TEST_SUCCESSFUL;
                $comment = t('Успешный тестовый платеж',true);
                break;
            case 'failure':
            default:
                $status = Payment::STATUS_FAIL;
                $comment = t('Ошибка оплаты',true);
        }

        return array($status,$comment);
    }

    /**
     * On this url will be come incoming request with data from payment service
     */
    public function __status()
    {
        if(isset($_POST['data']))
        {
            $data = $_POST['data'];

            //Get hash
            $hash = $this->gate->str_to_sign($this->privateKey.$data.$this->privateKey);
            $result = json_decode(base64_decode($data));
            //$result = json_decode($data);

            //If checking security was good then go further
            if($hash == $_POST['signature'])
            {
                //Save result
                $payment = $this->savePayment($result);

                //Call event which means the data was passed
                Event::fire('commerce.update.status', array($payment));
            }
        }
        exit;
    }
}