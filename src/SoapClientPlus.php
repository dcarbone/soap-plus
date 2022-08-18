<?php declare(strict_types=1);

namespace DCarbone\SoapPlus;

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
     * @var string|null
     */
    protected ?string $_wsdlCachePath;

    /** @var \DCarbone\CurlPlus\CurlPlusClient */
    protected CurlPlusClient $curlPlusClient;

    /**
     * @readonly
     * @var array
     */
    protected array $_options;

    /**
     * @readonly
     * @var array
     */
    protected array $_soapOptions;

    /**
     * @readonly
     * @var string|null
     */
    protected ?string $_wsdlTmpFileName;

    /** @var bool */
    protected bool $clearTmpFileOnDestruct = false;

    /** @var array */
    protected array $curlOptArray = [];

    /** @var array */
    protected array $_defaultCurlOptArray = [];

    /** @var array */
    protected array $_defaultRequestHeaders = [];

    /** @var array */
    protected array $requestHeaders = [];

    /**
     * @readonly
     * @var array
     */
    protected array $_debugQueries = [];

    /**
     * @readonly
     * @var array
     */
    protected array $_debugResults = [];

    /** @var int */
    protected int $_sxeArgs;

    /**
     * Constructor
     *
     * @param string|null $wsdl
     * @param array $options
     * @throws \SoapFault
     */
    public function __construct(?string $wsdl, array $options = array())
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

        if (null !== $wsdl && strtolower(substr($wsdl, 0, 4)) === 'http') {
            $wsdl = $this->loadWSDL($wsdl);
        }

        if (defined('LIBXML_PARSEHUGE')) {
            $this->_sxeArgs = LIBXML_COMPACT | LIBXML_NOBLANKS | LIBXML_PARSEHUGE;
        } else {
            $this->_sxeArgs = LIBXML_COMPACT | LIBXML_NOBLANKS;
        }

        parent::__construct($wsdl, $this->_soapOptions);
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        if ($this->clearTmpFileOnDestruct && file_exists($this->_wsdlCachePath.$this->_wsdlTmpFileName)) {
            @unlink($this->_wsdlCachePath . $this->_wsdlTmpFileName);
        }
    }

    /**
     * @param string $param
     * @return array|string
     * @throws \OutOfBoundsException
     */
    public function __get(string $param)
    {
        return match ($param) {
            'options' => $this->_options,
            'soapOptions' => $this->_soapOptions,
            'debugQueries' => $this->_debugQueries,
            'debugResults' => $this->_debugResults,
            'wsdlCachePath' => $this->_wsdlCachePath,
            'wsdlTmpFileName' => $this->_wsdlTmpFileName,
            default => throw new \OutOfBoundsException('Object does not have public property with name "' . $param . '".'),
        };
    }

    /**
     * Load that WSDL file
     *
     * @param string $wsdlURL
     * @return string
     * @throws \Exception
     */
    protected function loadWSDL(string $wsdlURL): string
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
        $cache = $soapCache != '0';

        // If they specifically pass in a cache value, use it.
        if ($optCache !== null) {
            switch($optCache) {
                case WSDL_CACHE_MEMORY:
                    throw new \RuntimeException('WSDL_CACHE_MEMORY is not supported by SoapClientPlus');
                case WSDL_CACHE_BOTH:
                    throw new \RuntimeException('WSDL_CACHE_BOTH is not supported by SoapClientPlus');

                case WSDL_CACHE_DISK:
                    $cache = true;
                    break;
                case WSDL_CACHE_NONE:
                    $cache = false;
                    break;
            }
        }

        $this->clearTmpFileOnDestruct = !$cache;

        // If cache === true, attempt to load from cache.
        if ($cache === true) {
            $path = $this->loadWSDLFromCache();
            if (null !== $path) {
                return $path;
            }
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
        return $this->createWSDLCache(trim((string)$response->responseBody));
    }

    /**
     * Try to return the WSDL file path string
     *
     * @return null|string
     */
    protected function loadWSDLFromCache(): ?string
    {
        $filePath = $this->_wsdlCachePath.$this->_wsdlTmpFileName;

        if (file_exists($filePath)) {
            return $filePath;
        }

        return null;
    }

    /**
     * Create the WSDL cache file
     *
     * @param string $wsdlString
     * @return string
     */
    protected function createWSDLCache(string $wsdlString): string
    {
        $file = $this->_wsdlCachePath.$this->_wsdlTmpFileName;
        file_put_contents($file, $wsdlString);
        return $file;
    }

    /**
     * @return string|null
     */
    public function getWSDLTmpFileName(): ?string
    {
        if (isset($this->_wsdlTmpFileName))
            return $this->_wsdlTmpFileName;

        return null;
    }

    /**
     * @return bool
     */
    public function debugEnabled(): bool
    {
        return (bool)($this->_options['debug'] ?? false);
    }

    /**
     * @deprecated Deprecated since 0.8.  Use debugEnabled instead
     * @return bool
     */
    protected function isDebug(): bool
    {
        return $this->debugEnabled();
    }

    /**
     * @return void
     */
    public function enableDebug(): void
    {
        $this->_options['debug'] = true;
    }

    /**
     * @return void
     */
    public function disableDebug(): void
    {
        $this->_options['debug'] = false;
    }

    /**
     * @return array
     */
    public function getDebugQueries(): array
    {
        return $this->_debugQueries;
    }

    /**
     * @return array
     */
    public function getDebugResults(): array
    {
        return $this->_debugResults;
    }

    /**
     * @return void
     */
    public function resetDebugValue(): void
    {
        $this->_debugQueries = [];
        $this->_debugResults = [];
    }

    /**
     * Just directly call the __soapCall method.
     *
     * @param string $name
     * @param string|array $args
     * @return mixed
     * @deprecated
     */
    public function __call(string $name, $args)
    {
        array_unshift($args, $name);
        return call_user_func_array('self::__soapCall', $args);
    }

    /**
     * __soapCall overload
     *
     * @param string $name
     * @param string|array $args
     * @param array $options
     * @param array $inputHeaders
     * @param array $outputHeaders
     * @return mixed|void
     * @throws \Exception
     */
    public function __soapCall(string $name, string|array $args, $options = [], $inputHeaders = [], &$outputHeaders = [])
    {
        if (is_string($args)) {
            $args = $this->createArgumentArrayFromXML($args, $name);
        }

        return parent::__soapCall($name, $args, $options, $inputHeaders, $outputHeaders);
    }

    /**
     * Parse things because soap.
     *
     * @param string $arguments
     * @param string $function_name
     * @return array
     */
    public function createArgumentArrayFromXML(string $arguments, string $function_name): array
    {
        try {
            libxml_use_internal_errors(true);
            $sxe = new \SimpleXMLElement(trim($arguments), $this->_sxeArgs, str_contains($arguments, 'http'));
        } catch (\Exception $e) {
            // If they have a catcher later on...
            libxml_use_internal_errors(false);

            if (false === ($lastError = libxml_get_last_error())) {
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

        if (!($sxe instanceof \SimpleXMLElement)) {
            if (false === ($lastError = libxml_get_last_error())) {
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

        foreach($sxe->children() as $element) {
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
    protected function parseXML(\SimpleXMLElement $element, array &$array): void
    {
        /** @var \SimpleXMLElement[] $children */
        $children = $element->children();
//        $attributes = $element->attributes();
        $elementValue = trim((string)$element);
        $elementName = $element->getName();

        if (count($children) > 0) {
            if ($elementName === 'any') {
                $child = $children[0];
                $array[$elementName] = $child->saveXML();
            } else {
                if (!isset($array[$elementName])) {
                    $array[$elementName] = array();
                }
                foreach($children as $child) {
                    $this->parseXML($child, $array[$elementName]);
                }
            }
        } else {
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
     * @param int $oneWay
     * @return string
     * @throws \Exception
     */
    public function __doRequest(string $request, string $location, string $action, int $version, $oneWay = 0): string
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

        if (false == $ret->responseBody || 200 !== $ret->httpCode) {
            throw new \Exception('DCarbone\SoapClientPlus::__doRequest - CURL Error during call: "' . addslashes($ret->curlError) . '", "' . addslashes($ret->responseBody) . '"');
        }

        return $ret->responseBody;
    }

    /**
     * @return CurlPlusClient
     */
    public function getCurlClient(): CurlPlusClient
    {
        return $this->curlPlusClient;
    }

    /**
     * @param array $requestHeaders
     * @return void
     */
    public function setRequestHeaders(array $requestHeaders): void
    {
        // For backwards compatibility sakes.
        $key = key($requestHeaders);
        if (is_int($key)) {
            $this->requestHeaders = array();
            foreach($requestHeaders as $header) {
                $exp = explode(':', $header, 2);
                $this->requestHeaders[$exp[0]] = $exp[1];
            }
        } else {
            $this->requestHeaders = $requestHeaders;
        }
    }

    /**
     * @return array
     */
    public function getRequestHeaders(): array
    {
        return $this->requestHeaders;
    }

    /**
     * @deprecated No longer differentiating between "user" and "default" request headers.  Use getRequestHeaders instead
     * @return array
     */
    public function getDefaultRequestHeaders(): array
    {
        return $this->requestHeaders;
    }

    /**
     * @deprecated No longer differentiating between "user" and "default" request headers.  Use setRequestHeaders instead
     * @param array $headers
     * @return $this
     */
    public function setDefaultRequestHeaders(array $headers): static
    {
        $this->requestHeaders = $headers;
        return $this;
    }

    /**
     * @param string $name
     * @param string $value
     * @return $this
     */
    public function setRequestHeader(string $name, string $value): static
    {
        $this->requestHeaders[$name] = $value;
        return $this;
    }

    /**
     * @param string $header
     * @return $this
     *@deprecated This method has been deprecated in favor of the setRequestHeader method
     *
     */
    public function addRequestHeaderString(string $header): static
    {
        $exp = explode(':', $header, 2);
        return $this->setRequestHeader($exp[0], $exp[1]);
    }

    /**
     * @return $this
     */
    public function resetRequestHeaders(): static
    {
        $this->requestHeaders = $this->_defaultRequestHeaders;
        return $this;
    }

    /**
     * @param int $opt
     * @param mixed $value
     * @return $this
     */
    public function setCurlOpt(int $opt, mixed $value): static
    {
        $this->curlOptArray[$opt] = $value;
        return $this;
    }

    /**
     * @param int $opt
     * @return $this
     */
    public function removeCurlOpt(int $opt): static
    {
        unset($this->curlOptArray[$opt]);
        return $this;
    }

    /**
     * @param array $opts
     * @return $this
     */
    public function setCurlOpts(array $opts): static
    {
        $this->curlOptArray = $opts;
        return $this;
    }

    /**
     * @param bool $humanReadable
     * @return array
     */
    public function getCurlOpts(bool $humanReadable = false): array
    {
        if ($humanReadable) {
            return CurlOptHelper::createHumanReadableCurlOptArray($this->curlOptArray);
        }

        return $this->curlOptArray;
    }

    /**
     * @return $this
     */
    public function resetCurlOpts(): static
    {
        $this->curlOptArray = $this->_defaultCurlOptArray;
        return $this;
    }

    /**
     * Reset to new state
     *
     * @return SoapClientPlus
     */
    public function reset(): static
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
    protected static function setupWSDLCachePath(array $options): string
    {
        $wsdlCachePath = $options['wsdl_cache_path'] ?? sys_get_temp_dir();

        $realpath = realpath($wsdlCachePath);

        if ($realpath === false) {
            $created = @mkdir($wsdlCachePath);

            if ($created === false) {
                throw new \RuntimeException('Could not find / create WSDL cache directory at path "' . $wsdlCachePath . '".');
            }

            $realpath = realpath($wsdlCachePath);
        }

        if (!is_writable($realpath)) {
            throw new \RuntimeException('WSDL Cache Directory "' . $realpath . '" is not writable.');
        }

        $realpath .= DIRECTORY_SEPARATOR;

        return $realpath;
    }

    /**
     * @param array $options
     * @return array
     * @throws \InvalidArgumentException
     */
    protected static function createCurlOptArray(array $options): array
    {
        $curlOptArray =  [
            CURLOPT_FAILONERROR => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLINFO_HEADER_OUT => true,
        ];
        if (isset($options['login']) && isset($options['password'])) {
            // Set the password in the client
            $curlOptArray[CURLOPT_USERPWD] = $options['login'].':'.$options['password'];

            // Attempt to set the Auth type requested
            if (isset($options['auth_type'])) {
                $authType = strtolower($options['auth_type']);
                $curlOptArray[CURLOPT_HTTPAUTH] = match ($authType) {
                    'basic' => CURLAUTH_BASIC,
                    'ntlm' => CURLAUTH_NTLM,
                    'digest' => CURLAUTH_DIGEST,
                    'any' => CURLAUTH_ANY,
                    'anysafe' => CURLAUTH_ANYSAFE,
                    default => throw new \InvalidArgumentException('Unknown Authentication type "' . $options['auth_type'] . '" requested'),
                };
            } else {
                $curlOptArray[CURLOPT_HTTPAUTH] = CURLAUTH_ANY;
            }
        }

        return $curlOptArray;
    }

    /**
     * @param array $options
     * @return array
     */
    protected static function createSoapOptionArray(array $options): array
    {
        unset(
            $options['login'],
            $options['password'],
            $options['wsdl_cache_path'],
            $options['auth_type'],
            $options['debug']
        );

        $options['exceptions'] = 1;
        $options['trace'] = 1;
        $options['cache_wsdl'] = WSDL_CACHE_NONE;

        if (!isset($options['user_agent'])) {
            $options['user_agent'] = 'SoapClientPlus';
        }

        return $options;
    }
}
