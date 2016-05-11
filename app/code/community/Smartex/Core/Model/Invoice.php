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
 */
class Smartex_Core_Model_Invoice extends Mage_Core_Model_Abstract
{
    /**
     */
    protected function _construct()
    {
        $this->_init('smartex/invoice');
    }

    /**
     * Adds data to model based on an Invoice that has been retrieved from
     * Smartex's API
     *
     * @param Smartex\Invoice $invoice
     * @return Smartex_Core_Model_Invoice
     */
    public function prepareWithSmartexInvoice($invoice)
    {
        if (false === isset($invoice) || true === empty($invoice)) {
            \Mage::helper('smartex')->debugData('[ERROR] In Smartex_Core_Model_Invoice::prepareWithSmartexInvoice(): Missing or empty $invoice parameter.');
            throw new \Exception('In Smartex_Core_Model_Invoice::prepareWithSmartexInvoice(): Missing or empty $invoice parameter.');
        }

        $this->addData(
            array(
                'id'               => $invoice->getId(),
                'url'              => $invoice->getUrl(),
                'pos_data'         => $invoice->getPosData(),
                'status'           => $invoice->getStatus(),
                'eth_price'        => $invoice->getEthPrice(),
                'price'            => $invoice->getPrice(),
                'currency'         => $invoice->getCurrency()->getCode(),
                'order_id'         => $invoice->getOrderId(),
                'invoice_time'     => intval($invoice->getInvoiceTime() / 1000),
                'expiration_time'  => intval($invoice->getExpirationTime() / 1000),
                'current_time'     => intval($invoice->getCurrentTime() / 1000),
                'eth_paid'         => $invoice->getEthPaid(),
                'rate'             => $invoice->getRate(),
                'exception_status' => $invoice->getExceptionStatus(),
            )
        );

        return $this;
    }

    /**
     * Adds information to based on the order object inside magento
     *
     * @param Mage_Sales_Model_Order $order
     * @return Smartex_Core_Model_Invoice
     */
    public function prepareWithOrder($order)
    {
        if (false === isset($order) || true === empty($order)) {
            \Mage::helper('smartex')->debugData('[ERROR] In Smartex_Core_Model_Invoice::prepateWithOrder(): Missing or empty $order parameter.');
            throw new \Exception('In Smartex_Core_Model_Invoice::prepateWithOrder(): Missing or empty $order parameter.');
        }
        
        $this->addData(
            array(
                'quote_id'     => $order['quote_id'],
                'increment_id' => $order['increment_id'],
            )
        );

        return $this;
    }
}
