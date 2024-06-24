<?php
/*
Plugin Name: درگاه پرداخت زیبال برای Restrict Content Pro
Version: 2.0
Requires at least: 4.0
Description: درگاه پرداخت <a href="http://www.zibal.ir/" target="_blank"> زیبال </a> برای افزونه Restrict Content Pro
Plugin URI: https://docs.zibal.ir
Author: Yahya Kangi
Author URI: http://zibal.ir
 */
if (!defined('ABSPATH')) {
    exit;
}
require_once 'ZIBAL_Session.php';
if (!class_exists('RCP_Zibal')) {
    class RCP_Zibal
    {

        #webhooks
        public function process_webhooks()
        {}
        /**
         * Use this space to enqueue any extra JavaScript files.
         *
        access public
        @return void
         */
        #script
        public function scripts()
        {}
        /**
         * Load any extra fields on the registration form
         *
         * @access public
         * @return string
         */
        #fields
        public function fields()
        {
            /* Example for loading the credit card fields :
        ob_start();
        rcp_get_template_part( 'card-form' );
        return ob_get_clean();
         */
        }

        #validateFields
        public function validate_fields()
        {
            /* Example :
        if ( empty( $_POST['rcp_card_cvc'] ) ) {
        rcp_errors()->add( 'missing_card_code', __( 'The security code you have entered is invalid', 'rcp' ), 'register' );
        }
         */
        }

        #supports

        // public $supports = array();
        public function supports($item = '')
        {
            return;
        }
        /**
         * Generate a transaction ID
         *
         * Used in the manual payments gateway.
         *
         * @return string
         */
        public function __construct()
        {
            add_action('init', array($this, 'Zibal_Verify'));
            add_action('rcp_payments_settings', array($this, 'Zibal_Setting'));
            add_action('rcp_gateway_Zibal', array($this, 'Zibal_Request'));
            add_filter('rcp_payment_gateways', array($this, 'Zibal_Register'));
            add_filter('rcp_currencies', array($this, 'RCP_IRAN_Currencies'));
            add_filter('rcp_irr_currency_filter_before', array($this, 'RCP_IRR_Before'), 10, 3);
            add_filter('rcp_irr_currency_filter_after', array($this, 'RCP_IRR_After'), 10, 3);
            add_filter('rcp_irt_currency_filter_before', array($this, 'RCP_IRT_Before'), 10, 3);
            add_filter('rcp_irt_currency_filter_after', array($this, 'RCP_IRT_After'), 10, 3);
        }

        public function RCP_IRR_Before($formatted_price, $currency_code, $price)
        {
            return __('ریال', 'rcp') . ' ' . $price;
        }

        public function RCP_IRR_After($formatted_price, $currency_code, $price)
        {
            return $price . ' ' . __('ریال', 'rcp');
        }

        public function RCP_IRT_Before($formatted_price, $currency_code, $price)
        {
            return __('تومان', 'rcp') . ' ' . $price;
        }

        public function RCP_IRT_After($formatted_price, $currency_code, $price)
        {
            return $price . ' ' . __('تومان', 'rcp');
        }

        public function RCP_IRAN_Currencies($currencies)
        {
            unset($currencies['RIAL'], $currencies['IRR'], $currencies['IRT']);
            $iran_currencies = array(
                'IRT' => __('تومان ایران (تومان)', 'rcp'),
                'IRR' => __('ریال ایران (ریال)', 'rcp'),
            );

            return array_unique(array_merge($iran_currencies, $currencies));
        }

        public function Zibal_Register($gateways)
        {
            global $rcp_options;

            if (version_compare(RCP_PLUGIN_VERSION, '2.1.0', '<')) {
                $gateways['Zibal'] = isset($rcp_options['zibal_name']) ? $rcp_options['zibal_name'] : __('زیبال', 'rcp_zibal');
            } else {
                $gateways['Zibal'] = array(
                    'label' => isset($rcp_options['zibal_name']) ? $rcp_options['zibal_name'] : __('زیبال', 'rcp_zibal'),
                    'admin_label' => isset($rcp_options['zibal_name']) ? $rcp_options['zibal_name'] : __('زیبال', 'rcp_zibal'),
                    'class' => 'rcp_zibal',
                );
            }

            return $gateways;
        }

        public function Zibal_Setting($rcp_options)
        {
            ?>
            <hr/>
            <table class="form-table">
                <?php do_action('RCP_Zibal_before_settings', $rcp_options);?>
                <tr valign="top">
                    <th colspan=2><h3><?php _e('تنظیمات زیبال', 'rcp_zibal');?></h3></th>
                </tr>
                <tr valign="top">
                    <th>
                        <label for="rcp_settings[zibal_merchant]"><?php _e('مرچنت زیبال', 'rcp_zibal');?></label>
                    </th>
                    <td>
                        <input class="regular-text" id="rcp_settings[zibal_merchant]" style="width: 300px;"
                               name="rcp_settings[zibal_merchant]"
                               value="<?php if (isset($rcp_options['zibal_merchant'])) {
                echo $rcp_options['zibal_merchant'];
            }?>"/>
                    </td>
                </tr>
                <tr valign="top">
                    <th>
                        <label for="rcp_settings[zibal_query_name]"><?php _e('نام لاتین درگاه', 'rcp_zibal');?></label>
                    </th>
                    <td>
                        <input class="regular-text" id="rcp_settings[zibal_query_name]" style="width: 300px;"
                               name="rcp_settings[zibal_query_name]"
                               value="<?php echo isset($rcp_options['zibal_query_name']) ? $rcp_options['zibal_query_name'] : 'Zibal'; ?>"/>
                        <div class="description"><?php _e('این نام در هنگام بازگشت از بانک در آدرس بازگشت از بانک نمایان خواهد شد . از به کاربردن حروف زائد و فاصله جدا خودداری نمایید . این نام باید با نام سایر درگاه ها متفاوت باشد .', 'rcp_zibal');?></div>
                    </td>
                </tr>
                <tr valign="top">
                    <th>
                        <label for="rcp_settings[zibal_name]"><?php _e('نام نمایشی درگاه', 'rcp_zibal');?></label>
                    </th>
                    <td>
                        <input class="regular-text" id="rcp_settings[zibal_name]" style="width: 300px;"
                               name="rcp_settings[zibal_name]"
                               value="<?php echo isset($rcp_options['zibal_name']) ? $rcp_options['zibal_name'] : __('زیبال', 'rcp_zibal'); ?>"/>
                    </td>
                </tr>
                <tr valign="top">
                    <th>
                        <label><?php _e('تذکر ', 'rcp_zibal');?></label>
                    </th>
                    <td>
                        <div class="description"><?php _e('از سربرگ مربوط به ثبت نام در تنظیمات افزونه حتما یک برگه برای بازگشت از بانک انتخاب نمایید . ترجیحا نامک برگه را لاتین قرار دهید .<br/> نیازی به قرار دادن شورت کد خاصی در برگه نیست و میتواند برگه ی خالی باشد .', 'rcp_zibal');?></div>
                    </td>
                </tr>
                <?php do_action('RCP_Zibal_after_settings', $rcp_options);?>
            </table>
            <?php
}

        public function Zibal_Request($subscription_data)
        {

            $new_subscription_id = get_user_meta($subscription_data['user_id'], 'rcp_subscription_level', true);
            if (!empty($new_subscription_id)) {
                update_user_meta($subscription_data['user_id'], 'rcp_subscription_level_new', $new_subscription_id);
            }

            $old_subscription_id = get_user_meta($subscription_data['user_id'], 'rcp_subscription_level_old', true);
            update_user_meta($subscription_data['user_id'], 'rcp_subscription_level', $old_subscription_id);

            global $rcp_options;
            ob_start();
            $query = isset($rcp_options['zibal_query_name']) ? $rcp_options['zibal_query_name'] : 'Zibal';
            $amount = str_replace(',', '', $subscription_data['price']);
            //$amount = $subscription_data['price'] + $subscription_data['fee'];

            $zibal_payment_data = array(
                'user_id' => $subscription_data['user_id'],
                'subscription_name' => $subscription_data['subscription_name'],
                'subscription_key' => $subscription_data['key'],
                'amount' => $amount,
            );

            $zibal_session = ZIBAL_Session::get_instance();
            @session_start();
            $zibal_session['zibal_payment_data'] = $zibal_payment_data;
            $_SESSION["zibal_payment_data"] = $zibal_payment_data;

            //Action For Zibal or RCP Developers...
            do_action('RCP_Before_Sending_to_Zibal', $subscription_data);

            if (!in_array($rcp_options['currency'], array(
                'irr',
                'IRR',
                'ریال',
                __('ریال', 'rcp'),
                __('ریال', 'rcp_zibal'),
            ))) {
                $amount = $amount * 10;
            }

            //Start of Zibal
            $MerchantID = isset($rcp_options['zibal_merchant']) ? $rcp_options['zibal_merchant'] : '';
            $Amount = intval($amount);
            $Email = isset($subscription_data['user_email']) ? $subscription_data['user_email'] : '-';
            $CallbackURL = add_query_arg('gateway', $query, $subscription_data['return_url']);
            $Description = sprintf(__('خرید اشتراک %s برای کاربر %s', 'rcp_zibal'), $subscription_data['subscription_name'], $subscription_data['user_name']);
            $Mobile = '-';

            $Description = apply_filters('RCP_Zibal_Description', $Description, $subscription_data);
            $Mobile = apply_filters('RCP_Mobile', $Mobile, $subscription_data);

            $data = array(
                'merchant' => $MerchantID,
                'amount' => $Amount,
                'callbackUrl' => $CallbackURL,
				'description' => $Description);
			
            $result = $this->postToZibal('request', $data);
            $result = (array)$result;

            if (isset($result) && $result['result'] == 100) {
				header('Location: https://gateway.zibal.ir/start/' . $result['trackId']);
				exit;
            } else {
                wp_die(sprintf(__('متاسفانه پرداخت به دلیل خطای زیر امکان پذیر نمی باشد . <br/><b> %s </b>', 'rcp_zibal'), $this->Fault($result['result'])));
            }
            //End of Zibal

            exit;
        }

        public function Zibal_Verify()
        {

            if (!isset($_GET['gateway'])) {
                return;
            }

            if (!class_exists('RCP_Payments')) {
                return;
            }

            global $rcp_options, $wpdb, $rcp_payments_db_name;
            @session_start();
            $zibal_session = ZIBAL_Session::get_instance();
            if (isset($zibal_session['zibal_payment_data'])) {
                $zibal_payment_data = $zibal_session['zibal_payment_data'];
            } else {
                $zibal_payment_data = isset($_SESSION["zibal_payment_data"]) ? $_SESSION["zibal_payment_data"] : '';
            }

            $query = isset($rcp_options['zibal_query_name']) ? $rcp_options['zibal_query_name'] : 'Zibal';

            if (($_GET['gateway'] == $query) && $zibal_payment_data) {

                $user_id = $zibal_payment_data['user_id'];
                $user_id = intval($user_id);
                $subscription_name = $zibal_payment_data['subscription_name'];
                $subscription_key = $zibal_payment_data['subscription_key'];
                $amount = $zibal_payment_data['amount'];

                /*
                $subscription_price = intval(number_format( (float) rcp_get_subscription_price( rcp_get_subscription_id( $user_id ) ), 2)) ;
                 */

                $payment_method = isset($rcp_options['zibal_name']) ? $rcp_options['zibal_name'] : __('زیبال طلایی', 'rcp_zibal');

                $new_payment = 1;
                if ($wpdb->get_results($wpdb->prepare("SELECT id FROM " . $rcp_payments_db_name . " WHERE `subscription_key`='%s' AND `payment_type`='%s';", $subscription_key, $payment_method))) {
                    $new_payment = 0;
                }

                unset($GLOBALS['zibal_new']);
                $GLOBALS['zibal_new'] = $new_payment;
                global $new;
                $new = $new_payment;

                if ($new_payment == 1) {

                    //Start of Zibal
                    $MerchantID = isset($rcp_options['zibal_merchant']) ? $rcp_options['zibal_merchant'] : '';
                    $Amount = intval($amount);
                    if (!in_array($rcp_options['currency'], array(
                        'irr',
                        'IRR',
                        'ریال',
                        __('ریال', 'rcp'),
                        __('ریال', 'rcp_zibal'),
                    ))) {
                        $Amount = $Amount * 10;
                    }

                    $Authority = isset($_GET['Authority']) ? sanitize_text_field($_GET['Authority']) : '';

                    $__param = $Authority;
                    RCP_check_verifications(__CLASS__, $__param);

                    if (isset($_GET['status']) && $_GET['status'] == '2') {

						$data = array(
							'merchant' => $MerchantID,
							'trackId' => $_GET['trackId']
						);
						
                        $result = $this->postToZibal('verify', $data);
                        $result = (array)$result;

                        if ($result['result'] == 100 && $result['amount'] == $Amount) {
                            $payment_status = 'completed';
                            $fault = 0;
                            $transaction_id = $result['refNumber'];
                        } elseif ($result['result'] == 201) {
                            $payment_status = 'completed';
                            $fault = $result['result'];
                        } else {
                            $payment_status = 'failed';
                            $fault = $result['Status'];
                            $transaction_id = 0;
                        }
                    } else {
                        $payment_status = 'cancelled';
                        $fault = 0;
                        $transaction_id = 0;
                    }
                    //End of Zibal

                    unset($GLOBALS['zibal_payment_status']);
                    unset($GLOBALS['zibal_transaction_id']);
                    unset($GLOBALS['zibal_fault']);
                    unset($GLOBALS['zibal_subscription_key']);
                    $GLOBALS['zibal_payment_status'] = $payment_status;
                    $GLOBALS['zibal_transaction_id'] = $transaction_id;
                    $GLOBALS['zibal_subscription_key'] = $subscription_key;
                    $GLOBALS['zibal_fault'] = $fault;
                    global $zibal_transaction;
                    $zibal_transaction = array();
                    $zibal_transaction['zibal_payment_status'] = $payment_status;
                    $zibal_transaction['zibal_transaction_id'] = $transaction_id;
                    $zibal_transaction['zibal_subscription_key'] = $subscription_key;
                    $zibal_transaction['zibal_fault'] = $fault;

                    if ($payment_status == 'completed') {

                        $payment_data = array(
                            'date' => date('Y-m-d g:i:s'),
                            'subscription' => $subscription_name,
                            'payment_type' => $payment_method,
                            'subscription_key' => $subscription_key,
                            'amount' => $amount,
                            'user_id' => $user_id,
                            'transaction_id' => $transaction_id,
                        );

                        //Action For Zibal or RCP Developers...
                        do_action('RCP_Zibal_Insert_Payment', $payment_data, $user_id);

                        $rcp_payments = new RCP_Payments();
                        RCP_set_verifications($rcp_payments->insert($payment_data), __CLASS__, $__param);

                        $new_subscription_id = get_user_meta($user_id, 'rcp_subscription_level_new', true);
                        if (!empty($new_subscription_id)) {
                            update_user_meta($user_id, 'rcp_subscription_level', $new_subscription_id);
                        }
                        $membership = (array) rcp_get_memberships()[0];
                        $old_status_level = $membership;
                        $replace = str_replace('\u0000*\u0000', '', json_encode($old_status_level));
                        $replace = json_decode($replace, true);
                        $status = $replace['status'];
                        $idMemberShip = (int) $replace['id'];
                        $arrayMember = array(
                            'status' => 'active',
                        );
                        if ($status == 'pending') {
                            rcp_update_membership($idMemberShip, $arrayMember);
                        } else {
                            rcp_set_status($user_id, 'active');
                        }

                        if (version_compare(RCP_PLUGIN_VERSION, '2.1.0', '<')) {
                            rcp_email_subscription_status($user_id, 'active');
                            if (!isset($rcp_options['disable_new_user_notices'])) {
                                wp_new_user_notification($user_id);
                            }
                        }

                        update_user_meta($user_id, 'rcp_payment_profile_id', $user_id);

                        update_user_meta($user_id, 'rcp_signup_method', 'live');
                        update_user_meta($user_id, 'rcp_recurring', 'no');

                        $subscription = rcp_get_subscription_details(rcp_get_subscription_id($user_id));
                        $member_new_expiration = date('Y-m-d H:i:s', strtotime('+' . $subscription->duration . ' ' . $subscription->duration_unit . ' 23:59:59'));
                        rcp_set_expiration_date($user_id, $member_new_expiration);
                        delete_user_meta($user_id, '_rcp_expired_email_sent');

                        $log_data = array(
                            'post_title' => __('تایید پرداخت', 'rcp_zibal'),
                            'post_content' => __('پرداخت با موفقیت انجام شد . کد تراکنش : ', 'rcp_zibal') . $transaction_id . __(' .  روش پرداخت : ', 'rcp_zibal') . $payment_method,
                            'post_parent' => 0,
                            'log_type' => 'gateway_error',
                        );

                        $log_meta = array(
                            'user_subscription' => $subscription_name,
                            'user_id' => $user_id,
                        );

                        $log_entry = WP_Logging::insert_log($log_data, $log_meta);

                        //Action For Zibal or RCP Developers...
                        do_action('RCP_Zibal_Completed', $user_id);
                    }

                    if ($payment_status == 'cancelled') {

                        $log_data = array(
                            'post_title' => __('انصراف از پرداخت', 'rcp_zibal'),
                            'post_content' => __('تراکنش به دلیل انصراف کاربر از پرداخت ، ناتمام باقی ماند .', 'rcp_zibal') . __(' روش پرداخت : ', 'rcp_zibal') . $payment_method,
                            'post_parent' => 0,
                            'log_type' => 'gateway_error',
                        );

                        $log_meta = array(
                            'user_subscription' => $subscription_name,
                            'user_id' => $user_id,
                        );

                        $log_entry = WP_Logging::insert_log($log_data, $log_meta);

                        //Action For Zibal or RCP Developers...
                        do_action('RCP_Zibal_Cancelled', $user_id);

                    }

                    if ($payment_status == 'failed') {

                        $log_data = array(
                            'post_title' => __('خطا در پرداخت', 'rcp_zibal'),
                            'post_content' => __('تراکنش به دلیل خطای رو به رو ناموفق باقی باند :', 'rcp_zibal') . $this->Fault($fault) . __(' روش پرداخت : ', 'rcp_zibal') . $payment_method,
                            'post_parent' => 0,
                            'log_type' => 'gateway_error',
                        );

                        $log_meta = array(
                            'user_subscription' => $subscription_name,
                            'user_id' => $user_id,
                        );

                        $log_entry = WP_Logging::insert_log($log_data, $log_meta);

                        //Action For Zibal or RCP Developers...
                        do_action('RCP_Zibal_Failed', $user_id);

                    }

                }
                add_filter('the_content', array($this, 'Zibal_Content_After_Return'));
                //session_destroy();
            }
        }

        public function Zibal_Content_After_Return($content)
        {

            global $zibal_transaction, $new;

            $zibal_session = ZIBAL_Session::get_instance();
            @session_start();

            $new_payment = isset($GLOBALS['zibal_new']) ? $GLOBALS['zibal_new'] : $new;

            $payment_status = isset($GLOBALS['zibal_payment_status']) ? $GLOBALS['zibal_payment_status'] : $zibal_transaction['zibal_payment_status'];
            $transaction_id = isset($GLOBALS['zibal_transaction_id']) ? $GLOBALS['zibal_transaction_id'] : $zibal_transaction['zibal_transaction_id'];
            $fault = isset($GLOBALS['zibal_fault']) ? $this->Fault($GLOBALS['zibal_fault']) : $this->Fault($zibal_transaction['zibal_fault']);

            if ($new_payment == 1) {

                $zibal_data = array(
                    'payment_status' => $payment_status,
                    'transaction_id' => $transaction_id,
                    'fault' => $fault,
                );

                $zibal_session['zibal_data'] = $zibal_data;
                $_SESSION["zibal_data"] = $zibal_data;

            } else {
                if (isset($zibal_session['zibal_data'])) {
                    $zibal_payment_data = $zibal_session['zibal_data'];
                } else {
                    $zibal_payment_data = isset($_SESSION["zibal_data"]) ? $_SESSION["zibal_data"] : '';
                }

                $payment_status = isset($zibal_payment_data['payment_status']) ? $zibal_payment_data['payment_status'] : '';
                $transaction_id = isset($zibal_payment_data['transaction_id']) ? $zibal_payment_data['transaction_id'] : '';
                $fault = isset($zibal_payment_data['fault']) ? $this->Fault($zibal_payment_data['fault']) : '';
            }

            $message = '';

            if ($payment_status == 'completed') {
                $message = '<br/>' . __('پرداخت با موفقیت انجام شد . کد تراکنش : ', 'rcp_zibal') . $transaction_id . '<br/>';
            }

            if ($payment_status == 'cancelled') {
                $message = '<br/>' . __('تراکنش به دلیل انصراف شما نا تمام باقی ماند .', 'rcp_zibal');
            }

            if ($payment_status == 'failed') {
                $message = '<br/>' . __('تراکنش به دلیل خطای زیر ناموفق باقی باند :', 'rcp_zibal') . '<br/>' . $fault . '<br/>';
            }

            return $content . $message;
		}
		
		/**
		 * connects to zibal's rest api
		 * @param $path
		 * @param $parameters
		 * @return stdClass
		 */
		private function postToZibal($path, $parameters)
		{
			$url = 'https://gateway.zibal.ir/v1/'.$path;
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($parameters));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$response  = curl_exec($ch);
			curl_close($ch);
			return json_decode($response);
		}

        private function Fault($error)
        {
            $response = '';
            switch ($error) {

                case '100':
                    $response = __('	با موفقیت تایید شد.', 'rcp_zibal');
                    break;

                case '102':
                    $response = __('merchant یافت نشد.', 'rcp_zibal');
                    break;

                case '103':
                    $response = __('merchant غیرفعال', 'rcp_zibal');
                    break;

                case '104':
                    $response = __('merchant نامعتبر', 'rcp_zibal');
                    break;

                case '201':
                    $response = __('قبلا تایید شده.', 'rcp_zibal');
                    break;

                case '105':
                    $response = __('amount بایستی بزرگتر از 1,000 ریال باشد.', 'rcp_zibal');
                    break;

                case '106':
                    $response = __('callbackUrl نامعتبر می‌باشد. (شروع با http و یا https)', 'rcp_zibal');
                    break;

                case '113':
                    $response = __('amount مبلغ تراکنش از سقف میزان تراکنش بیشتر است.', 'rcp_zibal');
                    break;

                case '202':
                    $response = __('سفارش پرداخت نشده یا ناموفق بوده است.', 'rcp_zibal');
                    break;

                case '203':
                    $response = __('trackId نامعتبر می‌باشد.', 'rcp_zibal');
                    break;
            }

            return $response;
        }

    }
}
new RCP_Zibal();
if (!function_exists('change_cancelled_to_pending')) {
    add_action('rcp_set_status', 'change_cancelled_to_pending', 10, 2);
    function change_cancelled_to_pending($status, $user_id)
    {
        if ('cancelled' == $status) {
            rcp_set_status($user_id, 'expired');
        }

        return true;
    }
}

if (!function_exists('RCP_User_Registration_Data') && !function_exists('RCP_User_Registration_Data')) {
    add_filter('rcp_user_registration_data', 'RCP_User_Registration_Data');
    function RCP_User_Registration_Data($user)
    {
        $old_subscription_id = get_user_meta($user['id'], 'rcp_subscription_level', true);
        if (!empty($old_subscription_id)) {
            update_user_meta($user['id'], 'rcp_subscription_level_old', $old_subscription_id);
        }

        $user_info = get_userdata($user['id']);
        $old_user_role = implode(', ', $user_info->roles);
        if (!empty($old_user_role)) {
            update_user_meta($user['id'], 'rcp_user_role_old', $old_user_role);
        }

        return $user;
    }
}

if (!function_exists('RCP_check_verifications')) {
    function RCP_check_verifications($gateway, $params)
    {

        if (!function_exists('rcp_get_payment_meta_db_name')) {
            return;
        }

        if (is_array($params) || is_object($params)) {
            $params = implode('_', (array) $params);
        }
        if (empty($params) || trim($params) == '') {
            return;
        }

        $gateway = str_ireplace(array('RCP_', 'bank'), array('', ''), $gateway);
        $params = trim(strtolower($gateway) . '_' . $params);

        $table = rcp_get_payment_meta_db_name();

        global $wpdb;
        $check = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE meta_key='_verification_params' AND meta_value='%s'", $params));

        if (!empty($check)) {
            wp_die('وضعیت این تراکنش قبلا مشخص شده بود.');
        }
    }
}

if (!function_exists('RCP_set_verifications')) {
    function RCP_set_verifications($payment_id, $gateway, $params)
    {

        if (!function_exists('rcp_get_payment_meta_db_name')) {
            return;
        }

        if (is_array($params) || is_object($params)) {
            $params = implode('_', (array) $params);
        }
        if (empty($params) || trim($params) == '') {
            return;
        }

        $gateway = str_ireplace(array('RCP_', 'bank'), array('', ''), $gateway);
        $params = trim(strtolower($gateway) . '_' . $params);

        $table = rcp_get_payment_meta_db_name();

        global $wpdb;
        $wpdb->insert($table, array(
            'rcp_payment_id' => $payment_id,
            'meta_key' => '_verification_params',
            'meta_value' => $params,
        ), array('%d', '%s', '%s'));
    }
}
?>