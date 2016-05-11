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
class Smartex_Core_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $_autoloaderRegistered;
    protected $_smartex;
    protected $_sin;
    protected $_publicKey;
    protected $_privateKey;
    protected $_keyManager;
    protected $_client;

    /**
     * @param mixed $debugData
     */
    public function debugData($debugData)
    {
        if (true === isset($debugData) && false === empty($debugData)) {
            \Mage::getModel('smartex/method_ethereum')->debugData($debugData);
        }
    }

    /**
     * @return boolean
     */
    public function isDebug()
    {
        return (boolean) \Mage::getStoreConfig('payment/smartex/debug');
    }

    /**
     * Returns true if Transaction Speed has been configured
     *
     * @return boolean
     */
    public function hasTransactionSpeed()
    {
        $speed = \Mage::getStoreConfig('payment/smartex/speed');

        return !empty($speed);
    }

    /**
     * Returns the URL where the IPN's are sent
     *
     * @return string
     */
    public function getNotificationUrl()
    {
        return \Mage::getUrl(\Mage::getStoreConfig('payment/smartex/notification_url'));
    }

    /**
     * Returns the URL where customers are redirected
     *
     * @return string
     */
    public function getRedirectUrl()
    {
        return \Mage::getUrl(\Mage::getStoreConfig('payment/smartex/redirect_url'));
    }

    /**
     * Registers the Smartex autoloader to run before Magento's. This MUST be
     * called before using any smartex classes.
     */
    public function registerAutoloader()
    {
        if (true === empty($this->_autoloaderRegistered)) {
            $autoloader_filename = \Mage::getBaseDir('lib').'/Smartex/Autoloader.php';

            if (true === is_file($autoloader_filename) && true === is_readable($autoloader_filename)) {
                require_once $autoloader_filename;
                \Smartex\Autoloader::register();
                $this->_autoloaderRegistered = true;
                $this->debugData('[INFO] In Smartex_Core_Helper_Data::registerAutoloader(): autoloader file was found and has been registered.');
            } else {
                $this->_autoloaderRegistered = false;
                $this->debugData('[ERROR] In Smartex_Core_Helper_Data::registerAutoloader(): autoloader file was not found or is not readable. Cannot continue!');
                throw new \Exception('In Smartex_Core_Helper_Data::registerAutoloader(): autoloader file was not found or is not readable. Cannot continue!');
            }
        }
    }

    /**
     * This function will generate keys that will need to be paired with Smartex
     * using
     */
    public function generateAndSaveKeys()
    {
        $this->debugData('[INFO] In Smartex_Core_Helper_Data::generateAndSaveKeys(): attempting to generate new keypair and save to database.');

        if (true === empty($this->_autoloaderRegistered)) {
            $this->registerAutoloader();
        }

        $this->_privateKey = new Smartex\PrivateKey('payment/smartex/private_key');

        if (false === isset($this->_privateKey) || true === empty($this->_privateKey)) {
            $this->debugData('[ERROR] In Smartex_Core_Helper_Data::generateAndSaveKeys(): could not create new Smartex private key object. Cannot continue!');
            throw new \Exception('In Smartex_Core_Helper_Data::generateAndSaveKeys(): could not create new Smartex private key object. Cannot continue!');
        } else {
            $this->_privateKey->generate();
        }

        $this->_publicKey = new Smartex\PublicKey('payment/smartex/public_key');

        if (false === isset($this->_publicKey) || true === empty($this->_publicKey)) {
            $this->debugData('[ERROR] In Smartex_Core_Helper_Data::generateAndSaveKeys(): could not create new Smartex public key object. Cannot continue!');
            throw new \Exception('In Smartex_Core_Helper_Data::generateAndSaveKeys(): could not create new Smartex public key object. Cannot continue!');
        } else {
            $this->_publicKey
                 ->setPrivateKey($this->_privateKey)
                 ->generate();
        }

        $this->getKeyManager()->persist($this->_publicKey);
        $this->getKeyManager()->persist($this->_privateKey);

        $this->debugData('[INFO] In Smartex_Core_Helper_Data::generateAndSaveKeys(): key manager called to persist keypair to database.');
    }

    /**
     * Send a pairing request to Smartex to receive a Token
     */
    public function sendPairingRequest($pairingCode)
    {
        if (false === isset($pairingCode) || true === empty($pairingCode)) {
            $this->debugData('[ERROR] In Smartex_Core_Helper_Data::sendPairingRequest(): missing or invalid pairingCode parameter.');
            throw new \Exception('In Smartex_Core_Helper_Data::sendPairingRequest(): missing or invalid pairingCode parameter.');
        } else {
            $this->debugData('[INFO] In Smartex_Core_Helper_Data::sendPairingRequest(): function called with the pairingCode parameter: ' . $pairingCode);
        }

        if (true === empty($this->_autoloaderRegistered)) {
            $this->registerAutoloader();
        }

        // Generate/Regenerate keys
        $this->generateAndSaveKeys();
        $sin = $this->getSinKey();

        if (false === isset($sin) || true === empty($sin)) {
            $this->debugData('[ERROR] In Smartex_Core_Helper_Data::sendPairingRequest(): could not retrieve the SIN parameter. Cannot continue!');
            throw new \Exception('In Smartex_Core_Helper_Data::sendPairingRequest(): could not retrieve the SIN parameter. Cannot continue!');
        } else {
            $this->debugData('[INFO] In Smartex_Core_Helper_Data::sendPairingRequest(): attempting to pair with the SIN parameter: ' . $sin);
        }

        // Sanitize label
        $label = preg_replace('/[^a-zA-Z0-9 ]/', '', \Mage::app()->getStore()->getName());
        $label = substr('Magento ' . $label, 0, 59);

        $this->debugData('[INFO] In Smartex_Core_Helper_Data::sendPairingRequest(): using the label "' . $label . '".');

        $token = $this->getSmartexClient()->createToken(
                                                       array(
                                                            'id'          => (string) $sin,
                                                            'pairingCode' => (string) $pairingCode,
                                                            'label'       => (string) $label,
                                                       )
                                           );

        if (false === isset($token) || true === empty($token)) {
            $this->debugData('[ERROR] In Smartex_Core_Helper_Data::sendPairingRequest(): could not obtain the token from the pairing process. Cannot continue!');
            throw new \Exception('In Smartex_Core_Helper_Data::sendPairingRequest(): could not obtain the token from the pairing process. Cannot continue!');
        } else {
            $this->debugData('[INFO] In Smartex_Core_Helper_Data::sendPairingRequest(): token successfully obtained.');
        }

        $config = new \Mage_Core_Model_Config();

        if (false === isset($config) || true === empty($config)) {
            $this->debugData('[ERROR] In Smartex_Core_Helper_Data::sendPairingRequest(): could not create new Mage_Core_Model_Config object. Cannot continue!');
            throw new \Exception('In Smartex_Core_Helper_Data::sendPairingRequest(): could not create new Mage_Core_Model_Config object. Cannot continue!');
        }

        if($config->saveConfig('payment/smartex/token', $token->getToken())) {
            $this->debugData('[INFO] In Smartex_Core_Helper_Data::sendPairingRequest(): token saved to database.');
        } else {
            $this->debugData('[ERROR] In Smartex_Core_Helper_Data::sendPairingRequest(): token could not be saved to database.');
            throw new \Exception('In Smartex_Core_Helper_Data::sendPairingRequest(): token could not be saved to database.');
        }
    }

    /**
     * @return Smartex\SinKey
     */
    public function getSinKey()
    {
        if (false === empty($this->_sin)) {
            return $this->_sin;
        }

        $this->debugData('[INFO] In Smartex_Core_Helper_Data::getSinKey(): attempting to get the SIN parameter.');

        if (true === empty($this->_autoloaderRegistered)) {
            $this->registerAutoloader();
        }

        $this->_sin = new Smartex\SinKey();

        if (false === isset($this->_sin) || true === empty($this->_sin)) {
            $this->debugData('[ERROR] In Smartex_Core_Helper_Data::getSinKey(): could not create new Smartex SinKey object. Cannot continue!');
            throw new \Exception('In Smartex_Core_Helper_Data::getSinKey(): could not create new Smartex SinKey object. Cannot continue!');
        }

        $this->_sin
             ->setPublicKey($this->getPublicKey())
             ->generate();

        if (false === isset($this->_sin) || true === empty($this->_sin)) {
            $this->debugData('[ERROR] In Smartex_Core_Helper_Data::getSinKey(): could not generate a new SIN from the public key. Cannot continue!');
            throw new \Exception('In Smartex_Core_Helper_Data::getSinKey(): could not generate a new SIN from the public key. Cannot continue!');
        }

        return $this->_sin;
    }

    public function getPublicKey()
    {
        if (true === isset($this->_publicKey) && false === empty($this->_publicKey)) {
            $this->debugData('[INFO] In Smartex_Core_Helper_Data::getPublicKey(): found an existing public key, returning that.');
            return $this->_publicKey;
        }

        if (true === empty($this->_autoloaderRegistered)) {
            $this->registerAutoloader();
        }

        $this->debugData('[INFO] In Smartex_Core_Helper_Data::getPublicKey(): did not find an existing public key, attempting to load one from the key manager.');

        $this->_publicKey = $this->getKeyManager()->load('payment/smartex/public_key');

        if (true === empty($this->_publicKey)) {
            $this->debugData('[INFO] In Smartex_Core_Helper_Data::getPublicKey(): could not load a public key from the key manager, generating a new one.');
            $this->generateAndSaveKeys();
        } else {
            $this->debugData('[INFO] In Smartex_Core_Helper_Data::getPublicKey(): successfully loaded public key from the key manager, returning that.');
            return $this->_publicKey;
        }

        if (false === empty($this->_publicKey)) {
            $this->debugData('[INFO] In Smartex_Core_Helper_Data::getPublicKey(): successfully generated a new public key.');
            return $this->_publicKey;
        } else {
            $this->debugData('[ERROR] In Smartex_Core_Helper_Data::getPublicKey(): could not load or generate a new public key. Cannot continue!');
            throw new \Exception('In Smartex_Core_Helper_Data::getPublicKey(): could not load or generate a new public key. Cannot continue!');
        }
    }

    public function getPrivateKey()
    {
        if (false === empty($this->_privateKey)) {
            $this->debugData('[INFO] In Smartex_Core_Helper_Data::getPrivateKey(): found an existing private key, returning that.');
            return $this->_privateKey;
        }

        if (true === empty($this->_autoloaderRegistered)) {
            $this->registerAutoloader();
        }

        $this->debugData('[INFO] In Smartex_Core_Helper_Data::getPrivateKey(): did not find an existing private key, attempting to load one from the key manager.');

        $this->_privateKey = $this->getKeyManager()->load('payment/smartex/private_key');

        if (true === empty($this->_privateKey)) {
            $this->debugData('[INFO] In Smartex_Core_Helper_Data::getPrivateKey(): could not load a private key from the key manager, generating a new one.');
            $this->generateAndSaveKeys();
        } else {
            $this->debugData('[INFO] In Smartex_Core_Helper_Data::getPrivateKey(): successfully loaded private key from the key manager, returning that.');
            return $this->_privateKey;
        }

        if (false === empty($this->_privateKey)) {
            $this->debugData('[INFO] In Smartex_Core_Helper_Data::getPrivateKey(): successfully generated a new private key.');
            return $this->_privateKey;
        } else {
            $this->debugData('[ERROR] In Smartex_Core_Helper_Data::getPrivateKey(): could not load or generate a new private key. Cannot continue!');
            throw new \Exception('In Smartex_Core_Helper_Data::getPrivateKey(): could not load or generate a new private key. Cannot continue!');
        }
    }

    /**
     * @return Smartex\KeyManager
     */
    public function getKeyManager()
    {
        if (true === empty($this->_keyManager)) {
            if (true === empty($this->_autoloaderRegistered)) {
                $this->registerAutoloader();
            }

            $this->_keyManager = new Smartex\KeyManager(new Smartex\Storage\MagentoStorage());

            if (false === isset($this->_keyManager) || true === empty($this->_keyManager)) {
                $this->debugData('[ERROR] In Smartex_Core_Helper_Data::getKeyManager(): could not create new Smartex KeyManager object. Cannot continue!');
                throw new \Exception('In Smartex_Core_Helper_Data::getKeyManager(): could not create new Smartex KeyManager object. Cannot continue!');
            } else {
                $this->debugData('[INFO] In Smartex_Core_Helper_Data::getKeyManager(): successfully created new Smartex KeyManager object.');
            }
        }

        return $this->_keyManager;
    }

    /**
     * @return Smartex\Client
     */
    public function getSmartexClient()
    {
        if (false === empty($this->_client)) {
            return $this->_client;
        }

        if (true === empty($this->_autoloaderRegistered)) {
            $this->registerAutoloader();
        }

        $this->_client = new Smartex\Client\Client();

        if (false === isset($this->_client) || true === empty($this->_client)) {
            $this->debugData('[ERROR] In Smartex_Core_Helper_Data::getSmartexClient(): could not create new Smartex Client object. Cannot continue!');
            throw new \Exception('In Smartex_Core_Helper_Data::getSmartexClient(): could not create new Smartex Client object. Cannot continue!');
        } else {
            $this->debugData('[INFO] In Smartex_Core_Helper_Data::getSmartexClient(): successfully created new Smartex Client object.');
        }

        if(\Mage::getStoreConfig('payment/smartex/network') === 'livenet') {
          $network = new Smartex\Network\Livenet();
        } else {
          $network = new Smartex\Network\Testnet();
        }
        $adapter = new Smartex\Client\Adapter\CurlAdapter();

        $this->_client->setPublicKey($this->getPublicKey());
        $this->_client->setPrivateKey($this->getPrivateKey());
        $this->_client->setNetwork($network);
        $this->_client->setAdapter($adapter);
        $this->_client->setToken($this->getToken());

        return $this->_client;
    }

    public function getToken()
    {
        if (true === empty($this->_autoloaderRegistered)) {
            $this->registerAutoloader();
        }

        $token = new Smartex\Token();

        if (false === isset($token) || true === empty($token)) {
            $this->debugData('[ERROR] In Smartex_Core_Helper_Data::getToken(): could not create new Smartex Token object. Cannot continue!');
            throw new \Exception('In Smartex_Core_Helper_Data::getToken(): could not create new Smartex Token object. Cannot continue!');
        } else {
            $this->debugData('[INFO] In Smartex_Core_Helper_Data::getToken(): successfully created new Smartex Token object.');
        }

        $token->setToken(\Mage::getStoreConfig('payment/smartex/token'));

        return $token;
    }
}
