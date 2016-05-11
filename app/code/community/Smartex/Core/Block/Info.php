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

class Smartex_Core_Block_Info extends Mage_Payment_Block_Info
{
    public function _construct()
    {
        parent::_construct();
        $this->setTemplate('smartex/info/default.phtml');
    }

    public function getSmartexInvoiceUrl()
    {
        $order       = $this->getInfo()->getOrder();

        if (false === isset($order) || true === empty($order)) {
            \Mage::helper('smartex')->debugData('[ERROR] In Smartex_Core_Block_Info::getSmartexInvoiceUrl(): could not obtain the order.');
            throw new \Exception('In Smartex_Core_Block_Info::getSmartexInvoiceUrl(): could not obtain the order.');
        }

        $incrementId = $order->getIncrementId();

        if (false === isset($incrementId) || true === empty($incrementId)) {
            \Mage::helper('smartex')->debugData('[ERROR] In Smartex_Core_Block_Info::getSmartexInvoiceUrl(): could not obtain the incrementId.');
            throw new \Exception('In Smartex_Core_Block_Info::getSmartexInvoiceUrl(): could not obtain the incrementId.');
        }

        $smartexInvoice = \Mage::getModel('smartex/invoice')->load($incrementId, 'increment_id');

        if (true === isset($smartexInvoice) && false === empty($smartexInvoice)) {
            return $smartexInvoice->getUrl();
        }
    }
}
