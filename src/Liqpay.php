<?php
/**
 * Created by Vitaly Iegorov <egorov@samsonos.com>
 * on 13.09.14 at 18:35
 */
namespace samsonos\commerce\liqpay;

use samson\core\CompressableService;
use samsonphp\event\Event;
use samsonos\commerce\Payment;


/**
 * SamsonPHP Liqpay module
 * @author Vitaly Egorov <egorov@samsonos.com>
 * @copyright 2014 SamsonOS
 */
class Liqpay extends CompressableService
{
    public $id = 'liqpay';

    public $publicKey;
    public $privateKey;
    public $resultUrl;
    public $userId = 1;

    private $gate;



    public function init(array $params = array())
    {
        parent::init($params);

        $this->gate = new \LiqPay($this->publicKey, $this->privateKey);
        if (!isset($this->resultUrl)) {
            $this->resultUrl = url_build('/');
        }

        Event::fire('commerce.gateinited',array(& $this));
    }

    public function createForm($Payment)
    {
        return $this->gate->cnb_form(array(
            'version'        => '3',
            'amount'         => $Payment->Amount,
            'currency'       => $Payment->Currency,
            'description'    => t("Оплата за заказ - ", true).$Payment->id,
            'order_id'       => $Payment->id,
            'result_url'     => $this->resultUrl,
            'server_url'     => url_build('liqpay','status')
        ));
    }

    public function __status()
    {
        if (isset($_POST['data'])  )
        {
            $data = $_POST['data'];
            $hash = $this->gate->str_to_sign($this->privateKey.$data.$this->privateKey);
            $result = json_decode(base64_decode($data));

            if ($hash == $_POST['signature'])
            {
                $status = Payment::STATUS_FAIL;
                $comment = '';
                switch ((string)$result->status) {
                    case 'success':
                        $status = Payment::STATUS_SUCCESS;
                        $comment = 'Оплата прошла успешно';
                        break;
                    case 'wait_secure':
                        $status = Payment::STATUS_WAIT_SECURE;
                        break;
                    case 'failure':
                        $status = Payment::STATUS_FAIL;
                        $comment = 'Ошибка оплаты';
                        break;
                }

                Event::fire('commerce.update.status', array('Payment', (string)$result->order_id, $status, $comment));
            }
        }
        url()->redirect();
    }
}