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
        $parms = $request->get_params();
        $copecart_signature = $_SERVER['X-Copecart-Signature'];
        $generated_signature = base64_encode(hash_hmac('sha256', $parms, self::IPN_PASSPHRASE, TRUE));
        die($generated_signature);
        if ($copecart_signature == $generated_signature) {
            // IPN message is varified
        }

    }

    public function process_copecart_ipn($request)
    {
        $parms = $request->get_params();
        $this->safe_to_db('', '', '', '', $parms);
        die('OK');
    }


    public
    function permission_check_ipn($request)
    {
        $parms = $request->get_params();
        $received_signature = $parms['sha_sign'];
        $expected_signature = $this->digistore_signature(self::IPN_PASSPHRASE, $parms);
        return $received_signature == $expected_signature;
        //return true;
    }

    /**
     * Throw error if Save to DB not working
     *
     *  ## Error Codes:
     *  ### general Events 1xx
     * <ul>
     *      <li>100 connection_test</li>
     *      <li>110 on_rebill_cancelled</li>
     *      <li>120 on_affiliation</li>
     *      <li>130 Unknown event</li>
     * </ul>
     *  ### cancel Events 2xx
     * <ul>
     *      <li>200 on_payment_missed</li>
     *      <li>210 on_refund</li>
     *      <li>220 on_chargeback</li>
     * </ul>
     *  ### payed Events 3xx
     * <ul>
     *      <li>300 on_rebill_resumed</li>
     *      <li>310 on_payment</li>
     * </ul>
     *
     * @param int $error_code
     */

    static function db_fail(int $error_code = 0)
    {
        die('DB save failed Code: ' . $error_code);
    }

    public
    function process_ipn($request)
    {
        $parms = $request->get_params();
        $ipn = new DigiStoreResponse($parms);
        $event = $ipn->get_param('event');
        $api_mode = $ipn->get_param('api_mode');
        $user_id = $ipn->get_user_id();
        switch ($event) {
            case 'connection_test':
            {
                //$this->change_user_status($user_id, true);
                if ($this->safe_to_db('', $user_id, '', 'connection_test', $parms)) {
                    die('OK');
                } else {
                    $this->db_fail(100);
                }
            }

            case 'on_payment':
            {

                $order_id = $ipn->get_param('order_id');

                // note: An order has one order_id and may consist of multiple order items.
                //       For each order item, an ipn call is performed.


                //$product_id = $ipn->get_param('product_id');
                //$product_name = $ipn->get_param('product_name');
                //$billing_type = $ipn->get_param('billing_type');

                /*switch ($billing_type) {
                    case 'single_payment':
                        $number_payments = 0;
                        $pay_sequence_no = 0;
                        break;

                    case 'installment':
                        $number_payments = $ipn->get_param('order_item_number_of_installments');
                        $pay_sequence_no = $ipn->get_param('pay_sequence_no');
                        break;

                    case 'subscription':
                        $number_payments = 0;
                        $pay_sequence_no = $ipn->get_param('pay_sequence_no');
                        break;
                }*/


                /*$first_name = $ipn->get_param('address_first_name');
                $last_name = $ipn->get_param('address_last_name');

                // Note: not all orders have the complete address.
                //       To make the complete address a requirement on the orderform,
                //       edit the product settings in Digistore24
                $address_street = $ipn->get_param('address_street_name');
                $address_street_no = $ipn->get_param('address_street_number');
                $address_city = $ipn->get_param('address_city');
                $address_state = $ipn->get_param('address_state');
                $address_zipcode = $ipn->get_param('address_zipcode');
                $address_phone_no = $ipn->get_param('address_phone_no');*/

                $is_test_mode = $api_mode != 'live';

                // EDIT HERE: Add the php code to store your order in your database

                $this->change_user_status($user_id, true);

                if ($this->safe_to_db($order_id, $user_id, $ipn->get_user_mail(), 'on_payment', $parms)) {
                    die('OK');
                } else {
                    $this->db_fail(310);
                }

                $do_transfer_member_ship_data_to_digistore = true; // if true, membership access data (or other data) may be displayed on the order confirmation email, receipt page and so on
                if (!$do_transfer_member_ship_data_to_digistore) {
                    die('OK');
                } else {
                    $username = get_user_by('ID', $user_id)->user_login;
                    $password = '*********';
                    $login_url = get_site_url(null, '/login', 'https');
                    //$thankyou_url = 'http://domain.com/thank_you';

                    //$show_on = 'all'; // e.g.: 'all',  'invoice', 'invoice,receipt_page,order_confirmation_email' - seperate multiple targets by comma
                    // $hide_on = 'invoice'; // e.g.: 'none', 'invoice', 'invoice,receipt_page,order_confirmation_email' - seperate multiple targets by comma
                    $headline = 'Deine Login Daten:'; // displayed above the membership access data
                    // Add as much data as you like - all data are optional.
                    // If show_on/hide_on is omitted, the data is displayed in any lcation
                    // If headline is omitted, a generic headline is used (like "Your license data").
                    // You also may add your own data (key value pairs), e.g. Note: Please contact me to schedule a call!
                    // IMPORTANT: if you add these data, Digistore24 will ONLY  mail them to the user if
                    //            the IPN timing is set to "Before redirect to thankyou page"
                    //            AND if "group by upsells" is set to NO. Otherwise they are only displayed on the
                    //            digistore thankyou page
                    die("OK
username: $username
password: $password
loginurl: $login_url
headline: $headline");
                }
            }

            case 'on_rebill_resumed':
            {
                $order_id = $ipn->get_param('order_id');
                $user_mail = $ipn->get_param('email');

                $is_test_mode = $api_mode != 'live';

                $this->change_user_status($user_id, true);
                if ($this->safe_to_db($order_id, $user_id, $user_mail, 'on_rebill_resumed', $parms)) {
                    die('OK');
                } else {
                    $this->db_fail(300);
                }
            }

            case 'on_payment_missed':
            {
                $order_id = $ipn->get_param('order_id');
                $user_mail = $ipn->get_param('email');

                $is_test_mode = $api_mode != 'live';

                $this->change_user_status($user_id, false);
                if ($this->safe_to_db($order_id, $user_id, $user_mail, 'on_payment_missed', $parms)) {
                    die('OK');
                } else {
                    $this->db_fail(200);
                }
            }

            case 'on_refund':
            {
                $order_id = $ipn->get_param('order_id');
                $user_mail = $ipn->get_param('email');

                $is_test_mode = $api_mode != 'live';

                $this->change_user_status($user_id, false);
                if ($this->safe_to_db($order_id, $user_id, $user_mail, 'on_refund', $parms)) {
                    die('OK');
                } else {
                    $this->db_fail(210);
                }
            }

            case 'on_chargeback':
            {
                $order_id = $ipn->get_param('order_id');
                $user_mail = $ipn->get_param('email');

                $is_test_mode = $api_mode != 'live';

                $this->change_user_status($user_id, false);
                if ($this->safe_to_db($order_id, $user_id, $user_mail, 'on_chargeback', $parms)) {
                    die('OK');
                } else {
                    $this->db_fail(220);
                }
            }

            case 'payment_denial':
            {
                $order_id = $ipn->get_param('order_id');
                $user_mail = $ipn->get_param('email');

                $is_test_mode = $api_mode != 'live';

                $this->change_user_status($user_id, false);
                if ($this->safe_to_db($order_id, $user_id, $user_mail, 'payment_denial', $parms)) {
                    die('OK');
                } else {
                    $this->db_fail(220);
                }
            }

            case 'on_rebill_cancelled':
            {
                $order_id = $ipn->get_param('order_id');
                $user_mail = $ipn->get_param('email');

                $is_test_mode = $api_mode != 'live';

                // EDIT HERE: Add the php code to handle stopped rebillings.
                // IMPORTANT: This event is sent at the point of time, when the customer's
                //            cancellation of therebilling is processed. Please cancel the
                //            access to the paid conentent using the "on_payment_missed" event.
                if ($this->safe_to_db($order_id, $user_id, $user_mail, 'on_rebill_cancelled', $parms)) {
                    die('OK');
                } else {
                    $this->db_fail(110);
                }
            }

            case 'on_affiliation':
            {
                $user_mail = $ipn->get_param('email');
                /*$email = $ipn->get_param('email');
                $digistore_id = $ipn->get_param('affiliate_name');
                $promolink = $ipn->get_param('affiliate_link');
                $language = $ipn->get_param('language');

                $first_name = $ipn->get_param('address_first_name');
                $last_name = $ipn->get_param('address_last_name');

                $address_street = $ipn->get_param('address_street_name');
                $address_street_no = $ipn->get_param('address_street_number');
                $address_city = $ipn->get_param('address_city');
                $address_state = $ipn->get_param('address_state');
                $address_zipcode = $ipn->get_param('address_zipcode');
                $address_phone_no = $ipn->get_param('address_phone_no');

                $product_id = $ipn->get_param('product_id');
                $product_name = $ipn->get_param('product_name');
                $merchant_id = $ipn->get_param('merchant_id');*/

                $is_test_mode = $api_mode != 'live';

                // EDIT HERE: Add the php code to handle new affiliations
                if ($this->safe_to_db($ipn->get_param('merchant_id'), $user_id, $user_mail, 'on_affiliation', $parms)) {
                    die('OK');
                } else {
                    $this->db_fail(120);
                }
            }

            default:
            {
                // Unknown event

                $user_mail = $ipn->get_param('email');
                if ($this->safe_to_db('', $user_id, $user_mail, 'unknown_event', $parms)) {
                    die('OK');
                } else {
                    $this->db_fail(130);
                }
            }
        }
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

    public
    function safe_to_db($order_id, $user_id = 0, $email = '', $ipn_event, $params = array())
    {
        global $wpdb;

        if (!is_serialized($params)) {
            $params = maybe_serialize($params);
        }
        //die($data);

        $table = $wpdb->prefix . 'mbqb_orders';
        $data = array(
            'order_id' => $order_id,
            'user_id' => $user_id,
            'email' => $email,
            'ipn_event' => $ipn_event,
            'data' => (string)$params,
        );
        $format = array('%s', '%d', '%s', '%s', '%s');

        $wpdb->insert($table, $data, $format);

        return $wpdb->insert_id;
    }

    public
    function digistore_signature($sha_passphrase, $parameters, $convert_keys_to_uppercase = false, $do_html_decode = false)
    {
        $algorythm = 'sha512';
        $sort_case_sensitive = true;

        if (!$sha_passphrase) {
            return 'no_signature_passphrase_provided';
        }

        unset($parameters['sha_sign']);
        unset($parameters['SHASIGN']);

        if ($convert_keys_to_uppercase) {
            $sort_case_sensitive = false;
        }

        $keys = array_keys($parameters);
        $keys_to_sort = array();
        foreach ($keys as $key) {
            $keys_to_sort[] = $sort_case_sensitive ? $key : strtoupper($key);
        }

        array_multisort($keys_to_sort, SORT_STRING, $keys);

        $sha_string = "";
        foreach ($keys as $key) {
            $value = $parameters[$key];

            if ($do_html_decode) {
                $value = html_entity_decode($value);
            }

            $is_empty = !isset($value) || $value === "" || $value === false;
            if ($is_empty) {
                continue;
            }

            $upperkey = $convert_keys_to_uppercase ? strtoupper($key) : $key;

            $sha_string .= "$upperkey=$value$sha_passphrase";
        }

        $sha_sign = strtoupper(hash($algorythm, $sha_string));

        return $sha_sign;
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

    public string $buyer_mail;

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
        if(isset($this->params['metadata'])){
            $this->set_id_by_meta();
        } else {
            $this->set_id_by_mail();
        }

    }

    private function set_id_by_mail()
    {
        $user = get_user_by('email', $this->buyer_mail);
        if ($user) {
            $this->wp_user_id = $user->ID;
        } else {
            $this->wp_user_id = 0;
        }
    }

    private function set_id_by_meta(){
        $decoded_meta = $this->decode($this->params['metadata']);
        $tmp_id = (int) preg_replace('/\D/', '', $decoded_meta);
        if (get_userdata($tmp_id) !== false) {
            $this->wp_user_id = $tmp_id;
        } else {
            $this->set_id_by_mail();
        }
    }

    private function decode($value) {
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

    private function encode($value) {
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

class DigiStoreResponse
{
    /**
     * Store all Parameter for current response
     *
     * @var array
     */
    protected array $params;

    protected int $user_id;

    protected string $user_mail;

    private function set_id_by_mail($mail)
    {
        $user = get_user_by('email', $mail);
        if ($user) {
            $this->user_id = $user->ID;
        } else {
            $this->user_id = 0;
        }
    }

    public function __construct(array $params)
    {
        unset($params['sha_sign']);
        unset($params['SHASIGN']);
        $this->params = $params;

        $email = '';
        if (isset($params['email'])) {
            $email = $params['email'];
        } elseif (isset($params['buyer_email'])) {
            $email = $params['buyer_email'];
        }

        $this->user_mail = $email;
        //$params['email'] = isset($params['email']) ? $params['email'] : '-1';

        if (isset($params['custom'])) {
            $tmp_id = (int)preg_replace('/\D/', '', $params['custom']);
            if (get_userdata($tmp_id) !== false) {
                $this->user_id = $tmp_id;
            } else {
                $this->set_id_by_mail($email);
            }
        } else {
            $this->set_id_by_mail($email);
        }

    }

    public function get_user_mail(): string
    {
        return $this->user_mail;
    }

    /**
     * Return WP User ID
     *
     * @return int
     */

    public function get_user_id(): int
    {
        return $this->user_id;
    }

    /**
     * Get all Parameters
     *
     * @return array
     */
    public function get_params(): array
    {
        return $this->params;
    }

    /**
     * Get Parameter of current IPN
     *
     * @param string $param
     * @return string
     */
    public function get_param(string $param): string
    {
        if (isset($this->params[$param])) {
            return (string)$this->params[$param];
        } else {
            return '';
        }
    }
}