<?php
/**
 * Created by Vitaly Iegorov <egorov@samsonos.com>
 * on 13.09.14 at 18:35
 */
namespace samsonos\commerce\liqpay;

use samson\core\CompressableService;
use samson\core\Config;


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

    private $gate;



    public function init()
    {
        parent::init();

        $this->gate = new \LiqPay($this->publicKey, $this->privateKey);
        if (!isset($this->resultUrl)) {
            $this->resultUrl = url_build('/');
        }
    }

    public function createForm($Payment)
    {
        return $this->gate->cnb_form(array(
            'version'        => '3',
            'amount'         => $Payment->Amount,
            'currency'       => $Payment->Currency,
            'description'    => t("Оплата за заказ - ").$Payment->id,
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
                $paymentId = (string)$result->order_id;

                if( (string)$result->status == 'success'){

                }
                if((string) $result->status == 'wait_secure'){

                }

                if((string) $result->status == 'failure'){

                }
            }
        }
        url()->redirect();
    }
}