<?php
/**
 * Abstract backend driver model
 *
 * @package     Cm_Diehard
 * @author      Colin Mollenhour
 */
abstract class Cm_Diehard_Model_Backend_Abstract
{

    const INJECTION_JS   = 'js';
    const INJECTION_AJAX = 'ajax';
    const INJECTION_ESI  = 'esi';

    protected $_name = '';

    protected static $_cacheKey;

    /**
     * If true, the backend supports Ajax dynamic block replacement (should be all)
     * 
     * @var bool
     */
    protected $_useAjax;

    /**
     * If true, the backend supports ESI for dynamic block replacement (should be all)
     *
     * @var bool
     */
    protected $_useEsi;

    /**
     * If true, the backend supports inline javascript for dynamic block replacement (only backends that do not use a caching proxy)
     *
     * @var bool
     */
    protected $_useJs;

    /**
     * @return Cm_Diehard_Helper_Data
     */
    public function helper()
    {
        static $helper;
        if ( ! $helper) {
            // Bypass loading using factory pattern in case config is not present
            if ( ! ($helper = Mage::registry('_helper/diehard'))) {
                $helper = new Cm_Diehard_Helper_Data();
                Mage::register('_helper/diehard', $helper);
            }
        }
        return $helper;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     * Set lazily and only once
     *
     * @return string
     */
    public function getCacheKey()
    {
        if ( ! self::$_cacheKey) {
            $request = Mage::app()->getRequest();
            self::$_cacheKey = strtoupper(implode('_', [
                'DIEHARD',
                $this->_name,
                // getStore() is not yet available this early in processRequest(), so key
                // on the `store` run-cookie that selects the store. Essential where store
                // views share a hostname (e.g. all stores on one local/staging host);
                // harmless in production (per-store hostnames). The ___store switch param
                // is already covered via getRequestUri().
                $request->getCookie(Mage_Core_Model_Store::COOKIE_NAME, ''),
                $request->getScheme(),
                $request->getHttpHost(FALSE),
                $request->getRequestUri(),
                $request->getCookie(Cm_Diehard_Helper_Data::COOKIE_CACHE_KEY_DATA, '')
                // Design?
            ]));
        }
        return self::$_cacheKey;
    }

    /**
     * @return string
     */
    public function getInjectionMethod()
    {
        return Mage::getStoreConfig('system/diehard/injection');
    }

    /**
     * @return bool
     */
    public function useAjax()
    {
        if ($this->getInjectionMethod() == self::INJECTION_AJAX) {
            if ( ! $this->_useAjax) {
                Mage::throwException('Ajax injection method is not supported by the selected backend.');
            }
            return TRUE;
        }
        return FALSE;
    }
    
    /**
     * @return bool
     */
    public function useEsi()
    {
        if ($this->getInjectionMethod() == self::INJECTION_ESI) {
            if ( ! $this->_useEsi) {
                Mage::throwException('ESI injection method is not supported by the selected backend.');
            }
            return TRUE;
        }
        return FALSE;
    }

    /**
     * @return bool
     */
    public function useJs()
    {
        if ($this->getInjectionMethod() == self::INJECTION_JS) {
            if ( ! $this->_useJs) {
                Mage::throwException('Javascript injection method is not supported by the selected backend.');
            }
            return TRUE;
        }
        return FALSE;
    }

    /**
     * @param string $body
     * @return array|bool
     */
    public function extractParamsFromBody($body)
    {
        if ( ! preg_match('|<!-- ###DIEHARD:(.+)### -->|', $body, $matches)) {
            return FALSE;
        }
        return json_decode($matches[1], true);
    }

    /**
     * @param string $body
     * @param string $replace
     * @return string
     */
    public function replaceParamsInBody($body, $replace)
    {
        return preg_replace('|<!-- ###DIEHARD:(.+)### -->|', $replace, $body, 1);
    }

    /**
     * @return string
     */
    public function getBaseUrl()
    {
        $scheme = Mage::app()->getRequest()->getScheme();
        $host = $_SERVER['HTTP_HOST'];
        $uri = $scheme . '://' . $host;
        return $uri;
    }

    abstract public function flush();

    abstract public function cleanCache($tags);

    abstract public function httpResponseSendBefore(Mage_Core_Controller_Response_Http $response, $lifetime);

}
