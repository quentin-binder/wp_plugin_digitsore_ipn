<?php

class CopeCartIPN extends WP_REST_Controller
{

    /**
     * Register the routes for the objects of the controller.
     */
    public function register_routes()
    {
        register_rest_route('digistore/v1', '/ipn', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'process_ipn'),
                'permission_callback' => array($this, 'permission_check_ipn'),
                'args' => array(),
            )
        ));
        register_rest_route('copecart/v1', '/ipn', array(
            array(
                'methods' => WP_REST_Server::ALLMETHODS,
                'callback' => array($this, 'process_copecart_ipn'),
                'permission_callback' => array($this, 'permission_check_copecart_ipn'),
                'args' => array(),
            )
        ));
    }

    private
    const IPN_PASSPHRASE = 'UBRbtPYDhGCpGEQiPbZJzAgOfXZiwPtUTaoJClkx';

    public function permission_check_copecart_ipn($request)
    {
        return true;
        /*$parms = $request->get_params();
        $copecart_signature = $_SERVER['X-Copecart-Signature'];
        $generated_signature = base64_encode(hash_hmac('sha256', $parms, self::IPN_PASSPHRASE, TRUE));
        die($generated_signature);
        if ($copecart_signature == $generated_signature) {
            // IPN message is varified
        }*/

    }

    public function process_copecart_ipn($request)
    {
        $ipn = new CopeCartResponse($request);
        switch ($ipn->event_type) {
            case 'payment.made':
            {
                $this->change_user_status($ipn->wp_user_id,true);
                break;
            }
            case 'payment.failed':
            case 'payment.refunded':
            case 'payment.charged_back':
            {
                $this->change_user_status($ipn->wp_user_id,false);
                break;
            }
            default:
            {

            }
        }
        die('OK');
    }



    public
    function change_user_status($user_id, $approved = false)
    {
        if (user_can($user_id, 'um_editor_reis') || user_can($user_id, 'um_editor_lion') || user_can($user_id, 'administrator')) {
            die('Has already access');
        } elseif ($user_id == 0) {
            die('User Invalid');
        }
        if ($approved) {
            get_user_by('ID', $user_id)->set_role('um_app_kunde');
        } else {
            get_user_by('ID', $user_id)->set_role('um_kunde');
        }


    }

}

class CopeCartResponse
{
    /**
     * Store all Parameter for current response
     *
     * @var array
     */
    protected array $params;

    /**
     * Event Type
     *
     * @var string
     */

    public string $event_type;

    /**
     * Buyers E-Mail Adress
     *
     * @var string
     */

    public string $buyer_mail;

    /**
     * Kunden User ID
     * @var WP
     */

    public string $wp_user_id;


    /**
     * CopeCartResponse constructor.
     * @param $request
     */

    public function __construct($request)
    {
        $this->params = $request->get_params();

        $this->event_type = $this->params['event_type'];
        $this->buyer_mail = $this->params['buyer_email'];
        if (isset($this->params['metadata'])) {
            $this->set_id_by_meta();
        } else {
            $this->set_id_by_mail();
        }

        global $wpdb;

        if (!is_serialized($this->params)) {
            $db_params = maybe_serialize($this->params);
        }
        //die($data);

        $table = $wpdb->prefix . 'mbqb_orders';
        $data = array(
            'order_id' => $this->params['order_id'],
            'user_id' => $this->wp_user_id,
            'email' => $this->buyer_mail,
            'ipn_event' => $this->event_type,
            'data' => (string)$db_params,
        );
        $format = array('%s', '%d', '%s', '%s', '%s');

        $wpdb->insert($table, $data, $format);

    }

    /**
     * Set user id by e-mail parameter
     */

    private function set_id_by_mail()
    {
        $user = get_user_by('email', $this->buyer_mail);
        if ($user) {
            $this->wp_user_id = $user->ID;
        } else {
            $this->wp_user_id = 0;
        }
    }

    /**
     * Set user id by metadata
     */

    private function set_id_by_meta()
    {
        $decoded_meta = $this->decode($this->params['metadata']);
        $tmp_id = (int)preg_replace('/\D/', '', $decoded_meta);
        if (get_userdata($tmp_id) !== false) {
            $this->wp_user_id = $tmp_id;
        } else {
            $this->set_id_by_mail();
        }
    }

    private function decode($value)
    {
        if (!$value) {
            return false;
        }

        $key = sha1('UBRbtPYDhGCpGEQiPbZJzAgOfXZiwPtUTaoJClkx');
        $strLen = strlen($value);
        $keyLen = strlen($key);
        $j = 0;
        $decrypttext = '';

        for ($i = 0; $i < $strLen; $i += 2) {
            $ordStr = hexdec(base_convert(strrev(substr($value, $i, 2)), 36, 16));
            if ($j == $keyLen) {
                $j = 0;
            }
            $ordKey = ord(substr($key, $j, 1));
            $j++;
            $decrypttext .= chr($ordStr - $ordKey);
        }

        return $decrypttext;
    }

    private function encode($value)
    {
        if (!$value) {
            return false;
        }

        $key = sha1('UBRbtPYDhGCpGEQiPbZJzAgOfXZiwPtUTaoJClkx');
        $strLen = strlen($value);
        $keyLen = strlen($key);
        $j = 0;
        $crypttext = '';

        for ($i = 0; $i < $strLen; $i++) {
            $ordStr = ord(substr($value, $i, 1));
            if ($j == $keyLen) {
                $j = 0;
            }
            $ordKey = ord(substr($key, $j, 1));
            $j++;
            $crypttext .= strrev(base_convert(dechex($ordStr + $ordKey), 16, 36));
        }

        return $crypttext;
    }

}