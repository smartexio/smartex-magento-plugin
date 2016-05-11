<?php
/**
 * The MIT License (MIT)
 * 
 * Copyright (c) 2016 Smartex.io Ltd.
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * Ethereum payment method support by Smartex
 */
class Smartex_Core_Model_Method_Ethereum extends Mage_Payment_Model_Method_Abstract
{
    protected $_code                        = 'smartex';
    protected $_formBlockType               = 'smartex/form_smartex';
    protected $_infoBlockType               = 'smartex/info';

    protected $_isGateway                   = true;
    protected $_canAuthorize                = true;
    protected $_canCapture                  = false;
    protected $_canUseInternal              = false;
    protected $_isInitializeNeeded          = false;
    protected $_canFetchTransactionInfo     = false;
    protected $_canManagerRecurringProfiles = false;
    protected $_canUseCheckout              = true;
    protected $_canUseForMultishipping      = true;
    protected $_canCapturePartial           = false;
    protected $_canRefund                   = false;
    protected $_canVoid                     = false;

    protected $_debugReplacePrivateDataKeys = array();

    protected static $_redirectUrl;

    /**
     * @param  Mage_Sales_Model_Order_Payment  $payment
     * @param  float                           $amount
     * @return Smartex_Core_Model_PaymentMethod
     */
    public function authorize(Varien_Object $payment, $amount, $iframe = false)
    {
        // Check if coming from iframe or submit button
        if ((!Mage::getStoreConfig('payment/smartex/fullscreen') && $iframe === false)
            || (Mage::getStoreConfig('payment/smartex/fullscreen') && $iframe === true)) {
            $quoteId = $payment->getOrder()->getQuoteId();
            $ipn     = Mage::getModel('smartex/ipn');

            if (!$ipn->GetQuotePaid($quoteId))
            {
                // This is the error that is displayed to the customer during checkout.
                Mage::throwException("Order not paid for.  Please pay first and then Place your Order.");
                Mage::log('Order not paid for. Please pay first and then Place Your Order.', Zend_Log::CRIT, Mage::helper('smartex')->getLogFile());
            }

            return $this;
        }

        if (false === isset($payment) || false === isset($amount) || true === empty($payment) || true === empty($amount)) {
            $this->debugData('[ERROR] In Smartex_Core_Model_Method_Ethereum::authorize(): missing payment or amount parameters.');
            throw new \Exception('In Smartex_Core_Model_Method_Ethereum::authorize(): missing payment or amount parameters.');
        }

        $this->debugData('[INFO] Smartex_Core_Model_Method_Ethereum::authorize(): authorizing new order.');

        // Create Smartex Invoice
        $invoice = $this->initializeInvoice();

        if (false === isset($invoice) || true === empty($invoice)) {
            $this->debugData('[ERROR] In Smartex_Core_Model_Method_Ethereum::authorize(): could not initialize invoice.');
            throw new \Exception('In Smartex_Core_Model_Method_Ethereum::authorize(): could not initialize invoice.');
        }

        $invoice = $this->prepareInvoice($invoice, $payment, $amount);

        try {
            $smartexInvoice = \Mage::helper('smartex')->getSmartexClient()->createInvoice($invoice);
        } catch (\Exception $e) {
            $this->debugData('[ERROR] In Smartex_Core_Model_Method_Ethereum::authorize(): ' . $e->getMessage());
            \Mage::throwException('In Smartex_Core_Model_Method_Ethereum::authorize(): Could not authorize transaction.');
        }

        self::$_redirectUrl = (Mage::getStoreConfig('payment/smartex/fullscreen')) ? $smartexInvoice->getUrl(): $smartexInvoice->getUrl().'&view=iframe';

        $this->debugData(
            array(
                '[INFO] Smartex Invoice created',
                sprintf('Invoice URL: "%s"', $smartexInvoice->getUrl()),
            )
        );

        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $order = \Mage::getModel('sales/order')->load($quote->getId(), 'quote_id');

        // Save Smartex Invoice in database for reference
        $mirrorInvoice = \Mage::getModel('smartex/invoice')
            ->prepareWithSmartexInvoice($smartexInvoice)
            ->prepareWithOrder(array('increment_id' => $order->getIncrementId(), 'quote_id'=> $quote->getId()))
            ->save();

        $this->debugData('[INFO] Leaving Smartex_Core_Model_Method_Ethereum::authorize(): invoice id ' . $smartexInvoice->getId());

        return $this;
    }

    /**
     * This makes sure that the merchant has setup the extension correctly
     * and if they have not, it will not show up on the checkout.
     *
     * @see Mage_Payment_Model_Method_Abstract::canUseCheckout()
     * @return bool
     */
    public function canUseCheckout()
    {
        $token = \Mage::getStoreConfig('payment/smartex/token');

        if (false === isset($token) || true === empty($token)) {
            /**
             * Merchant must goto their account and create a pairing code to
             * enter in.
             */
            $this->debugData('[ERROR] In Smartex_Core_Model_Method_Ethereum::canUseCheckout(): There was an error retrieving the token store param from the database or this Magento store does not have a Smartex token.');

            return false;
        }

        $this->debugData('[INFO] Leaving Smartex_Core_Model_Method_Ethereum::canUseCheckout(): token obtained from storage successfully.');

        return true;
    }

    /**
     * Fetchs an invoice from Smartex
     *
     * @param string $id
     * @return Smartex\Invoice
     */
    public function fetchInvoice($id)
    {
        if (false === isset($id) || true === empty($id)) {
            $this->debugData('[ERROR] In Smartex_Core_Model_Method_Ethereum::fetchInvoice(): missing or invalid id parameter.');
            throw new \Exception('In Smartex_Core_Model_Method_Ethereum::fetchInvoice(): missing or invalid id parameter.');
        } else {
            $this->debugData('[INFO] In Smartex_Core_Model_Method_Ethereum::fetchInvoice(): function called with id ' . $id);
        }

       \Mage::helper('smartex')->registerAutoloader();

        $client  = \Mage::helper('smartex')->getSmartexClient();

        if (false === isset($client) || true === empty($client)) {
            $this->debugData('[ERROR] In Smartex_Core_Model_Method_Ethereum::fetchInvoice(): could not obtain Smartex client.');
            throw new \Exception('In Smartex_Core_Model_Method_Ethereum::fetchInvoice(): could not obtain Smartex client.');
        } else {
            $this->debugData('[INFO] In Smartex_Core_Model_Method_Ethereum::fetchInvoice(): obtained Smartex client successfully.');
        }

        $invoice = $client->getInvoice($id);

        if (false === isset($invoice) || true === empty($invoice)) {
            $this->debugData('[ERROR] In Smartex_Core_Model_Method_Ethereum::fetchInvoice(): could not retrieve invoice from Smartex.');
            throw new \Exception('In Smartex_Core_Model_Method_Ethereum::fetchInvoice(): could not retrieve invoice from Smartex.');
        } else {
            $this->debugData('[INFO] In Smartex_Core_Model_Method_Ethereum::fetchInvoice(): successfully retrieved invoice id ' . $id . ' from Smartex.');
        }

        return $invoice;
    }

    /**
     * given Mage_Core_Model_Abstract, return api-friendly address
     *
     * @param $address
     *
     * @return array
     */
    public function extractAddress($address)
    {
        if (false === isset($address) || true === empty($address)) {
            $this->debugData('[ERROR] In Smartex_Core_Model_Method_Ethereum::extractAddress(): missing or invalid address parameter.');
            throw new \Exception('In Smartex_Core_Model_Method_Ethereum::extractAddress(): missing or invalid address parameter.');
        } else {
            $this->debugData('[INFO] In Smartex_Core_Model_Method_Ethereum::extractAddress(): called with good address parameter, extracting now.');
        }

        $options              = array();
        $options['buyerName'] = $address->getName();

        if ($address->getCompany()) {
            $options['buyerName'] = $options['buyerName'].' c/o '.$address->getCompany();
        }

        $options['buyerAddress1'] = $address->getStreet1();
        $options['buyerAddress2'] = $address->getStreet2();
        $options['buyerAddress3'] = $address->getStreet3();
        $options['buyerAddress4'] = $address->getStreet4();
        $options['buyerCity']     = $address->getCity();
        $options['buyerState']    = $address->getRegionCode();
        $options['buyerZip']      = $address->getPostcode();
        $options['buyerCountry']  = $address->getCountry();
        $options['buyerEmail']    = $address->getEmail();
        $options['buyerPhone']    = $address->getTelephone();

        // trim to fit API specs
        foreach (array('buyerName', 'buyerAddress1', 'buyerAddress2', 'buyerAddress3', 'buyerAddress4', 'buyerCity', 'buyerState', 'buyerZip', 'buyerCountry', 'buyerEmail', 'buyerPhone') as $f) {
            if (true === isset($options[$f]) && strlen($options[$f]) > 100) {
                $this->debugData('[WARNING] In Smartex_Core_Model_Method_Ethereum::extractAddress(): the ' . $f . ' parameter was greater than 100 characters, trimming.');
                $options[$f] = substr($options[$f], 0, 100);
            }
        }

        return $options;
    }

    /**
     * This is called when a user clicks the `Place Order` button
     *
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        $this->debugData('[INFO] In Smartex_Core_Model_Method_Ethereum::getOrderPlaceRedirectUrl(): $_redirectUrl is ' . self::$_redirectUrl);

        return self::$_redirectUrl;

    }

    /**
     * Create a new invoice with as much info already added. It should add
     * some basic info and setup the invoice object.
     *
     * @return Smartex\Invoice
     */
    private function initializeInvoice()
    {
        \Mage::helper('smartex')->registerAutoloader();

        $invoice = new Smartex\Invoice();

        if (false === isset($invoice) || true === empty($invoice)) {
            $this->debugData('[ERROR] In Smartex_Core_Model_Method_Ethereum::initializeInvoice(): could not construct new Smartex invoice object.');
            throw new \Exception('In Smartex_Core_Model_Method_Ethereum::initializeInvoice(): could not construct new Smartex invoice object.');
        } else {
            $this->debugData('[INFO] In Smartex_Core_Model_Method_Ethereum::initializeInvoice(): constructed new Smartex invoice object successfully.');
        }

        $invoice->setFullNotifications(true);
        $invoice->setTransactionSpeed(\Mage::getStoreConfig('payment/smartex/speed'));
        $invoice->setNotificationUrl(\Mage::getUrl(\Mage::getStoreConfig('payment/smartex/notification_url')));
        $invoice->setRedirectUrl(\Mage::getUrl(\Mage::getStoreConfig('payment/smartex/redirect_url')));

        return $invoice;
    }

    /**
     * Prepares the invoice object to be sent to Smartex's API. This method sets
     * all the other info that we have to rely on other objects for.
     *
     * @param Smartex\Invoice                  $invoice
     * @param  Mage_Sales_Model_Order_Payment $payment
     * @param  float                          $amount
     * @return Smartex\Invoice
     */
    private function prepareInvoice($invoice, $payment, $amount)
    {
        if (false === isset($invoice) || true === empty($invoice) || false === isset($payment) || true === empty($payment) || false === isset($amount) || true === empty($amount)) {
            $this->debugData('[ERROR] In Smartex_Core_Model_Method_Ethereum::prepareInvoice(): missing or invalid invoice, payment or amount parameter.');
            throw new \Exception('In Smartex_Core_Model_Method_Ethereum::prepareInvoice(): missing or invalid invoice, payment or amount parameter.');
        } else {
            $this->debugData('[INFO] In Smartex_Core_Model_Method_Ethereum::prepareInvoice(): entered function with good invoice, payment and amount parameters.');
        }

        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $order = \Mage::getModel('sales/order')->load($quote->getId(), 'quote_id');

        if (Mage::getStoreConfig('payment/smartex/fullscreen')) {
            $invoice->setOrderId($order->getIncrementId());
            $invoice->setPosData(json_encode(array('orderId' => $order->getIncrementId())));
        } else {
            $invoice->setOrderId($quote->getId());
            $invoice->setPosData(json_encode(array('quoteId' => $quote->getId())));
            $convertQuote = Mage::getSingleton('sales/convert_quote');
            $order = $convertQuote->toOrder($quote);
        }

        $invoice = $this->addCurrencyInfo($invoice, $order);
        $invoice = $this->addPriceInfo($invoice, $amount);
        $invoice = $this->addBuyerInfo($invoice, $order);

        return $invoice;
    }

    /**
     * This adds the buyer information to the invoice.
     *
     * @param Smartex\Invoice         $invoice
     * @param Mage_Sales_Model_Order $order
     * @return Smartex\Invoice
     */
    private function addBuyerInfo($invoice, $order)
    {
        if (false === isset($invoice) || true === empty($invoice) || false === isset($order) || true === empty($order)) {
            $this->debugData('[ERROR] In Smartex_Core_Model_Method_Ethereum::addBuyerInfo(): missing or invalid invoice or order parameter.');
            throw new \Exception('In Smartex_Core_Model_Method_Ethereum::addBuyerInfo(): missing or invalid invoice or order parameter.');
        } else {
            $this->debugData('[INFO] In Smartex_Core_Model_Method_Ethereum::addBuyerInfo(): function called with good invoice and order parameters.');
        }

        $buyer = new Smartex\Buyer();

        if (false === isset($buyer) || true === empty($buyer)) {
            $this->debugData('[ERROR] In Smartex_Core_Model_Method_Ethereum::addBuyerInfo(): could not construct new Smartex buyer object.');
            throw new \Exception('In Smartex_Core_Model_Method_Ethereum::addBuyerInfo(): could not construct new Smartex buyer object.');
        }


        $buyer->setFirstName($order->getCustomerFirstname());
        $buyer->setLastName($order->getCustomerLastname());


        if (Mage::getStoreConfig('payment/smartex/fullscreen')) {
            $address = $order->getBillingAddress();
        } else {
            $quote = Mage::getSingleton('checkout/session')->getQuote();
            $address = $quote->getBillingAddress();
        }

        $street = $address->getStreet1();
        if (null !== $street && '' !== $street) {
            $buyer->setAddress(
                array(
                    $street,
                    $address->getStreet2(),
                    $address->getStreet3(),
                    $address->getStreet4()
                    )
                );
        }

        $region     = $address->getRegion();
        $regioncode = $address->getRegionCode();
        if (null !== $regioncode && '' !== $regioncode) {
            $buyer->setState($regioncode);
        } else if (null !== $region && '' !== $region) {
            $buyer->setState($region);
        }

        $country = $address->getCountry();
        if (null !== $country && '' !== $country) {
            $buyer->setCountry($country);
        }

        $city = $address->getCity();
        if (null !== $city && '' !== $city) {
            $buyer->setCity($city);
        }

        $postcode = $address->getPostcode();
        if (null !== $postcode && '' !== $postcode) {
            $buyer->setZip($postcode);
        }

        $email = $address->getEmail();
        if (null !== $email && '' !== $email) {
            $buyer->setEmail($email);
        }

        $telephone = $address->getTelephone();
        if (null !== $telephone && '' !== $telephone) {
            $buyer->setPhone($telephone);
        }

        $invoice->setBuyer($buyer);

        return $invoice;
    }

    /**
     * Adds currency information to the invoice
     *
     * @param Smartex\Invoice         $invoice
     * @param Mage_Sales_Model_Order $order
     * @return Smartex\Invoice
     */
    private function addCurrencyInfo($invoice, $order)
    {
        if (false === isset($invoice) || true === empty($invoice) || false === isset($order) || true === empty($order)) {
            $this->debugData('[ERROR] In Smartex_Core_Model_Method_Ethereum::addCurrencyInfo(): missing or invalid invoice or order parameter.');
            throw new \Exception('In Smartex_Core_Model_Method_Ethereum::addCurrencyInfo(): missing or invalid invoice or order parameter.');
        } else {
            $this->debugData('[INFO] In Smartex_Core_Model_Method_Ethereum::addCurrencyInfo(): function called with good invoice and order parameters.');
        }

        $currency = new Smartex\Currency();

        if (false === isset($currency) || true === empty($currency)) {
            $this->debugData('[ERROR] In Smartex_Core_Model_Method_Ethereum::addCurrencyInfo(): could not construct new Smartex currency object.');
            throw new \Exception('In Smartex_Core_Model_Method_Ethereum::addCurrencyInfo(): could not construct new Smartex currency object.');
        }

        $currency->setCode($order->getOrderCurrencyCode());
        $invoice->setCurrency($currency);

        return $invoice;
    }

    /**
     * Adds pricing information to the invoice
     *
     * @param Smartex\Invoice  invoice
     * @param float           $amount
     * @return Smartex\Invoice
     */
    private function addPriceInfo($invoice, $amount)
    {
        if (false === isset($invoice) || true === empty($invoice) || false === isset($amount) || true === empty($amount)) {
            $this->debugData('[ERROR] In Smartex_Core_Model_Method_Ethereum::addPriceInfo(): missing or invalid invoice or amount parameter.');
            throw new \Exception('In Smartex_Core_Model_Method_Ethereum::addPriceInfo(): missing or invalid invoice or amount parameter.');
        } else {
            $this->debugData('[INFO] In Smartex_Core_Model_Method_Ethereum::addPriceInfo(): function called with good invoice and amount parameters.');
        }

        $item = new \Smartex\Item();

        if (false === isset($item) || true === empty($item)) {
            $this->debugData('[ERROR] In Smartex_Core_Model_Method_Ethereum::addPriceInfo(): could not construct new Smartex item object.');
            throw new \Exception('In Smartex_Core_Model_Method_Ethereum::addPriceInfo(): could not construct new Smartex item object.');
        }

        $item->setPrice($amount);
        $invoice->setItem($item);

        return $invoice;
    }
}
