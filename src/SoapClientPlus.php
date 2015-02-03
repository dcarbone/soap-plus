<?php namespace DCarbone\SoapPlus;

use DCarbone\CurlPlus\CurlPlusClient;
use DCarbone\CurlPlus\ICurlPlusContainer;

/**
 * Class SoapClientPlus
 * @package DCarbone\SoapPlus
 *
 * @property array options
 * @property array soapOptions
 * @property array debugQueries
 * @property array debugResults
 * @property string wsdlCachePath
 * @property string wsdlTmpFileName
 */
class SoapClientPlus extends \SoapClient implements ICurlPlusContainer
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
    protected $defaultRequestHeaders = array(
        'Content-type' => 'text/xml;charset="utf-8"',
        'Accept' => 'text/xml',
        'Cache-Control' => 'no-cache',
        'Pragma' => 'no-cache',
    );

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

    /**
     * Constructor
     *
     * @param string $wsdl
     * @param array $options
     * @throws \RuntimeException
     * @return \DCarbone\SoapPlus\SoapClientPlus
     */
    public function __construct($wsdl, array $options = array())
    {
        $this->curlPlusClient = new CurlPlusClient();
        $this->_options = $options;
        $this->_wsdlCachePath = static::setupWSDLCachePath($options);
        $this->curlOptArray = static::createCurlOptArray($options);
        $this->_soapOptions = static::createSoapOptionArray($options);

        if ($wsdl !== null && strtolower(substr($wsdl, 0, 4)) === 'http')
            $wsdl = $this->loadWSDL($wsdl);

        parent::SoapClient($wsdl, $this->_soapOptions);
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
                    throw new \RuntimeException('WSDL_CACHE_MEMORY is not yet supported by SoapClientPlus');
                case WSDL_CACHE_BOTH :
                    throw new \RuntimeException('WSDL_CACHE_BOTH is not yet supported by SoapClientPlus');

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
        if ($response->getHttpCode() != 200 || $response->getResponse() === false)
            throw new \Exception('Error thrown while trying to retrieve WSDL file: "'.$response->getError().'"');

        // Create a local copy of WSDL file and return the file path to it.
        $path = $this->createWSDLCache(trim((string)$response));
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
        $file = fopen($this->_wsdlCachePath.$this->_wsdlTmpFileName, 'w+');
        fwrite($file, $wsdlString, strlen($wsdlString));
        fclose($file);
        return $this->_wsdlCachePath.$this->_wsdlTmpFileName;
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
            if (defined('LIBXML_PARSEHUGE'))
                $arg = LIBXML_COMPACT | LIBXML_NOBLANKS | LIBXML_PARSEHUGE;
            else
                $arg = LIBXML_COMPACT | LIBXML_NOBLANKS;

            $sxe = @new \SimpleXMLElement(trim($arguments), $arg);
        }
        catch (\Exception $e)
        {
            if (libxml_get_last_error() !== false)
            {
                throw new \RuntimeException(
                    get_class($this).'::createArgumentArrayFromXML - Error found while parsing ActionBody: "'.
                    libxml_get_last_error()->message.'"', $e->getCode(), $e);
            }
            else
            {
                throw new \RuntimeException(
                    get_class($this).'::createArgumentArrayFromXML - Error found while parsing ActionBody: "'.
                    $e->getMessage().'"', $e->getCode(), $e);
            }
        }

        if (!($sxe instanceof \SimpleXMLElement))
        {
            if (libxml_get_last_error() !== false)
            {
                throw new \RuntimeException(
                    get_class($this).'::createArgumentArrayFromXML - Error found while parsing ActionBody: "'.
                    libxml_get_last_error()->message.'"');
            }
            else
            {
                throw new \RuntimeException(
                    get_class($this).'::createArgumentArrayFromXML - Encountered unknown error while parsing ActionBody.');
            }
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
        /** @var array $children */
        $children = $element->children();
//        $attributes = $element->attributes();
        $elementValue = trim((string)$element);
        $elementName = $element->getName();

        if (count($children) > 0)
        {
            if ($elementName === 'any')
            {
                /** @var \SimpleXMLElement $child */
                $child = $children[0];
                $array[$elementName] = $child->saveXML();
            }
            else
            {
                if (!isset($array[$elementName]))
                    $array[$elementName] = array();

                foreach($children as $child)
                {
                    /** @var \SimpleXMLElement $child */
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
        foreach($this->defaultRequestHeaders as $k=>$v)
        {
            $this->getCurlClient()->setRequestHeader($k, $v);
        }
        $this->curlPlusClient->setRequestHeader('SOAPAction', $action);
        
        $ret = $this->curlPlusClient->execute();

        if ($this->debugEnabled())
        {
            $this->_debugQueries[] = array(
                'headers' => $ret->getRequestHeaders(),
                'body' => (string)$request,
            );

            $this->_debugResults[] = array(
                'code' => $ret->getHttpCode(),
                'headers' => $ret->getResponseHeaders(),
                'response' => (string)$ret->getResponse(),
            );
        }

        if ($ret->getResponse() == false || $ret->getHttpCode() != 200)
            throw new \Exception('DCarbone\SoapClientPlus::__doRequest - CURL Error during call: "'. addslashes($ret->getError()).'", "'.addslashes($ret->getResponse()).'"');

        return $ret->getResponse();
    }

    /**
     * @param array $requestHeaders
     * @return void
     */
    public function setDefaultRequestHeaders(array $requestHeaders)
    {
        // For backwards compatibility sakes.
        $key = key($requestHeaders);
        if (is_numeric($key))
        {
            $this->defaultRequestHeaders = array();
            foreach($requestHeaders as $header)
            {
                $exp = explode(':', $requestHeaders, 2);
                $this->defaultRequestHeaders[$exp[0]] = $exp[1];
            }
        }
        else
        {
            $this->defaultRequestHeaders = $requestHeaders;
        }
    }

    /**
     * @return array
     */
    public function getDefaultRequestHeaders()
    {
        return $this->defaultRequestHeaders;
    }

    /**
     * @return CurlPlusClient
     */
    public function getCurlClient()
    {
        return $this->curlPlusClient;
    }

    /**
     * @deprecated This has been deprecated as of dcarbone/curl-plus 1.0.  Use getCurlClient instead.
     * @return CurlPlusClient
     */
    public function &getClient()
    {
        return $this->curlPlusClient;
    }

    /**
     * @return array
     */
    public function getRequestHeaders()
    {
        return $this->getCurlClient()->getRequestHeaders();
    }

    /**
     * @param array $headers
     * @return $this
     */
    public function setRequestHeaders(array $headers)
    {
        $this->getCurlClient()->setRequestHeaders($headers);
        return $this;
    }

    /**
     * @param string $name
     * @param string $value
     * @return $this
     */
    public function setRequestHeader($name, $value)
    {
        $this->getCurlClient()->setRequestHeader($name, $value);
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
     * @param int $opt
     * @param mixed $value
     * @return $this
     */
    public function setCurlOpt($opt, $value)
    {
        $this->getCurlClient()->setCurlOpt($opt, $value);
        return $this;
    }

    /**
     * @param int $opt
     * @return $this
     */
    public function removeCurlOpt($opt)
    {
        $this->getCurlClient()->removeCurlOpt($opt);
        return $this;
    }

    /**
     * @param array $opts
     * @return $this
     */
    public function setCurlOpts(array $opts)
    {
        $this->getCurlClient()->setCurlOpts($opts);
        return $this;
    }

    /**
     * @param bool $humanReadable
     * @return array
     */
    public function getCurlOpts($humanReadable = false)
    {
        return $this->getCurlClient()->getCurlOpts($humanReadable);
    }

    /**
     * @return $this
     */
    public function resetCurlOpts()
    {
        $this->getCurlClient()->reset();
        return $this;
    }

    /**
     * Reset to new state
     *
     * @return ICurlPlusContainer
     */
    public function reset()
    {
        $this->getCurlClient()->reset();
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
