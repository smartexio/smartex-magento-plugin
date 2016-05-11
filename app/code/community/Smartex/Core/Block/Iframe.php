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

class Smartex_Core_Block_Iframe extends Mage_Checkout_Block_Onepage_Payment
{
    /**
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('smartex/iframe.phtml');
    }

    /**
     * create an invoice and return the url so that iframe.phtml can display it
     *
     * @return string
     */
    public function getIframeUrl()
    {

        if (!($quote = Mage::getSingleton('checkout/session')->getQuote()) 
            or !($payment = $quote->getPayment())
            or !($paymentMethod = $payment->getMethod())
            or ($paymentMethod !== 'smartex')
            or (Mage::getStoreConfig('payment/smartex/fullscreen')))
        {
            return 'notsmartex';
        }

        \Mage::helper('smartex')->registerAutoloader();

        // fullscreen disabled?
        if (Mage::getStoreConfig('payment/smartex/fullscreen'))
        {
            return 'disabled';
        }

        if (\Mage::getModel('smartex/ipn')->getQuotePaid($this->getQuote()->getId())) {
            return 'paid'; // quote's already paid, so don't show the iframe
        }

        /*** @var Smartex_Core_Model_PaymentMethod ***/
        $method  = $this->getQuote()->getPayment()->getMethodInstance();

        $amount = $this->getQuote()->getGrandTotal();

        if (false === isset($method) || true === empty($method)) {
            \Mage::helper('smartex')->debugData('[ERROR] In Smartex_Core_Block_Iframe::getIframeUrl(): Could not obtain an instance of the payment method.');
            throw new \Exception('In Smartex_Core_Block_Iframe::getIframeUrl(): Could not obtain an instance of the payment method.');
        }

        $ethereumMethod = \Mage::getModel('smartex/method_ethereum');

        try {
            $ethereumMethod->authorize($payment, $amount, true);
        } catch (\Exception $e) {
            \Mage::helper('smartex')->debugData('[ERROR] In Smartex_Core_Block_Iframe::getIframeUrl(): failed with the message: ' . $e->getMessage());
            \Mage::throwException("Error creating Smartex invoice. Please try again or use another payment option.");
            return false;
        }

        return $ethereumMethod->getOrderPlaceRedirectUrl();
    }
}
