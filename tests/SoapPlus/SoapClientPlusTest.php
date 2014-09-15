<?php

require __DIR__.'/../misc/cleanup.php';

/**
 * Class SoapClientTest
 */
class SoapClientTest extends \PHPUnit_Framework_TestCase
{
    public static $weatherWSDL = 'http://wsf.cdyne.com/WeatherWS/Weather.asmx?WSDL';

    /**
     * @covers \DCarbone\SoapPlus\SoapClientPlus::__construct
     * @covers \DCarbone\SoapPlus\SoapClientPlus::parseOptions
     * @covers \DCarbone\SoapPlus\SoapClientPlus::loadWSDL
     * @covers \DCarbone\SoapPlus\SoapClientPlus::loadWSDLFromCache
     * @covers \DCarbone\SoapPlus\SoapClientPlus::createWSDLCache
     * @uses \DCarbone\SoapPlus\SoapClientPlus
     * @uses \DCarbone\CurlPlus\CurlPlusClient
     */
    public function testCanConstructSoapClientPlusWithNoOptions()
    {
        $soapClient = new \DCarbone\SoapPlus\SoapClientPlus(self::$weatherWSDL);

        $this->assertInstanceOf('\\DCarbone\\SoapPlus\\SoapClientPlus', $soapClient);
    }

    /**
     * @covers \DCarbone\SoapPlus\SoapClientPlus::__construct
     * @covers \DCarbone\SoapPlus\SoapClientPlus::parseOptions
     * @covers \DCarbone\SoapPlus\SoapClientPlus::loadWSDL
     * @covers \DCarbone\SoapPlus\SoapClientPlus::loadWSDLFromCache
     * @covers \DCarbone\SoapPlus\SoapClientPlus::createWSDLCache
     * @covers \DCarbone\SoapPlus\SoapClientPlus::getWSDLTmpFileName
     * @uses \DCarbone\SoapPlus\SoapClientPlus
     * @uses \DCarbone\CurlPlus\CurlPlusClient
     */
    public function testCanConstructSoapClientPlusWithCustomCacheDirectory()
    {
        \DCarbone\SoapPlus\SoapClientPlus::$wsdlCachePath = null;
        $soapClient = new \DCarbone\SoapPlus\SoapClientPlus(self::$weatherWSDL, array('wsdl_cache_path' => sys_get_temp_dir().'/SoapClientPlus'));

        $this->assertInstanceOf('\\DCarbone\\SoapPlus\\SoapClientPlus', $soapClient);
        $this->assertFileExists(
            \DCarbone\SoapPlus\SoapClientPlus::$wsdlCachePath.$soapClient->getWSDLTmpFileName()
        );
    }
}
