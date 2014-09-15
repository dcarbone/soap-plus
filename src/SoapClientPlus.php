<?php namespace DCarbone\SoapPlus;

use DCarbone\CurlPlus\CurlPlusClient;
use DCarbone\CurlPlus\ICurlPlusContainer;

/**
 * Class SoapClientPlus
 * @package DCarbone\SoapPlus
 */
class SoapClientPlus extends \SoapClient implements ICurlPlusContainer
{
    /** @var string */
    public static $wsdlCachePath;

    /** @var \DCarbone\CurlPlus\CurlPlusClient */
    protected $curlPlusClient;

    /** @var array */
    protected $options;

    /** @var array */
    protected $soapOptions;

    protected $wsdlTmpFileName;
    protected $clearTmpFileOnDestruct = false;

    /** @var array */
    protected $curlOptArray = array(
        CURLOPT_FAILONERROR => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLINFO_HEADER_OUT => true,
    );

    /** @var array */
    protected $defaultRequestHeaders = array(
        'Content-type: text/xml;charset="utf-8"',
        'Accept: text/xml',
        'Cache-Control: no-cache',
        'Pragma: no-cache',
    );

    /** @var string */
    protected $login = null;
    /** @var string */
    protected $password = null;

    /** @var array */
    protected $debugQueries = array();
    /** @var array */
    protected $debugResults = array();

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

        if (!isset(self::$wsdlCachePath))
        {
            if (isset($options['wsdl_cache_path']))
            {
                $wsdlCachePath = $options['wsdl_cache_path'];

                unset($options['wsdl_cache_path']);
            }
            else
            {
                $wsdlCachePath = sys_get_temp_dir();
            }

            $realpath = realpath($wsdlCachePath);

            if ($realpath === false)
            {
                $created = mkdir($wsdlCachePath);

                if ($created === false)
                    throw new \RuntimeException(get_class($this).'::__construct - Could not find / create WSDL cache directory!');
            }

            self::$wsdlCachePath = $realpath;
        }

        $lastChr = substr(self::$wsdlCachePath, -1);
        if ($lastChr !== '\\' && $lastChr !== '/')
            self::$wsdlCachePath .= DIRECTORY_SEPARATOR;

        if (is_writable(self::$wsdlCachePath) !== true)
            throw new \RuntimeException(get_class($this).'::__construct - WSDL cache directory is not writable!');

        $this->options = $options;
        $this->parseOptions();

        if ($wsdl !== null && strtolower(substr($wsdl, 0, 4)) === 'http')
            $wsdl = $this->loadWSDL($wsdl);

        parent::SoapClient($wsdl, $this->soapOptions);
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        if ($this->clearTmpFileOnDestruct && file_exists(self::$wsdlCachePath.$this->wsdlTmpFileName))
            @unlink(self::$wsdlCachePath.$this->wsdlTmpFileName);
    }

    /**
     * Parse the options array
     *
     * @throws \InvalidArgumentException
     * @return void
     */
    protected function parseOptions()
    {
        $this->soapOptions = $this->options;
        $this->soapOptions['exceptions'] = 1;
        $this->soapOptions['trace'] = 1;
        $this->soapOptions['cache_wsdl'] = WSDL_CACHE_NONE;

        if (!isset($this->options['user_agent']))
            $this->soapOptions['user_agent'] = 'SoapClientPlus';

        if (isset($this->soapOptions['debug']))
            unset($this->soapOptions['debug']);

        if (isset($this->options['login']) && isset($this->options['password']))
        {
            $this->login = $this->options['login'];
            $this->password = $this->options['password'];
            unset($this->soapOptions['login'], $this->soapOptions['password']);

            // Set the password in the client
            $this->curlOptArray[CURLOPT_USERPWD] = $this->login.':'.$this->password;

            // Attempt to set the Auth type requested
            if (isset($this->options['auth_type']))
            {
                $authType = strtolower($this->options['auth_type']);
                switch($authType)
                {
                    case 'basic'    : $this->curlOptArray[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;   break;
                    case 'ntlm'     : $this->curlOptArray[CURLOPT_HTTPAUTH] = CURLAUTH_NTLM;    break;
                    case 'digest'   : $this->curlOptArray[CURLOPT_HTTPAUTH] = CURLAUTH_DIGEST;  break;
                    case 'any'      : $this->curlOptArray[CURLOPT_HTTPAUTH] = CURLAUTH_ANY;     break;
                    case 'anysafe'  : $this->curlOptArray[CURLOPT_HTTPAUTH] = CURLAUTH_ANYSAFE; break;

                    default :
                        throw new \InvalidArgumentException('Unknown Authentication type "'.$this->options['auth_type'].'" requested');
                }
                unset($this->soapOptions['auth_type']);
            }
            else
            {
                $this->curlOptArray[CURLOPT_HTTPAUTH] = CURLAUTH_ANY;
            }
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
        $this->wsdlTmpFileName = sha1(strtolower($wsdlURL)).'.xml';

        // Get the runtime soap cache configuration
        $soapCache = ini_get('soap.wsdl_cache_enabled');

        // Get the passed in cache parameter, if there is one.
        if (isset($this->options['cache_wsdl']))
            $optCache = $this->options['cache_wsdl'];
        else
            $optCache = null;

        // By default defer to the global cache value
        $cache = $soapCache != '0' ? true : false;
        $this->clearTmpFileOnDestruct = !$cache;

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
            throw new \Exception('SoapClientPlus - Error thrown while trying to retrieve WSDL file: "'.$response->getError().'"');

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
        $filePath = static::$wsdlCachePath.$this->wsdlTmpFileName;

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
        $file = fopen(static::$wsdlCachePath.$this->wsdlTmpFileName, 'w+');
        fwrite($file, $wsdlString, strlen($wsdlString));
        fclose($file);
        return static::$wsdlCachePath.$this->wsdlTmpFileName;
    }

    /**
     * @return string|null
     */
    public function getWSDLTmpFileName()
    {
        if (isset($this->wsdlTmpFileName))
            return $this->wsdlTmpFileName;

        return null;
    }

    /**
     * @return bool
     */
    protected function isDebug()
    {
        if (!isset($this->options['debug']))
            return false;

        return (bool)$this->options['debug'];
    }

    /**
     * @return void
     */
    public function enableDebug()
    {
        $this->options['debug'] = true;
    }

    /**
     * @return void
     */
    public function disableDebug()
    {
        $this->options['debug'] = false;
    }

    /**
     * @return array
     */
    public function getDebugQueries()
    {
        return $this->debugQueries;
    }

    /**
     * @return array
     */
    public function getDebugResults()
    {
        return $this->debugResults;
    }

    /**
     * @return void
     */
    public function resetDebugValue()
    {
        $this->debugQueries = array();
        $this->debugResults = array();
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
                throw new \RuntimeException(
                    get_class($this).'::createArgumentArrayFromXML - Error found while parsing ActionBody: "'.
                    libxml_get_last_error()->message.'"', $e->getCode(), $e);
            else
                throw new \RuntimeException(
                    get_class($this).'::createArgumentArrayFromXML - Error found while parsing ActionBody: "'.
                    $e->getMessage().'"', $e->getCode(), $e);
        }

        if (!($sxe instanceof \SimpleXMLElement))
        {
            if (libxml_get_last_error() !== false)
                throw new \RuntimeException(
                    get_class($this).'::createArgumentArrayFromXML - Error found while parsing ActionBody: "'.
                    libxml_get_last_error()->message.'"');
            else
                throw new \RuntimeException(
                    get_class($this).'::createArgumentArrayFromXML - Encountered unknown error while parsing ActionBody.');
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
        foreach($this->defaultRequestHeaders as $headerString)
        {
            $this->getClient()->addRequestHeaderString($headerString);
        }
        $this->curlPlusClient->addRequestHeaderString('SOAPAction: "'.$action.'"');
        
        $ret = $this->curlPlusClient->execute();

        if ($this->isDebug())
        {
            $this->debugQueries[] = array(
                'headers' => $ret->getRequestHeaders(),
                'body' => (string)$request,
            );

            $this->debugResults[] = array(
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
        $this->defaultRequestHeaders = $requestHeaders;
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
    public function &getClient()
    {
        return $this->curlPlusClient;
    }

    /**
     * @return array
     */
    public function getRequestHeaders()
    {
        return $this->getClient()->getRequestHeaders();
    }

    /**
     * @param array $headers
     * @return $this
     */
    public function setRequestHeaders(array $headers)
    {
        $this->getClient()->setRequestHeaders($headers);
        return $this;
    }

    /**
     * @param string $header
     * @return $this
     */
    public function addRequestHeaderString($header)
    {
        $this->getClient()->addRequestHeaderString($header);
        return $this;
    }

    /**
     * @param int $opt
     * @param mixed $value
     * @return $this
     */
    public function setCurlOpt($opt, $value)
    {
        $this->getClient()->setCurlOpt($opt, $value);
        return $this;
    }

    /**
     * @param int $opt
     * @return $this
     */
    public function removeCurlOpt($opt)
    {
        $this->getClient()->removeCurlOpt($opt);
        return $this;
    }

    /**
     * @param array $opts
     * @return $this
     */
    public function setCurlOpts(array $opts)
    {
        $this->getClient()->setCurlOpts($opts);
        return $this;
    }

    /**
     * @param bool $humanReadable
     * @return array
     */
    public function getCurlOpts($humanReadable = false)
    {
        return $this->getClient()->getCurlOpts($humanReadable);
    }

    /**
     * @return $this
     */
    public function resetCurlOpts()
    {
        $this->getClient()->reset();
        return $this;
    }

    /**
     * Reset to new state
     *
     * @return ICurlPlusContainer
     */
    public function reset()
    {
        $this->getClient()->reset();
        return $this;
    }
}
