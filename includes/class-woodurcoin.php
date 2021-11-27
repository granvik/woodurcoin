<?php

/**
 * Main plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @since      1.0.0
 * @package    Woodurcoin
 * @subpackage Woodurcoin/includes
 * @author     granvik
 * @class      WC_Gateway_Woodurcoin
 * @extends    WC_Payment_Gateway
 */
class WC_Gateway_Woodurcoin extends WC_Payment_Gateway
{
    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
        $this->setup_properties();
        $this->init_form_fields();
        $this->init_settings();
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->api_key = $this->get_option('api_key');
        $this->secret = $this->get_option('secret');
        $this->widget_id = $this->get_option('widget_id');
        $this->instructions = $this->get_option('instructions');
        $this->enable_for_methods = $this->get_option('enable_for_methods', array());
        $this->enable_for_virtual = $this->get_option('enable_for_virtual', 'yes') === 'yes';
        $this->icon = $this->get_option('icon');
        $this->assetId = empty($this->get_option('asset_id')) ? 'F1HoALyCDnvMbMxZcvWEVdtPXTY9BL9nbHnzSyjRLTt8' : $this->get_option('asset_id');
        $this->assetCode = empty($this->get_option('asset_code')) ? 'Durcoin' : $this->get_option('asset_code');
        $this->assetDescription = $this->get_option('asset_description');
        $this->address = $this->get_option('address');
        $this->decimals = empty($this->get_option('decimals')) ? 2 : (int)$this->get_option('decimals');
        $this->qr_show = $this->get_option('qr_show');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
        add_action('wp_enqueue_scripts', array($this, 'woodurcoin_pay_scripts'));
        add_action('before_woocommerce_pay', array($this, 'woodurcoin_pay_for_order'), 10);

        // Add asset as currency for site
        add_filter('woocommerce_currencies', array($this, 'woodurcoin_add_currency'));
        add_filter('woocommerce_currency_symbol', array($this, 'woodurcoin_add_currency_symbol'), 10, 2);
        add_filter('woocommerce_gateway_description', array($this, 'woodurcoin_description_fields'), 20, 2);
    }
    /**
     * Add asset as currency
     */
    function woodurcoin_add_currency($array)
    {
        if (!empty($this->assetCode) && strtoupper($this->assetCode) != 'WAVES') {
            if (empty($this->assetDescription)) {
                $array[$this->assetCode] = __($this->assetCode, 'woodurcoin');
            } else {
                $array[$this->assetCode] = __($this->assetDescription, 'woodurcoin');
            }
        } else {
            $array['WAVES'] = __('Durcoin token', 'woodurcoin');
        }
        return $array;
    }
    /**
     * Add asset currency symbol
     */
    function woodurcoin_add_currency_symbol($currency_symbol, $currency)
    {
        if (strtoupper($currency) == 'WAVES') {
            $currency_symbol = 'WAVES';
        } elseif (strtoupper($currency) == strtoupper($this->assetCode)) {
            $currency_symbol = strtoupper($this->assetCode);
        }
        return $currency_symbol;
    }

    public function woodurcoin_pay_scripts()
    {
        wp_enqueue_script('jquery');
        wp_enqueue_script('qrcode', 'https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js', array(), '444');
    }

    /**
     * Setup general properties for the gateway.
     */
    protected function setup_properties()
    {
        $this->id = 'woodurcoin';
        //$this->icon               = apply_filters( 'woocommerce_woodurcoin_icon', plugins_url('../assets/icon.png', __FILE__ ) );
        $this->method_title = __('Durcoin Payments', 'woodurcoin');
        $this->api_key = __('Add API Key', 'woodurcoin');
        $this->widget_id = __('Add Widget ID', 'woodurcoin');
        $this->method_description = __('Have your customers pay with Durcoin Payments.', 'woodurcoin');
        //$this->has_fields         = false;
        $this->has_fields = true; // ! direct gateway (i.e., one that takes payment on the actual checkout page) using payment_box
    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woodurcoin'),
                'label' => __('Enable Durcoin Payments', 'woodurcoin'),
                'type' => 'checkbox',
                'description' => '',
                'default' => 'no',
            ),
            'title' => array(
                'title' => __('Title', 'woodurcoin'),
                'type' => 'text',
                'description' => __('Durcoin Payment method description that the customer will see on your checkout.', 'woodurcoin'),
                'default' => __('Durcoin Payments', 'woodurcoin'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'woodurcoin'),
                'type' => 'textarea',
                'description' => __('Durcoin Payment method description that the customer will see on your website.', 'woodurcoin'),
                'default' => __('Durcoin Payments before delivery.', 'woodurcoin'),
                'desc_tip' => true,
            ),
            'address' => array(
                'title' => __('Destination address', 'woodurcoin'),
                'type' => 'text',
                'default' => '3PEDwc7zjxQFzYwAqWAgVradxGba1YE6zMJ',
                'description' => __('This addresses will be used for receiving funds.', 'woodurcoin'),
                'desc_tip' => true,
            ),
            'secret' => array(
                'type' => 'hidden',
                'default' => sha1(wp_generate_password(12, false) . Date('U')),
            ),
            'asset_description' => array(
                'title' => __('Asset description', 'woodurcoin'),
                'type' => 'text',
                'default' => null,
                'description' => __('Asset full name.', 'woodurcoin'),
                'desc_tip' => true,
            ),
            'instructions' => array(
                'title' => __('Instructions', 'durcoin-payments-woo'),
                'type' => 'textarea',
                'description' => __('Instructions that will be added to the thank you page.', 'woodurcoin'),
                'default' => __('Durcoin Payments before delivery.', 'woodurcoin'),
                'desc_tip' => true,
            ),
            'enable_for_virtual' => array(
                'title' => __('Accept for virtual orders', 'woodurcoin'),
                'label' => __('Accept DURCOIN if the order is virtual', 'woodurcoin'),
                'type' => 'checkbox',
                'default' => 'yes',
            ),
            'icon' => array(
                'title' => __('Icon', 'woodurcoin'),
                'type' => 'url',
                'default' => 'https://durcoin.org/uploads/default/optimized/1X/561bf06388b3e10a2fb75be7c24f3690dff9ad9d_2_32x32.jpeg',
                'description' => __('Icon for checkout gateway view.', 'woodurcoin'),
                'desc_tip' => true,
            ),
            'qr_show' => array(
                'title' => __('QR code', 'woodurcoin'),
                'label' => __('Show a QR code', 'woodurcoin'),
                'type' => 'checkbox',
                'description' => __('Show a QR code with a link to the payment page on the Durcoin.Exchange.', 'woodurcoin'),
                'default' => 'yes',
                'desc_tip' => true,
            ),
        );
    }

    public function payment_fields()
    {
        if ($this->description) {
            // echo "" . wpautop(wptexturize($description)); // @codingStandardsIgnoreLine.
            echo $this->description;
        }

        $total_durcoin = $this->getConvertedToAsset($this->get_order_total());

        echo "<div>Total: {$total_durcoin} {$this->assetCode}</div>";

    }

    /**
     * Process the payment and return the result.
     *
     * @param int $order_id Order ID.
     * @return array
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        if ($order->get_total() > 0) {
            return $this->woodurcoin_payment_processing($order);
        }
    }

    private function woodurcoin_payment_processing($order)
    {
        $total_durcoin = $this->getConvertedToAsset($order->get_total());
        $attachment = $this->getGeneratedAttachment($order->id);
        $order->update_meta_data('durcoin_total', $total_durcoin);
        $order->update_meta_data('durcoin_attachment', $attachment);
        $order->save();
        WC()->cart->empty_cart();
        return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url(true));
    }

    /**
     * Output for the order received page.
     */
    public function thankyou_page()
    {
        if ($this->instructions) {
            echo wp_kses_post(wpautop(wptexturize($this->instructions)));
        }
    }

    /**
     * Generate payment form
     */
    public function woodurcoin_pay_for_order()
    {
        global $wp;
        if (is_wc_endpoint_url('order-pay')) {
            $order_id = $wp->query_vars['order-pay'];
            $order = wc_get_order($order_id);
            if (!empty($wp->query_vars['txId'])) {
                $txId = $wp->query_vars['txId'];
                $durcoinNodes = new WavesNodes();
                $tx = $durcoinNodes->getTransactionInfo($txId);
                if ($this->handleOrderTransaction($order, $tx)) {
                    wp_redirect($order->get_checkout_order_received_url()); //success
                } else {
                    wp_redirect($order->get_checkout_order_received_url()); //fail
                }
            } else {
                $total_durcoin = get_post_meta($order_id, 'durcoin_total', true);
                $attachment = get_post_meta($order_id, 'durcoin_attachment', true);
                $attachment58 = \deemru\ABCode::base58()->encode($attachment);
                $referrer = get_site_url() . "/woodurcoin-pay-order-{$order_id}";
                $url = 'https://waves.exchange/#send' . urlencode('/' . $this->assetId)
                    . '?recipient=' . $this->address
                    . '&amount=' . $total_durcoin
                    . '&attachment=' . $attachment
                    . '&referrer=' . $referrer; //$order->get_checkout_order_received_url();
                $urlQr = $url;
                $urlTransactions = 'https://nodes.wavesnodes.com/transactions/address/' . $this->address . '/limit/10';
                $urlRedirect = $order->get_checkout_payment_url(true);

                if ($this->qr_show == 'yes') {
                    ?>
                    <canvas id="qr" data-attachment58="<?php echo $attachment58 ?>"></canvas>
                    <script>
                        (function () {
                            let qr = new QRious({
                                element:
                                    document . getElementById('qr'),
                                size: 260,
                                value: '<?php echo $urlQr ?>'
                            });
                        })();
                    </script>
                    <?php
                }
                ?>
                <div style="margin-top:10px;padding: 5px;">
                    <?php echo __('Total', 'woodurcoin') . ': ' . $total_durcoin . ' ' .  $this->assetCode; ?>
                </div>
                <div style="margin-top:10px;padding: 5px;">
                    <a href="<?php echo $url; ?>">
                        <button style="padding:16px;"><?php echo __('Pay on Waves.Exchange', 'woodurcoin'); ?></button>
                    </a>
                </div>

                <script>
                    setInterval(checkTransactions, 3000);

                    function checkTransactions() {
                        jQuery.getJSON('<?php echo $urlTransactions ?>', function (data, status) {
                            jQuery.each(data[0], function (i, a) {
                                    if (a.attachment == '<?php echo $attachment58 ?>') {
                                        window.location.replace('<?php echo $urlRedirect ?>' + '?txId=' + a[0].id);
                                    }
                                }
                            );
                        });
                    }
                </script>
                <?php
            }
        }
    }

    /**
     * Return amount converted to asset
     * @param $amount
     * @return float|int
     */
    function getConvertedToAsset($amount)
    {
        // Without converted, prices in the token used
        if (strtoupper($this->assetCode) == get_woocommerce_currency()) {
            return $amount;
        }
        // Rate for converting site currency to USD
        if (get_woocommerce_currency() == 'USD') {
            $rate1 = 1;
        } else {
            $rate1 = (new CbrExchange)->getRate(get_woocommerce_currency(), 'USD');
        }
        // Rate for converting USD-N to Durcoin token
        if (strtoupper($this->assetId) == 'WAVES') {
            $rate2 = 1;
        } else {
            $usdnAssetId = 'DG2xFkPdDwKUoBkzGAhQtLpSGzfXLiCYPEzeKH2Ad24p'; // USDN-N
            $rate2 = (new WavesExchange)->getRate($usdnAssetId, $this->assetId);
        }
        // Full converting rate ( assignment fiat USD == USD-N )
        $rate = $rate1 * $rate2;
        return ceil((10 ** $this->decimals) * $amount * $rate) / (10 ** $this->decimals);
    }

    /**
     * Return generated attachment string
     * @param $order_id
     * @return string
     */
    function getGeneratedAttachment($order_id)
    {
        $total_durcoin = $this->getConvertedToAsset($this->get_order_total());
        return sha1($this->secret . '/' . $order_id . '/' . $total_durcoin);
    }

    /**
     * Receives and processes the transaction
     * @param $order
     * @param $tx
     * @return bool
     */
    function handleOrderTransaction($order, $tx)
    {
        if (!empty($tx)) {
            $recipient = $tx->recipient;
            $applicationStatus = $tx->applicationStatus;
            $attachmentFromDurcoin = \deemru\ABCode::base58()->decode($tx->attachment);
            $amount = $tx->amount / (10 ** $this->decimals); // converting
            $assetId = $tx->assetId;
            $attachment = $order->get_meta('durcoin_attachment');
            $total_durcoin = $order->get_meta('durcoin_total');
            if ($applicationStatus == 'succeeded'
                && $this->address == $recipient
                && $this->assetId == $assetId
                && $attachment == $attachmentFromDurcoin
                && $total_durcoin == $amount
            ) {
                $order->payment_complete();
                update_post_meta($order->id, 'tx_id', $tx->id);
                return true;
            }
        }
        return false;
    }

    /**
     * Return gateway info and instructions
     * @param $description
     * @param $payment_id
     * @return string
     */
    function woodurcoin_description_fields($description, $payment_id)
    {
        if ('woodurcoin' !== $payment_id) {
            return $description;
        }
        ob_start();
        ?>
        <div style="display: block; width:300px; height:auto;">
        </div>
        <?php
        $description .= ob_get_clean();
        return $description;
    }

}


