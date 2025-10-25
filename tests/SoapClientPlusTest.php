<?php declare(strict_types=1);

namespace DCarbone\SoapPlus\Tests;

/*
   Copyright 2012-2022 Daniel Carbone (daniel.p.carbone@gmail.com)

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

use DCarbone\SoapPlus\SoapClientPlus;
use InvalidArgumentException;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require __DIR__ . '/misc/cleanup.php';

/**
 * Class SoapClientTest
 */
class SoapClientPlusTest extends TestCase
{
    private const TEMP_CONVERT_WSDL = "https://www.w3schools.com/xml/tempconvert.asmx?WSDL";

    private const WSDL_CACHE_PATH = __DIR__ . '/misc/wsdl-cache';

    private static SoapClientPlus $soapClient;

    public static function setUpBeforeClass(): void
    {
        SoapClientPlusTest::$soapClient = new SoapClientPlus(self::TEMP_CONVERT_WSDL);
    }

    protected function assertPreConditions(): void
    {
        $this->assertInstanceOf(SoapClientPlus::class, self::$soapClient);
    }

    public function testCanCreateLocalCacheOfWSDLToSystemTemp(): void
    {
        $this->assertFileExists(
            self::$soapClient->wsdlCachePath . self::$soapClient->getWSDLTmpFileName()
        );
    }

    public function testCanConstructSoapClientPlusWithCustomCacheDirectory(): void
    {
        $soapClient = new SoapClientPlus(self::TEMP_CONVERT_WSDL, array('wsdl_cache_path' => self::WSDL_CACHE_PATH));
        $this->assertInstanceOf(SoapClientPlus::class, $soapClient);
    }

    public function testCanCreateLocalCacheFileOfWSDLToCustomDir(): void
    {
        $this->assertFileExists(
            self::$soapClient->wsdlCachePath . self::$soapClient->getWSDLTmpFileName()
        );
    }

    public function testCanGetReadOnlyParameters(): void
    {
        $options = self::$soapClient->options;
        $soapOptions = self::$soapClient->soapOptions;
        $debugQueries = self::$soapClient->debugQueries;
        $debugResults = self::$soapClient->debugResults;
        $wsdlCachePath = self::$soapClient->wsdlCachePath;
        $wsdlTmpName = self::$soapClient->wsdlTmpFileName;

        $this->assertIsArray($options);
        $this->assertIsArray($soapOptions);
        $this->assertIsArray($debugQueries);
        $this->assertIsArray($debugResults);
        $this->assertIsString($wsdlCachePath);
        $this->assertIsString($wsdlTmpName);
    }

    public function testExceptionThrownWhenTryingToGetInvalidProperty(): void
    {
        $this->expectException(OutOfBoundsException::class);
        self::$soapClient->nope;
    }

    public function testCanGetWeatherForecastWithArrayRequest(): void
    {
        $array = array(
            'FahrenheitToCelsius' => array(
                'Fahrenheit' => '212',
            ),
        );

        $response = self::$soapClient->FahrenheitToCelsius($array);
        $this->assertIsObject($response);
        $this->assertObjectHasProperty('FahrenheitToCelsiusResult', $response);
        $this->assertEquals(100, $response->FahrenheitToCelsiusResult);
    }

    public function testCanGetWeatherForecastWithXMLRequest(): void
    {
        $xml = <<<XML
<FahrenheitToCelsius>
    <Fahrenheit>212</Fahrenheit>
</FahrenheitToCelsius>
XML;

        $response = self::$soapClient->FahrenheitToCelsius($xml);
        $this->assertIsObject($response);
        $this->assertObjectHasProperty('FahrenheitToCelsiusResult', $response);
        $this->assertEquals(100, $response->FahrenheitToCelsiusResult);
    }

    public function testCanConstructSoapClientPlusWithValidAuthCredentials(): void
    {
        $soapClient = new SoapClientPlus(self::TEMP_CONVERT_WSDL, [
            'wsdl_cache_path' => self::WSDL_CACHE_PATH,
            'login' => 'my_login',
            'password' => 'my_password',
            'auth_type' => 'ntlm',
            'debug' => true
        ]);

        $this->assertInstanceOf('\\DCarbone\\SoapPlus\\SoapClientPlus', $soapClient);

        $options = $soapClient->options;
        $soapOptions =$soapClient->soapOptions;

        $this->assertArrayHasKey('login', $options);
        $this->assertArrayNotHasKey('login', $soapOptions);

        $this->assertArrayHasKey('password', $options);
        $this->assertArrayNotHasKey('password', $soapOptions);

        $this->assertArrayHasKey('wsdl_cache_path', $options);
        $this->assertArrayNotHasKey('wsdl_cache_path', $soapOptions);

        $this->assertArrayHasKey('auth_type', $options);
        $this->assertArrayNotHasKey('auth_type', $soapOptions);
    }

    public function testExceptionThrownWhenInvalidAuthTypeSpecified(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SoapClientPlus(self::TEMP_CONVERT_WSDL, array(
            'wsdl_cache_path' => self::WSDL_CACHE_PATH,
            'login' => 'my_login',
            'password' => 'my_password',
            'auth_type' => 'sandwiches',
        ));
    }

    public function testExceptionThrownWhenAttemptingToSetWSDLCacheMemory(): void
    {
        $this->expectException(RuntimeException::class);
        new SoapClientPlus(self::TEMP_CONVERT_WSDL, array(
            'cache_wsdl' => WSDL_CACHE_MEMORY,
        ));
    }

    public function testExceptionThrownWhenAttemptingToSetWSDLCacheBoth(): void
    {
        $this->expectException(RuntimeException::class);
        $soapClient = new SoapClientPlus(self::TEMP_CONVERT_WSDL, array(
            'cache_wsdl' => WSDL_CACHE_BOTH,
        ));
    }

    public function testDebugStateChange(): void
    {
        $soapClient = new SoapClientPlus(self::TEMP_CONVERT_WSDL);
        $this->assertFalse($soapClient->debugEnabled());

        $soapClient->enableDebug();
        $this->assertTrue($soapClient->debugEnabled());
    }

    public function testCanConstructWithDebugEnabled(): void
    {
        $soapClient = new SoapClientPlus(self::TEMP_CONVERT_WSDL, array(
            'debug' => true,
        ));

        $this->assertTrue($soapClient->debugEnabled());
    }

    public function testCanDisableDebugPostConstruct(): void
    {
        self::$soapClient->disableDebug();

        $this->assertFalse(self::$soapClient->debugEnabled());
    }

    public function testCanGetDefaultRequestHeaders(): void
    {
        $defaultHeaders = self::$soapClient->getRequestHeaders();

        $this->assertIsArray($defaultHeaders);
    }
}
