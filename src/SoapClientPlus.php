<?php namespace DCarbone\SoapPlus;

/*
   Copyright 2012-2016 Daniel Carbone (daniel.p.carbone@gmail.com)

   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

       http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.
*/

use DCarbone\CurlPlus\CurlOptHelper;
use DCarbone\CurlPlus\CurlPlusClient;

/**
 * Class SoapClientPlus
 * @package DCarbone\SoapPlus
 *
 * @property array options
 * @property array soapOptions
 * @property array debugQueries
 * @property \DCarbone\CurlPlus\CurlPlusResponse[] debugResults
 * @property string wsdlCachePath
 * @property string wsdlTmpFileName
 */
class SoapClientPlus extends \SoapClient
{
    /**
     * @readonly
     * @var string
     */
    protected $_wsdlCachePath = null;

    /** @var \DCarbone\CurlPlus\CurlPlusClient */
    protected $curlPlusClient;

    /**
     * @readonly
     * @var array
     */
    protected $_options;

    /**
     * @readonly
     * @var array
     */
    protected $_soapOptions;

    /**
     * @readonly
     * @var string
     */
    protected $_wsdlTmpFileName = null;

    /** @var bool */
    protected $clearTmpFileOnDestruct = false;

    /** @var array */
    protected $curlOptArray = array();

    /** @var array */
    protected $_defaultCurlOptArray = array();

    /** @var array */
    protected $_defaultRequestHeaders = array();

    /** @var array */
    protected $requestHeaders = array();

    /**
     * @readonly
     * @var array
     */
    protected $_debugQueries = array();

    /**
     * @readonly
     * @var array
     */
    protected $_debugResults = array();

    /** @var int */
    protected $_sxeArgs;

    /**
     * Constructor
     *
     * @param string $wsdl
     * @param array $options
     * @throws \RuntimeException
     */
    public function __construct($wsdl, array $options = array())
    {
        $this->curlPlusClient = new CurlPlusClient();
        $this->_options = $options;
        $this->_wsdlCachePath = static::setupWSDLCachePath($options);
        $this->curlOptArray = $this->_defaultCurlOptArray = static::createCurlOptArray($options);
        $this->_soapOptions = static::createSoapOptionArray($options);
        $this->requestHeaders = $this->_defaultRequestHeaders = array(
            'Content-type' => 'text/xml;charset="utf-8"',
            'Accept' => 'text/xml',
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
        );

        if ($wsdl !== null && strtolower(substr($wsdl, 0, 4)) === 'http')
            $wsdl = $this->loadWSDL($wsdl);

        if (defined('LIBXML_PARSEHUGE'))
            $this->_sxeArgs = LIBXML_COMPACT | LIBXML_NOBLANKS | LIBXML_PARSEHUGE;
        else
            $this->_sxeArgs = LIBXML_COMPACT | LIBXML_NOBLANKS;

        parent::__construct($wsdl, $this->_soapOptions);
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        if ($this->clearTmpFileOnDestruct && file_exists($this->_wsdlCachePath.$this->_wsdlTmpFileName))
            @unlink($this->_wsdlCachePath.$this->_wsdlTmpFileName);
    }

    /**
     * @param string $param
     * @return array
     * @throws \OutOfBoundsException
     */
    public function __get($param)
    {
        switch($param)
        {
            case 'options':
                return $this->_options;

            case 'soapOptions':
                return $this->_soapOptions;

            case 'debugQueries':
                return $this->_debugQueries;

            case 'debugResults':
                return $this->_debugResults;

            case 'wsdlCachePath':
                return $this->_wsdlCachePath;

            case 'wsdlTmpFileName':
                return $this->_wsdlTmpFileName;

            default:
                throw new \OutOfBoundsException('Object does not have public property with name "'.$param.'".');
        }
    }

    /**
     * Load that WSDL file
     *
     * @param $wsdlURL
     * @return string
     * @throws \Exception
     */
    protected function loadWSDL($wsdlURL)
    {
        // Get a SHA1 hash of the full WSDL url to use as the cache filename
        $this->_wsdlTmpFileName = sha1(strtolower($wsdlURL)).'.xml';

        // Get the runtime soap cache configuration
        $soapCache = ini_get('soap.wsdl_cache_enabled');

        // Get the passed in cache parameter, if there is one.
        if (isset($this->_options['cache_wsdl']))
            $optCache = $this->_options['cache_wsdl'];
        else
            $optCache = null;

        // By default defer to the global cache value
        $cache = $soapCache != '0' ? true : false;

        // If they specifically pass in a cache value, use it.
        if ($optCache !== null)
        {
            switch($optCache)
            {
                case WSDL_CACHE_MEMORY :
                    throw new \RuntimeException('WSDL_CACHE_MEMORY is not supported by SoapClientPlus');
                case WSDL_CACHE_BOTH :
                    throw new \RuntimeException('WSDL_CACHE_BOTH is not supported by SoapClientPlus');

                case WSDL_CACHE_DISK : $cache = true; break;
                case WSDL_CACHE_NONE : $cache = false; break;
            }
        }

        $this->clearTmpFileOnDestruct = !$cache;

        // If cache === true, attempt to load from cache.
        if ($cache === true)
        {
            $path = $this->loadWSDLFromCache();
            if ($path !== null)
                return $path;
        }

        // Otherwise move on!

        // First, load the wsdl
        $this->curlPlusClient->initialize($wsdlURL);
        $this->curlPlusClient->setCurlOpts($this->curlOptArray);
        $response = $this->curlPlusClient->execute(true);

        // Check for error
        if ($response->httpCode != 200 || $response->responseBody === false)
            throw new \Exception('Error thrown while trying to retrieve WSDL file: "'.$response->curlError.'"');

        // Create a local copy of WSDL file and return the file path to it.
        $path = $this->createWSDLCache(trim((string)$response->responseBody));
        return $path;
    }

    /**
     * Try to return the WSDL file path string
     *
     * @return null|string
     */
    protected function loadWSDLFromCache()
    {
        $filePath = $this->_wsdlCachePath.$this->_wsdlTmpFileName;

        if (file_exists($filePath))
            return $filePath;

        return null;
    }

    /**
     * Create the WSDL cache file
     *
     * @param string $wsdlString
     * @return string
     */
    protected function createWSDLCache($wsdlString)
    {
        $file = $this->_wsdlCachePath.$this->_wsdlTmpFileName;
        file_put_contents($file, $wsdlString);
        return $file;
    }

    /**
     * @return string|null
     */
    public function getWSDLTmpFileName()
    {
        if (isset($this->_wsdlTmpFileName))
            return $this->_wsdlTmpFileName;

        return null;
    }

    /**
     * @return bool
     */
    public function debugEnabled()
    {
        if (!isset($this->_options['debug']))
            return false;

        return (bool)$this->_options['debug'];
    }

    /**
     * @deprecated Deprecated since 0.8.  Use debugEnabled instead
     * @return bool
     */
    protected function isDebug()
    {
        return $this->debugEnabled();
    }

    /**
     * @return void
     */
    public function enableDebug()
    {
        $this->_options['debug'] = true;
    }

    /**
     * @return void
     */
    public function disableDebug()
    {
        $this->_options['debug'] = false;
    }

    /**
     * @return array
     */
    public function getDebugQueries()
    {
        return $this->_debugQueries;
    }

    /**
     * @return array
     */
    public function getDebugResults()
    {
        return $this->_debugResults;
    }

    /**
     * @return void
     */
    public function resetDebugValue()
    {
        $this->_debugQueries = array();
        $this->_debugResults = array();
    }

    /**
     * Just directly call the __soapCall method.
     *
     * @deprecated
     * @param string $function_name
     * @param string $arguments
     * @return mixed
     */
    public function __call($function_name, $arguments)
    {
        array_unshift($arguments, $function_name);
        return call_user_func_array('self::__soapCall', $arguments);
    }

    /**
     * __soapCall overload
     *
     * @param string $function_name
     * @param array $arguments
     * @param array $options
     * @param array $input_headers
     * @param array $output_headers
     * @return mixed|void
     */
    public function __soapCall($function_name, $arguments, $options = array(), $input_headers = array(), &$output_headers = array())
    {
        if (is_string($arguments))
            $arguments = $this->createArgumentArrayFromXML($arguments, $function_name);

        return parent::__soapCall($function_name, $arguments, $options, $input_headers, $output_headers);
    }

    /**
     * Parse things because soap.
     *
     * @param $arguments
     * @param $function_name
     * @return array
     * @throws \Exception
     */
    public function createArgumentArrayFromXML($arguments, $function_name)
    {
        try {
            libxml_use_internal_errors(true);
            $sxe = new \SimpleXMLElement(trim($arguments), $this->_sxeArgs, str_contains($arguments, 'http'));
        }
        catch (\Exception $e)
        {
            // If they have a catcher later on...
            libxml_use_internal_errors(false);

            if (false === ($lastError = libxml_get_last_error()))
            {
                throw new \RuntimeException(
                    sprintf(
                        '%s::createArgumentArrayFromXML - Error found while parsing ActionBody: "%s"',
                        get_class($this),
                        $e->getMessage()
                    ),
                    $e->getCode(),
                    $e
                );
            }

            throw new \RuntimeException(
                sprintf(
                    '%s::createArgumentArrayFromXML - Error found while parsing ActionBody: "%s"',
                    get_class($this),
                    $lastError->message
                ),
                $e->getCode(),
                $e
            );
        }

        // If no exception...
        libxml_use_internal_errors(false);

        if (!($sxe instanceof \SimpleXMLElement))
        {
            if (false === ($lastError = libxml_get_last_error()))
            {
                throw new \RuntimeException(sprintf(
                    '%s::createArgumentArrayFromXML - Encountered unknown error while parsing ActionBody.',
                    get_class($this)
                ));
            }

            throw new \RuntimeException(
                sprintf(
                    '%s::createArgumentArrayFromXML - Error found while parsing ActionBody: "%s"',
                    get_class($this),
                    $lastError->message
                )
            );
        }

        $name = $sxe->getName();
        $array = array($function_name => array());

        foreach($sxe->children() as $element)
        {
            /** @var $element \SimpleXMLElement */
            $this->parseXML($element, $array[$name]);
        }

        unset($sxe);

        return $array;
    }

    /**
     * @param \SimpleXMLElement $element
     * @param array $array
     * @return void
     */
    protected function parseXML(\SimpleXMLElement $element, array &$array)
    {
        /** @var \SimpleXMLElement[] $children */
        $children = $element->children();
//        $attributes = $element->attributes();
        $elementValue = trim((string)$element);
        $elementName = $element->getName();

        if (count($children) > 0)
        {
            if ($elementName === 'any')
            {
                $child = $children[0];
                $array[$elementName] = $child->saveXML();
            }
            else
            {
                if (!isset($array[$elementName]))
                    $array[$elementName] = array();

                foreach($children as $child)
                {
                    $this->parseXML($child, $array[$elementName]);
                }
            }
        }
        else
        {
            $array[$elementName] = $elementValue;
        }
    }

    /**
     * Execute SOAP request using CURL
     *
     * @param string $request
     * @param string $location
     * @param string $action
     * @param int $version
     * @param int $one_way
     * @return string
     * @throws \Exception
     */
    public function __doRequest($request, $location, $action, $version, $one_way = 0)
    {
        $this->curlPlusClient->initialize($location, true);
        $this->curlPlusClient->setCurlOpt(CURLOPT_POSTFIELDS, (string)$request);
        $this->curlPlusClient->setCurlOpts($this->curlOptArray);

        // Add the header strings
        foreach($this->requestHeaders as $k=>$v)
        {
            $this->getCurlClient()->setRequestHeader($k, $v);
        }
        $this->curlPlusClient->setRequestHeader('SOAPAction', $action);

        $ret = $this->curlPlusClient->execute();

        if ($this->debugEnabled())
        {
            $this->_debugQueries[] = array(
                'headers' => $ret->getRequestHeaderArray(),
                'body' => (string)$request,
            );

            $this->_debugResults[] = $ret;
        }

        if ($ret->responseBody == false || $ret->httpCode !== 200)
            throw new \Exception('DCarbone\SoapClientPlus::__doRequest - CURL Error during call: "'. addslashes($ret->curlError).'", "'.addslashes($ret->responseBody).'"');

        return $ret->responseBody;
    }

    /**
     * @return CurlPlusClient
     */
    public function getCurlClient()
    {
        return $this->curlPlusClient;
    }

    /**
     * @param array $requestHeaders
     * @return void
     */
    public function setRequestHeaders(array $requestHeaders)
    {
        // For backwards compatibility sakes.
        $key = key($requestHeaders);
        if (is_int($key))
        {
            $this->requestHeaders = array();
            foreach($requestHeaders as $header)
            {
                $exp = explode(':', $header, 2);
                $this->requestHeaders[$exp[0]] = $exp[1];
            }
        }
        else
        {
            $this->requestHeaders = $requestHeaders;
        }
    }

    /**
     * @return array
     */
    public function getRequestHeaders()
    {
        return $this->requestHeaders;
    }

    /**
     * @deprecated No longer differentiating between "user" and "default" request headers.  Use getRequestHeaders instead
     * @return array
     */
    public function getDefaultRequestHeaders()
    {
        return $this->requestHeaders;
    }

    /**
     * @deprecated No longer differentiating between "user" and "default" request headers.  Use setRequestHeaders instead
     * @param array $headers
     * @return $this
     */
    public function setDefaultRequestHeaders(array $headers)
    {
        $this->requestHeaders = $headers;
        return $this;
    }

    /**
     * @param string $name
     * @param string $value
     * @return $this
     */
    public function setRequestHeader($name, $value)
    {
        $this->requestHeaders[$name] = $value;
        return $this;
    }

    /**
     * @deprecated This method has been deprecated in favor of the setRequestHeader method
     *
     * @param string $header
     * @return $this
     */
    public function addRequestHeaderString($header)
    {
        $exp = explode(':', $header, 2);
        return $this->setRequestHeader($exp[0], $exp[1]);
    }

    /**
     * @return $this
     */
    public function resetRequestHeaders()
    {
        $this->requestHeaders = $this->_defaultRequestHeaders;
        return $this;
    }

    /**
     * @param int $opt
     * @param mixed $value
     * @return $this
     */
    public function setCurlOpt($opt, $value)
    {
        $this->curlOptArray[$opt] = $value;
        return $this;
    }

    /**
     * @param int $opt
     * @return $this
     */
    public function removeCurlOpt($opt)
    {
        if (isset($this->curlOptArray[$opt]))
            unset($this->curlOptArray[$opt]);

        return $this;
    }

    /**
     * @param array $opts
     * @return $this
     */
    public function setCurlOpts(array $opts)
    {
        $this->curlOptArray = $opts;
        return $this;
    }

    /**
     * @param bool $humanReadable
     * @return array
     */
    public function getCurlOpts($humanReadable = false)
    {
        if ($humanReadable)
            return CurlOptHelper::createHumanReadableCurlOptArray($this->curlOptArray);

        return $this->curlOptArray;
    }

    /**
     * @return $this
     */
    public function resetCurlOpts()
    {
        $this->curlOptArray = $this->_defaultCurlOptArray;
        return $this;
    }

    /**
     * Reset to new state
     *
     * @return SoapClientPlus
     */
    public function reset()
    {
        $this->getCurlClient()->reset();
        $this->resetCurlOpts();
        $this->resetRequestHeaders();
        return $this;
    }

    // -----------------------------------------------------------------------------------------------------------------

    /**
     * @param array $options
     * @return string
     * @throws \RuntimeException
     */
    protected static function setupWSDLCachePath(array $options)
    {
        if (isset($options['wsdl_cache_path']))
            $wsdlCachePath = $options['wsdl_cache_path'];
        else
            $wsdlCachePath = sys_get_temp_dir();

        $realpath = realpath($wsdlCachePath);

        if ($realpath === false)
        {
            $created = (bool)@mkdir($wsdlCachePath);

            if ($created === false)
                throw new \RuntimeException('Could not find / create WSDL cache directory at path "'.$wsdlCachePath.'".');

            $realpath = realpath($wsdlCachePath);
        }

        if (!is_writable($realpath))
            throw new \RuntimeException('WSDL Cache Directory "'.$realpath.'" is not writable.');

        $realpath .= DIRECTORY_SEPARATOR;

        return $realpath;
    }

    /**
     * @param array $options
     * @return array
     * @throws \InvalidArgumentException
     */
    protected static function createCurlOptArray(array $options)
    {
        $curlOptArray =  array(
            CURLOPT_FAILONERROR => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLINFO_HEADER_OUT => true,
        );
        if (isset($options['login']) && isset($options['password']))
        {
            // Set the password in the client
            $curlOptArray[CURLOPT_USERPWD] = $options['login'].':'.$options['password'];

            // Attempt to set the Auth type requested
            if (isset($options['auth_type']))
            {
                $authType = strtolower($options['auth_type']);
                switch($authType)
                {
                    case 'basic'    : $curlOptArray[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;   break;
                    case 'ntlm'     : $curlOptArray[CURLOPT_HTTPAUTH] = CURLAUTH_NTLM;    break;
                    case 'digest'   : $curlOptArray[CURLOPT_HTTPAUTH] = CURLAUTH_DIGEST;  break;
                    case 'any'      : $curlOptArray[CURLOPT_HTTPAUTH] = CURLAUTH_ANY;     break;
                    case 'anysafe'  : $curlOptArray[CURLOPT_HTTPAUTH] = CURLAUTH_ANYSAFE; break;

                    default :
                        throw new \InvalidArgumentException('Unknown Authentication type "'.$options['auth_type'].'" requested');
                }
            }
            else
            {
                $curlOptArray[CURLOPT_HTTPAUTH] = CURLAUTH_ANY;
            }
        }

        return $curlOptArray;
    }

    /**
     * @param array $options
     * @return array
     */
    protected static function createSoapOptionArray(array $options)
    {
        if (isset($options['login']))
            unset($options['login']);

        if (isset($options['password']))
            unset($options['password']);

        if (isset($options['wsdl_cache_path']))
            unset($options['wsdl_cache_path']);

        if (isset($options['auth_type']))
            unset($options['auth_type']);

        if (isset($options['debug']))
            unset($options['debug']);

        $options['exceptions'] = 1;
        $options['trace'] = 1;
        $options['cache_wsdl'] = WSDL_CACHE_NONE;

        if (!isset($options['user_agent']))
            $options['user_agent'] = 'SoapClientPlus';

        return $options;
    }
}
