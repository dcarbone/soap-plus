<?php declare(strict_types=1);

require __DIR__.'/../misc/cleanup.php';

/**
 * Class SoapClientTest
 */
class SoapClientPlusTest extends \PHPUnit\Framework\TestCase
{
    /** @var string */
    public const WEATHER_WSDL = "http://ws.cdyne.com/emailverify/Emailvernotestemail.asmx?WSDL";

    /**
     * @covers \DCarbone\SoapPlus\SoapClientPlus::__construct
     * @covers \DCarbone\SoapPlus\SoapClientPlus::setupWSDLCachePath
     * @covers \DCarbone\SoapPlus\SoapClientPlus::createCurlOptArray
     * @covers \DCarbone\SoapPlus\SoapClientPlus::createSoapOptionArray
     * @covers \DCarbone\SoapPlus\SoapClientPlus::loadWSDL
     * @covers \DCarbone\SoapPlus\SoapClientPlus::loadWSDLFromCache
     * @covers \DCarbone\SoapPlus\SoapClientPlus::createWSDLCache
     * @uses \DCarbone\SoapPlus\SoapClientPlus
     * @uses \DCarbone\CurlPlus\CurlPlusClient
     * @return \DCarbone\SoapPlus\SoapClientPlus
     */
    public function testCanConstructSoapClientPlusWithNoOptions()
    {
		echo self::WEATHER_WSDL;
        $soapClient = new \DCarbone\SoapPlus\SoapClientPlus(self::WEATHER_WSDL);

        $this->assertInstanceOf('\\DCarbone\\SoapPlus\\SoapClientPlus', $soapClient);

        return $soapClient;
    }

    /**
     * @covers \DCarbone\SoapPlus\SoapClientPlus::getWSDLTmpFileName
     * @covers \DCarbone\SoapPlus\SoapClientPlus::createWSDLCache
     * @uses \DCarbone\SoapPlus\SoapClientPlus
     * @depends testCanConstructSoapClientPlusWithNoOptions
     * @param \DCarbone\SoapPlus\SoapClientPlus $soapClient
     */
    public function testCanCreateLocalCacheOfWSDLToSystemTemp(\DCarbone\SoapPlus\SoapClientPlus $soapClient)
    {
        $this->assertFileExists(
            $soapClient->wsdlCachePath.$soapClient->getWSDLTmpFileName()
        );
    }

    /**
     * @covers \DCarbone\SoapPlus\SoapClientPlus::__construct
     * @covers \DCarbone\SoapPlus\SoapClientPlus::setupWSDLCachePath
     * @covers \DCarbone\SoapPlus\SoapClientPlus::createCurlOptArray
     * @covers \DCarbone\SoapPlus\SoapClientPlus::createSoapOptionArray
     * @covers \DCarbone\SoapPlus\SoapClientPlus::loadWSDL
     * @covers \DCarbone\SoapPlus\SoapClientPlus::loadWSDLFromCache
     * @covers \DCarbone\SoapPlus\SoapClientPlus::createWSDLCache
     * @uses \DCarbone\SoapPlus\SoapClientPlus
     * @uses \DCarbone\CurlPlus\CurlPlusClient
     * @return \DCarbone\SoapPlus\SoapClientPlus
     */
    public function testCanConstructSoapClientPlusWithCustomCacheDirectory()
    {
        $soapClient = new \DCarbone\SoapPlus\SoapClientPlus(self::WEATHER_WSDL,
            array('wsdl_cache_path' => __DIR__.'/../misc/wsdl-cache'));

        $this->assertInstanceOf('\\DCarbone\\SoapPlus\\SoapClientPlus', $soapClient);

        return $soapClient;
    }

    /**
     * @covers \DCarbone\SoapPlus\SoapClientPlus::getWSDLTmpFileName
     * @covers \DCarbone\SoapPlus\SoapClientPlus::createWSDLCache
     * @uses \DCarbone\SoapPlus\SoapClientPlus
     * @depends testCanConstructSoapClientPlusWithCustomCacheDirectory
     * @param \DCarbone\SoapPlus\SoapClientPlus $soapClient
     */
    public function testCanCreateLocalCacheFileOfWSDLToCustomDir(\DCarbone\SoapPlus\SoapClientPlus $soapClient)
    {
        $this->assertFileExists(
            $soapClient->wsdlCachePath.$soapClient->getWSDLTmpFileName()
        );
    }

    /**
     * @covers \DCarbone\SoapPlus\SoapClientPlus::__get
     * @uses \DCarbone\SoapPlus\SoapClientPlus
     * @depends testCanConstructSoapClientPlusWithCustomCacheDirectory
     * @param \DCarbone\SoapPlus\SoapClientPlus $soapClient
     */
    public function testCanGetReadOnlyParameters(\DCarbone\SoapPlus\SoapClientPlus $soapClient)
    {
        $options = $soapClient->options;
        $soapOptions = $soapClient->soapOptions;
        $debugQueries = $soapClient->debugQueries;
        $debugResults = $soapClient->debugResults;
        $wsdlCachePath = $soapClient->wsdlCachePath;
        $wsdlTmpName = $soapClient->wsdlTmpFileName;

        $this->assertIsArray($options);
        $this->assertIsArray($soapOptions);
        $this->assertIsArray($debugQueries);
        $this->assertIsArray($debugResults);
        $this->assertIsString($wsdlCachePath);
        $this->assertIsString($wsdlTmpName);
    }

    /**
     * @covers \DCarbone\SoapPlus\SoapClientPlus::__get
     * @uses \DCarbone\SoapPlus\SoapClientPlus
     * @expectedException \OutOfBoundsException
     * @depends testCanConstructSoapClientPlusWithCustomCacheDirectory
     * @param \DCarbone\SoapPlus\SoapClientPlus $soapClient
     */
    public function testExceptionThrownWhenTryingToGetInvalidProperty(\DCarbone\SoapPlus\SoapClientPlus $soapClient)
    {
		$this->expectException(OutOfBoundsException::class);
		$nope = $soapClient->nope;
    }

    /**
     * @covers \DCarbone\SoapPlus\SoapClientPlus::__call
     * @covers \DCarbone\SoapPlus\SoapClientPlus::__soapCall
     * @covers \DCarbone\SoapPlus\SoapClientPlus::__doRequest
     * @covers \DCarbone\SoapPlus\SoapClientPlus::getClient
     * @uses \DCarbone\SoapPlus\SoapClientPlus
     * @depends testCanConstructSoapClientPlusWithCustomCacheDirectory
     * @param \DCarbone\SoapPlus\SoapClientPlus $soapClient
     */
    public function testCanGetWeatherForecastWithArrayRequest(\DCarbone\SoapPlus\SoapClientPlus $soapClient)
    {
        $array = array(
            'VerifyEmail' => array(
                'email' => 'test@gmail.com',
                'LicenseKey' => ''
            ),
        );

        $response = $soapClient->VerifyEmail($array);
        $this->assertIsObject($response);
        $this->assertObjectHasAttribute('VerifyEmailResult', $response);
    }

    /**
     * @covers \DCarbone\SoapPlus\SoapClientPlus::__call
     * @covers \DCarbone\SoapPlus\SoapClientPlus::__soapCall
     * @covers \DCarbone\SoapPlus\SoapClientPlus::createArgumentArrayFromXML
     * @covers \DCarbone\SoapPlus\SoapClientPlus::parseXML
     * @covers \DCarbone\SoapPlus\SoapClientPlus::__doRequest
     * @covers \DCarbone\SoapPlus\SoapClientPlus::getClient
     * @uses \DCarbone\SoapPlus\SoapClientPlus
     * @depends testCanConstructSoapClientPlusWithCustomCacheDirectory
     * @param \DCarbone\SoapPlus\SoapClientPlus $soapClient
     */
    public function testCanGetWeatherForecastWithXMLRequest(\DCarbone\SoapPlus\SoapClientPlus $soapClient)
    {
        $xml = <<<XML
<VerifyEmail>
    <email>test@gmail.com</email>
    <LicenseKey></LicenseKey>
</VerifyEmail>
XML;

        $response = $soapClient->VerifyEmail($xml);
        $this->assertIsObject($response);
        $this->assertObjectHasAttribute('VerifyEmailResult', $response);
    }

    /**
     * @covers \DCarbone\SoapPlus\SoapClientPlus::__construct
     * @covers \DCarbone\SoapPlus\SoapClientPlus::createCurlOptArray
     * @covers \DCarbone\SoapPlus\SoapClientPlus::createSoapOptionArray
     * @uses \DCarbone\SoapPlus\SoapClientPlus
     * @return \DCarbone\SoapPlus\SoapClientPlus
     */
    public function testCanConstructSoapClientPlusWithValidAuthCredentials()
    {
        $soapClient = new \DCarbone\SoapPlus\SoapClientPlus(self::WEATHER_WSDL, array(
            'wsdl_cache_path' => __DIR__.'/../misc/wsdl-cache',
            'login' => 'my_login',
            'password' => 'my_password',
            'auth_type' => 'ntlm',
            'debug' => true
        ));

        $this->assertInstanceOf('\\DCarbone\\SoapPlus\\SoapClientPlus', $soapClient);

        return $soapClient;
    }

    /**
     * @covers \DCarbone\SoapPlus\SoapClientPlus::__construct
     * @covers \DCarbone\SoapPlus\SoapClientPlus::createCurlOptArray
     * @uses \DCarbone\SoapPlus\SoapClientPlus
     * @expectedException \InvalidArgumentException
     */
    public function testExceptionThrownWhenInvalidAuthTypeSpecified()
    {
        $this->expectException(InvalidArgumentException::class);
        $soapClient = new \DCarbone\SoapPlus\SoapClientPlus(self::WEATHER_WSDL, array(
            'wsdl_cache_path' => __DIR__.'/../misc/wsdl-cache',
            'login' => 'my_login',
            'password' => 'my_password',
            'auth_type' => 'sandwiches',
        ));
    }

    /**
     * @covers \DCarbone\SoapPlus\SoapClientPlus::__get
     * @covers \DCarbone\SoapPlus\SoapClientPlus::createSoapOptionArray
     * @uses \DCarbone\SoapPlus\SoapClientPlus
     * @depends testCanConstructSoapClientPlusWithValidAuthCredentials
     * @param \DCarbone\SoapPlus\SoapClientPlus $soapClient
     */
    public function testNonSoapOptionsProperlyRemoved(\DCarbone\SoapPlus\SoapClientPlus $soapClient)
    {
        $options = $soapClient->options;
        $soapOptions = $soapClient->soapOptions;

        $this->assertArrayHasKey('login', $options);
        $this->assertArrayNotHasKey('login', $soapOptions);

        $this->assertArrayHasKey('password', $options);
        $this->assertArrayNotHasKey('password', $soapOptions);

        $this->assertArrayHasKey('wsdl_cache_path', $options);
        $this->assertArrayNotHasKey('wsdl_cache_path', $soapOptions);

        $this->assertArrayHasKey('auth_type', $options);
        $this->assertArrayNotHasKey('auth_type', $soapOptions);
    }

    /**
     * @covers \DCarbone\SoapPlus\SoapClientPlus::loadWSDL
     * @expectedException \RuntimeException
     */
    public function testExceptionThrownWhenAttemptingToSetWSDLCacheMemory()
    {
        $this->expectException(RuntimeException::class);
        $soapClient = new \DCarbone\SoapPlus\SoapClientPlus(self::WEATHER_WSDL, array(
            'cache_wsdl' => WSDL_CACHE_MEMORY,
        ));
    }

    /**
     * @covers \DCarbone\SoapPlus\SoapClientPlus::loadWSDL
     * @expectedException \RuntimeException
     */
    public function testExceptionThrownWhenAttemptingToSetWSDLCacheBoth()
    {
        $this->expectException(RuntimeException::class);
        $soapClient = new \DCarbone\SoapPlus\SoapClientPlus(self::WEATHER_WSDL, array(
            'cache_wsdl' => WSDL_CACHE_BOTH,
        ));
    }

    /**
     * @covers \DCarbone\SoapPlus\SoapClientPlus::debugEnabled
     */
    public function testDebugDisabledByDefault()
    {
        $soapClient = new \DCarbone\SoapPlus\SoapClientPlus(self::WEATHER_WSDL);

        $this->assertFalse($soapClient->debugEnabled());

        return $soapClient;
    }

    /**
     * @covers \DCarbone\SoapPlus\SoapClientPlus::enableDebug
     * @covers \DCarbone\SoapPlus\SoapClientPlus::debugEnabled
     * @depends testDebugDisabledByDefault
     * @param \DCarbone\SoapPlus\SoapClientPlus $soapClient
     */
    public function testCanEnableDebugPostConstruct(\DCarbone\SoapPlus\SoapClientPlus $soapClient)
    {
        $soapClient->enableDebug();

        $this->assertTrue($soapClient->debugEnabled());
    }

    /**
     * @covers \DCarbone\SoapPlus\SoapClientPlus::debugEnabled
     */
    public function testCanConstructWithDebugEnabled()
    {
        $soapClient = new \DCarbone\SoapPlus\SoapClientPlus(self::WEATHER_WSDL, array(
            'debug' => true,
        ));

        $this->assertTrue($soapClient->debugEnabled());

        return $soapClient;
    }

    /**
     * @covers \DCarbone\SoapPlus\SoapClientPlus::disableDebug
     * @depends testCanConstructWithDebugEnabled
     * @param \DCarbone\SoapPlus\SoapClientPlus $soapClient
     */
    public function testCanDisableDebugPostConstruct(\DCarbone\SoapPlus\SoapClientPlus $soapClient)
    {
        $soapClient->disableDebug();

        $this->assertFalse($soapClient->debugEnabled());
    }

    /**
     * @covers \DCarbone\SoapPlus\SoapClientPlus::getCurlClient
     */
    public function testCanGetCurlClient()
    {
        $soapClient = new \DCarbone\SoapPlus\SoapClientPlus(self::WEATHER_WSDL);

        $curlPlusClient = $soapClient->getCurlClient();

        $this->assertInstanceOf('\\DCarbone\\CurlPlus\\CurlPlusClient', $curlPlusClient);

        return $soapClient;
    }

    /**
     * @covers \DCarbone\SoapPlus\SoapClientPlus::getRequestHeaders
     * @depends testCanGetCurlClient
     * @param \DCarbone\SoapPlus\SoapClientPlus $soapClient
     */
    public function testCanGetDefaultRequestHeaders(\DCarbone\SoapPlus\SoapClientPlus $soapClient)
    {
        $defaultHeaders = $soapClient->getRequestHeaders();

        $this->assertIsArray($defaultHeaders);
    }
}
