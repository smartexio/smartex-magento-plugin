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
 * @route /smartex/ipn
 */
class Smartex_Core_IpnController extends Mage_Core_Controller_Front_Action
{
    /**
     * smartex's IPN lands here
     *
     * @route /smartex/ipn
     * @route /smartex/ipn/index
     */
    public function indexAction()
    {
        if (false === ini_get('allow_url_fopen')) {
            ini_set('allow_url_fopen', true);
        }

        $raw_post_data = file_get_contents('php://input');

        if (false === $raw_post_data) {
            \Mage::helper('smartex')->debugData('[ERROR] In Smartex_Core_IpnController::indexAction(), Could not read from the php://input stream or invalid Smartex IPN received.');
            throw new \Exception('Could not read from the php://input stream or invalid Smartex IPN received.');
        }

        \Mage::helper('smartex')->registerAutoloader();

        \Mage::helper('smartex')->debugData(array(sprintf('[INFO] In Smartex_Core_IpnController::indexAction(), Incoming IPN message from Smartex: '),$raw_post_data,));

        // Magento doesn't seem to have a way to get the Request body
        $ipn = json_decode($raw_post_data);

        if (true === empty($ipn)) {
            \Mage::helper('smartex')->debugData('[ERROR] In Smartex_Core_IpnController::indexAction(), Could not decode the JSON payload from Smartex.');
            throw new \Exception('Could not decode the JSON payload from Smartex.');
        }

        if (true === empty($ipn->id) || false === isset($ipn->posData)) {
            \Mage::helper('smartex')->debugData(sprintf('[ERROR] In Smartex_Core_IpnController::indexAction(), Did not receive order ID in IPN: ', $ipn));
            throw new \Exception('Invalid Smartex payment notification message received - did not receive order ID.');
        }

        $ipn->posData     = is_string($ipn->posData) ? json_decode($ipn->posData) : $ipn->posData;
        $ipn->buyerFields = isset($ipn->buyerFields) ? $ipn->buyerFields : new stdClass();

        \Mage::helper('smartex')->debugData($ipn);

        // Log IPN
        $mageIpn = \Mage::getModel('smartex/ipn')->addData(
            array(
                'invoice_id'       => isset($ipn->id) ? $ipn->id : '',
                'url'              => isset($ipn->url) ? $ipn->url : '',
                'pos_data'         => json_encode($ipn->posData),
                'status'           => isset($ipn->status) ? $ipn->status : '',
                'eth_price'        => isset($ipn->ethPrice) ? $ipn->ethPrice : '',
                'price'            => isset($ipn->price) ? $ipn->price : '',
                'currency'         => isset($ipn->currency) ? $ipn->currency : '',
                'invoice_time'     => isset($ipn->invoiceTime) ? intval($ipn->invoiceTime / 1000) : '',
                'expiration_time'  => isset($ipn->expirationTime) ? intval($ipn->expirationTime / 1000) : '',
                'current_time'     => isset($ipn->currentTime) ? intval($ipn->currentTime / 1000) : '',
                'eth_paid'         => isset($ipn->ethPaid) ? $ipn->ethPaid : '',
                'rate'             => isset($ipn->rate) ? $ipn->rate : '',
                'exception_status' => isset($ipn->exceptionStatus) ? $ipn->exceptionStatus : '',
            )
        )->save();


        // Order isn't being created for iframe...
        if (isset($ipn->posData->orderId)) {
            $order = \Mage::getModel('sales/order')->loadByIncrementId($ipn->posData->orderId);
        } else {
            $order = \Mage::getModel('sales/order')->load($ipn->posData->quoteId, 'quote_id');
        }

        if (false === isset($order) || true === empty($order)) {
            \Mage::helper('smartex')->debugData('[ERROR] In Smartex_Core_IpnController::indexAction(), Invalid Smartex IPN received.');
            \Mage::throwException('Invalid Smartex IPN received.');
        }

        $orderId = $order->getId();
        if (false === isset($orderId) || true === empty($orderId)) {
            \Mage::helper('smartex')->debugData('[ERROR] In Smartex_Core_IpnController::indexAction(), Invalid Smartex IPN received.');
            \Mage::throwException('Invalid Smartex IPN received.');
        }

        /**
         * Ask Smartex to retreive the invoice so we can make sure the invoices
         * match up and no one is using an automated tool to post IPN's to merchants
         * store.
         */
        $invoice = \Mage::getModel('smartex/method_ethereum')->fetchInvoice($ipn->id);

        if (false === isset($invoice) || true === empty($invoice)) {
            \Mage::helper('smartex')->debugData('[ERROR] In Smartex_Core_IpnController::indexAction(), Could not retrieve the invoice details for the ipn ID of ' . $ipn->id);
            \Mage::throwException('Could not retrieve the invoice details for the ipn ID of ' . $ipn->id);
        }

        // Does the status match?
        if ($invoice->getStatus() != $ipn->status) {
            \Mage::getModel('smartex/method_ethereum')->debugData('[ERROR] In Smartex_Core_IpnController::indexAction(), IPN status and status from Smartex are different. Rejecting this IPN!');
            \Mage::throwException('There was an error processing the IPN - statuses are different. Rejecting this IPN!');
        }

        // Does the price match?
        if ($invoice->getPrice() != $ipn->price) {
            \Mage::getModel('smartex/method_ethereum')>debugData('[ERROR] In Smartex_Core_IpnController::indexAction(), IPN price and invoice price are different. Rejecting this IPN!');
            \Mage::throwException('There was an error processing the IPN - invoice price does not match the IPN price. Rejecting this IPN!');
        }

        // Update the order to notifiy that it has been paid
        $transactionSpeed = \Mage::getStoreConfig('payment/smartex/speed');
        if ($invoice->getStatus() === 'paid' 
            || ($invoice->getStatus() === 'confirmed' && $transactionSpeed === 'high')) {

            $payment = \Mage::getModel('sales/order_payment')->setOrder($order);

            if (true === isset($payment) && false === empty($payment)) {
                $payment->registerCaptureNotification($invoice->getPrice());
                $order->addPayment($payment);

                // If the customer has not already been notified by email
                // send the notification now that there's a new order.
                if (!$order->getEmailSent()) {
                    \Mage::helper('smartex')->debugData('[INFO] In Smartex_Core_IpnController::indexAction(), Order email not sent so I am calling $order->sendNewOrderEmail() now...');
                    $order->sendNewOrderEmail();
                }

                $order->save();

            } else {
                \Mage::helper('smartex')->debugData('[ERROR] In Smartex_Core_IpnController::indexAction(), Could not create a payment object in the Smartex IPN controller.');
                \Mage::throwException('Could not create a payment object in the Smartex IPN controller.');
            }
        }

        // use state as defined by Merchant
        $state = \Mage::getStoreConfig(sprintf('payment/smartex/invoice_%s', $invoice->getStatus()));

        if (false === isset($state) || true === empty($state)) {
            \Mage::helper('smartex')->debugData('[ERROR] In Smartex_Core_IpnController::indexAction(), Could not retrieve the defined state parameter to update this order to in the Smartex IPN controller.');
            \Mage::throwException('Could not retrieve the defined state parameter to update this order in the Smartex IPN controller.');
        }

        // Check if status should be updated
        switch ($order->getStatus()) {
            case Mage_Sales_Model_Order::STATE_CANCELED:
            case Mage_Sales_Model_Order::STATUS_FRAUD: 
            case Mage_Sales_Model_Order::STATE_CLOSED: 
            case Mage_Sales_Model_Order::STATE_COMPLETE: 
            case Mage_Sales_Model_Order::STATE_HOLDED:
                // Do not Update 
                break;
            case Mage_Sales_Model_Order::STATE_PENDING_PAYMENT: 
            case Mage_Sales_Model_Order::STATE_PROCESSING: 
            default:
                $order->addStatusToHistory(
                    $state,
                    sprintf('[INFO] In Smartex_Core_IpnController::indexAction(), Incoming IPN status "%s" updated order state to "%s"', $invoice->getStatus(), $state)
                )->save();
                break;
        }


    }
}
