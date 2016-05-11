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

class Smartex_Core_Model_Observer
{
    /*
     * TODO: Why is this here?
     */
    public function checkForRequest($observer)
    {
    }

    /*
     * Queries Smartex to update the order states in magento to make sure that
     * open orders are closed/canceled if the Smartex invoice expires or becomes
     * invalid.
     */
    public function updateOrderStates()
    {
        $apiKey = \Mage::getStoreConfig('payment/smartex/api_key');

        if (false === isset($apiKey) || empty($apiKey)) {
            \Mage::helper('smartex')->debugData('[INFO] Smartex_Core_Model_Observer::updateOrderStates() could not start job to update the order states because the API key was not set.');
            return;
        } else {
            \Mage::helper('smartex')->debugData('[INFO] Smartex_Core_Model_Observer::updateOrderStates() started job to query Smartex to update the existing order states.');
        }

        /*
         * Get all of the orders that are open and have not received an IPN for
         * complete, expired, or invalid.
         */
        $orders = \Mage::getModel('smartex/ipn')->getOpenOrders();

        if (false === isset($orders) || empty($orders)) {
            \Mage::helper('smartex')->debugData('[INFO] Smartex_Core_Model_Observer::updateOrderStates() could not retrieve the open orders.');
            return;
        } else {
            \Mage::helper('smartex')->debugData('[INFO] Smartex_Core_Model_Observer::updateOrderStates() successfully retrieved existing open orders.');
        }

        /*
         * Get all orders that have been paid using smartex and
         * are not complete/closed/etc
         */
        foreach ($orders as $order) {
            /*
             * Query Smartex with the invoice ID to get the status. We must take
             * care not to anger the API limiting gods and disable our access
             * to the API.
             */
            $status = null;

            // TODO:
            // Does the order need to be updated?
            // Yes? Update Order Status
            // No? continue
        }

        \Mage::helper('smartex')->debugData('[INFO] Smartex_Core_Model_Observer::updateOrderStates() order status update job finished.');
    }

    /**
     * Method that is called via the magento cron to update orders if the
     * invoice has expired
     */
    public function cleanExpired()
    {
        \Mage::helper('smartex')->debugData('[INFO] Smartex_Core_Model_Observer::cleanExpired() called.');
        \Mage::helper('smartex')->cleanExpired();
    }
}
