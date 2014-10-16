<?php

require __DIR__.'/../misc/cleanup.php';

/**
 * Class SoapClientTest
 */
class SoapClientPlusTest extends \PHPUnit_Framework_TestCase
{
    /** @var string */
    public static $weatherWSDL = 'http://wsf.cdyne.com/WeatherWS/Weather.asmx?WSDL';

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
        $soapClient = new \DCarbone\SoapPlus\SoapClientPlus(self::$weatherWSDL);

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
        $soapClient = new \DCarbone\SoapPlus\SoapClientPlus(self::$weatherWSDL,
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

        $this->assertInternalType('array', $options);
        $this->assertInternalType('array', $soapOptions);
        $this->assertInternalType('array', $debugQueries);
        $this->assertInternalType('array', $debugResults);
        $this->assertInternalType('string', $wsdlCachePath);
        $this->assertInternalType('string', $wsdlTmpName);
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
            'GetCityForecastByZIP' => array(
                'ZIP' => '37209',
            ),
        );

        $response = $soapClient->GetCityForecastByZIP($array);
        $this->assertInternalType('object', $response);
        $this->assertObjectHasAttribute('GetCityForecastByZIPResult', $response);
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
<GetCityForecastByZIP>
    <ZIP>37209</ZIP>
</GetCityForecastByZIP>
XML;

        $response = $soapClient->GetCityForecastByZIP($xml);
        $this->assertInternalType('object', $response);
        $this->assertObjectHasAttribute('GetCityForecastByZIPResult', $response);
    }
}
