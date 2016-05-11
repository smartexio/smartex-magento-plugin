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
 * @route smartex/index/
 */
class Smartex_Core_IndexController extends Mage_Core_Controller_Front_Action
{
    /**
     * @route smartex/index/index?quote=n
     */
    public function indexAction()
    {
        $params  = $this->getRequest()->getParams();
        $quoteId = $params['quote'];
        \Mage::helper('smartex')->registerAutoloader();
        \Mage::helper('smartex')->debugData($params);
        $paid = Mage::getModel('smartex/ipn')->GetQuotePaid($quoteId);

        $this->loadLayout();

        $this->getResponse()->setHeader('Content-type', 'application/json');
        
        $this->getResponse()->setBody(json_encode(array('paid' => $paid)));
    }
}
