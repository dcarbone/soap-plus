<?php namespace DCarbone\SoapPlus;

use DCarbone\CurlPlus\CurlPlusClient;
use DCarbone\CurlPlus\Error\CurlErrorBase;
use DCarbone\CurlPlus\IOCurlContainer;

/**
 * Class SoapClientPlus
 * @package DCarbone\SoapPlus
 */
class SoapClientPlus extends \SoapClient implements IOCurlContainer
{
    /** @var \DCarbone\CurlPlus\CurlPlusClient */
    protected $curlClient;

    /** @var array */
    protected $options;

    /** @var array */
    protected $soapOptions;

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

    /** @var string */
    protected static $wsdlCachePath;

    /**
     * Constructor
     *
     * @param string $wsdl
     * @param array $options
     * @throws \Exception
     * @return \DCarbone\SoapPlus\SoapClientPlus
     */
    public function __construct($wsdl, array $options = array())
    {
        $this->curlClient = new CurlPlusClient();

        static::$wsdlCachePath = realpath(__DIR__.DIRECTORY_SEPARATOR.'WSDL');

        if (!is_writable(static::$wsdlCachePath))
            throw new \Exception('DCarbone::SoapPlus - WSDL temp directory is not writable!');

        $this->options = $options;
        $this->parseOptions();

        if ($wsdl !== null && strtolower(substr($wsdl, 0, 4)) === 'http')
            $wsdl = $this->loadWSDL($wsdl);

        parent::SoapClient($wsdl, $this->soapOptions);
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
        $wsdlName = sha1(strtolower($wsdlURL));

        // Get the runtime soap cache configuration
        $soapCache = ini_get('soap.wsdl_cache_enabled');

        // Get the passed in cache parameter, if there is one.
        $optCache = isset($this->options['cache_wsdl']) ? $this->options['cache_wsdl'] : null;

        // By default defer to the global cache value
        $cache = $soapCache != '0' ? true : false;

        // If they specifically pass in a cache value, use it.
        if ($optCache !== null)
        {
            switch($optCache)
            {
                case WSDL_CACHE_MEMORY :
                    throw new \Exception('WSDL_CACHE_MEMORY is not yet supported by SoapPlus');
                case WSDL_CACHE_BOTH :
                    throw new \Exception('WSDL_CACHE_BOTH is not yet supported by SoapPlus');

                case WSDL_CACHE_DISK : $cache = true; break;
                case WSDL_CACHE_NONE : $cache = false; break;
            }
        }

        // If cache === true, attempt to load from cache.
        if ($cache === true)
        {
            $path = $this->loadWSDLFromCache($wsdlName);
            if ($path !== null)
                return $path;
        }

        // Otherwise move on!

        // First, load the wsdl
        $this->curlClient->setRequestUrl($wsdlURL);
        $this->curlClient->setCurlOpts($this->curlOptArray);
        $response = $this->curlClient->execute();
        // Check for error
        if ($response instanceof CurlErrorBase)
            throw new \Exception('Error thrown while trying to retrieve WSDL file: "'.$response->getError().'"');

        // If caching is enabled, go ahead and return the file path value
        if ($cache === true)
            return $this->createWSDLCache($wsdlName, trim((string)$response));

        // Else create a "temp" file and return the file path to it.
        $path = $this->createWSDLTempFile($wsdlName, trim((string)$response));
        return $path;
    }

    /**
     * Try to return the WSDL file path string
     *
     * @param string $wsdlName
     * @return null|string
     */
    protected function loadWSDLFromCache($wsdlName)
    {
        $filePath = static::$wsdlCachePath.DIRECTORY_SEPARATOR.$wsdlName.'.xml';

        if (file_exists($filePath))
            return $filePath;

        return null;
    }

    /**
     * Create the WSDL cache file
     *
     * @param string $wsdlName
     * @param string $wsdlString
     * @return string
     */
    protected function createWSDLCache($wsdlName, $wsdlString)
    {
        $file = fopen(static::$wsdlCachePath.DIRECTORY_SEPARATOR.$wsdlName.'.xml', 'w+');
        fwrite($file, $wsdlString, strlen($wsdlString));
        fclose($file);
        return static::$wsdlCachePath.DIRECTORY_SEPARATOR.$wsdlName.'.xml';
    }

    /**
     * For now this is the only way I'm aware of to get SoapClient to play nice.
     *
     * @param $wsdlName
     * @param $wsdlString
     * @return string
     */
    protected function createWSDLTempFile($wsdlName, $wsdlString)
    {
        $file = fopen(static::$wsdlCachePath.DIRECTORY_SEPARATOR.'Temp'.DIRECTORY_SEPARATOR.$wsdlName.'.xml', 'w+');
        fwrite($file, $wsdlString, strlen($wsdlString));
        fclose($file);
        return static::$wsdlCachePath.DIRECTORY_SEPARATOR.'Temp'.DIRECTORY_SEPARATOR.$wsdlName.'.xml';
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
            $sxe = @new \SimpleXMLElement(trim($arguments), LIBXML_COMPACT | LIBXML_PARSEHUGE | LIBXML_NOBLANKS);
        }
        catch (\Exception $e)
        {
            if (libxml_get_last_error() !== false)
                throw new \Exception('DCarbone\SoapClientPlus::createArgumentArrayFromXML - Error found while parsing ActionBody: "'.libxml_get_last_error()->message.'"');
            else
                throw new \Exception('DCarbone\SoapClientPlus::createArgumentArrayFromXML - Error found while parsing ActionBody: Unknown');
        }

        if (!($sxe instanceof \SimpleXMLElement))
            throw new \Exception('DCarbone\SoapClientPlus::createArgumentArrayFromXML - Error found while parsing ActionBody: "'.libxml_get_last_error()->message.'"');

        $array = array($function_name => array());
        foreach($sxe->children() as $element)
        {
            /** @var $element \SimpleXMLElement */
            $children = $element->children();
            $attributes = $element->attributes();
            $value = trim((string)$element);

            if (count($children) > 0)
                $array[$sxe->getName()][$element->getName()]['any'] = reset($children)->saveXML();
            else
                $array[$sxe->getName()][$element->getName()] = $value;
        }

        return $array;
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
        $this->curlClient->setRequestUrl($location);
        $this->curlClient->setCurlOpt(CURLOPT_POSTFIELDS, $request);
        $this->curlClient->setCurlOpts($this->curlOptArray);

        // Add the header strings
        foreach($this->defaultRequestHeaders as $headerString)
        {
            $this->getClient()->addRequestHeaderString($headerString);
        }
        $this->curlClient->addRequestHeaderString('SOAPAction: "'.$action.'"');

        $ret = $this->curlClient->execute();
        if ($ret instanceof CurlErrorBase)
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
        return $this->curlClient;
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
     * @return void
     */
    public function setRequestHeaders(array $headers)
    {
        $this->getClient()->setRequestHeaders($headers);
    }

    /**
     * @param string $header
     * @return void
     */
    public function addRequestHeaderString($header)
    {
        $this->getClient()->addRequestHeaderString($header);
    }

    /**
     * @param int $opt
     * @param mixed $value
     * @return void
     */
    public function setCurlOpt($opt, $value)
    {
        $this->getClient()->setCurlOpt($opt, $value);
    }

    /**
     * @param int $opt
     * @return void
     */
    public function removeCurlOpt($opt)
    {
        $this->getClient()->removeCurlOpt($opt);
    }

    /**
     * @param array $opts
     * @return void
     */
    public function setCurlOpts(array $opts)
    {
        $this->getClient()->setCurlOpts($opts);
    }

    /**
     * @return array
     */
    public function getCurlOpts()
    {
        return $this->getClient()->getCurlOpts();
    }

    /**
     * @return void
     */
    public function resetCurlOpts()
    {
        $this->getClient()->resetCurlOpts();
    }

}