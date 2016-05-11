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
$this->startSetup();

/**
 * IPN Log Table, used to keep track of incoming IPNs
 */
$this->run(sprintf('DROP TABLE IF EXISTS `%s`;', $this->getTable('smartex/ipn')));
$ipnTable = new Varien_Db_Ddl_Table();
$ipnTable->setName($this->getTable('smartex/ipn'));
$ipnTable->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, 11, array('auto_increment' => true, 'nullable' => false, 'primary' => true,));
$ipnTable->addColumn('invoice_id', Varien_Db_Ddl_Table::TYPE_TEXT, 200);
$ipnTable->addColumn('url', Varien_Db_Ddl_Table::TYPE_TEXT, 400);
$ipnTable->addColumn('status', Varien_Db_Ddl_Table::TYPE_TEXT, 20);
$ipnTable->addColumn('eth_price', Varien_Db_Ddl_Table::TYPE_DECIMAL, array(16, 8));
$ipnTable->addColumn('price', Varien_Db_Ddl_Table::TYPE_DECIMAL, array(16, 8));
$ipnTable->addColumn('currency', Varien_Db_Ddl_Table::TYPE_TEXT, 10);
$ipnTable->addColumn('invoice_time', Varien_Db_Ddl_Table::TYPE_INTEGER, 11);
$ipnTable->addColumn('expiration_time', Varien_Db_Ddl_Table::TYPE_INTEGER, 11);
$ipnTable->addColumn('current_time', Varien_Db_Ddl_Table::TYPE_INTEGER, 11);
$ipnTable->addColumn('pos_data', Varien_Db_Ddl_Table::TYPE_TEXT, 255);
$ipnTable->addColumn('eth_paid', Varien_Db_Ddl_Table::TYPE_DECIMAL, array(16, 8));
$ipnTable->addColumn('rate', Varien_Db_Ddl_Table::TYPE_DECIMAL, array(16, 8));
$ipnTable->addColumn('exception_status', Varien_Db_Ddl_Table::TYPE_TEXT, 255);

$ipnTable->setOption('type', 'InnoDB');
$ipnTable->setOption('charset', 'utf8');
$this->getConnection()->createTable($ipnTable);

/**
 * Table used to keep track of invoices that have been created. The
 * IPNs that are received are used to update this table.
 */
$this->run(sprintf('DROP TABLE IF EXISTS `%s`;', $this->getTable('smartex/invoice')));
$invoiceTable = new Varien_Db_Ddl_Table();
$invoiceTable->setName($this->getTable('smartex/invoice'));
$invoiceTable->addColumn('id', Varien_Db_Ddl_Table::TYPE_TEXT, 64, array('nullable' => false, 'primary' => true));
$invoiceTable->addColumn('quote_id', Varien_Db_Ddl_Table::TYPE_INTEGER, 11);
$invoiceTable->addColumn('increment_id', Varien_Db_Ddl_Table::TYPE_INTEGER, 11);
$invoiceTable->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP);
$invoiceTable->addColumn('url', Varien_Db_Ddl_Table::TYPE_TEXT, 200);
$invoiceTable->addColumn('pos_data', Varien_Db_Ddl_Table::TYPE_TEXT, 255);
$invoiceTable->addColumn('status', Varien_Db_Ddl_Table::TYPE_TEXT, 20);
$invoiceTable->addColumn('eth_price', Varien_Db_Ddl_Table::TYPE_DECIMAL, array(16, 8));
$invoiceTable->addColumn('eth_due', Varien_Db_Ddl_Table::TYPE_DECIMAL, array(16, 8));
$invoiceTable->addColumn('price', Varien_Db_Ddl_Table::TYPE_DECIMAL, array(16, 8));
$invoiceTable->addColumn('currency', Varien_Db_Ddl_Table::TYPE_TEXT, 10);
$invoiceTable->addColumn('ex_rates', Varien_Db_Ddl_Table::TYPE_TEXT, 255);
$invoiceTable->addColumn('order_id', Varien_Db_Ddl_Table::TYPE_TEXT, 64);
$invoiceTable->addColumn('invoice_time', Varien_Db_Ddl_Table::TYPE_INTEGER, 11);
$invoiceTable->addColumn('expiration_time', Varien_Db_Ddl_Table::TYPE_INTEGER, 11);
$invoiceTable->addColumn('current_time', Varien_Db_Ddl_Table::TYPE_INTEGER, 11);
$invoiceTable->addColumn('eth_paid', Varien_Db_Ddl_Table::TYPE_DECIMAL, array(16, 8));
$invoiceTable->addColumn('rate', Varien_Db_Ddl_Table::TYPE_DECIMAL, array(16, 8));
$invoiceTable->addColumn('exception_status', Varien_Db_Ddl_Table::TYPE_TEXT, 255);
$invoiceTable->addColumn('token', Varien_Db_Ddl_Table::TYPE_TEXT, 164);
$invoiceTable->setOption('type', 'InnoDB');
$invoiceTable->setOption('charset', 'utf8');
$this->getConnection()->createTable($invoiceTable);

$this->endSetup();
