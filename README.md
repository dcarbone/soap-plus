soap-plus
=========

In a nutshell, this class replaces the internal query mechanism used by PHP's <a href="http://www.php.net/manual/en/class.soapclient.php">SoapClient</a> class.
The main reason for this wrapper was enable consumption of NTLM-authenticated SharePoint SOAP services in a PHP application.
This is not a data-mapper, it simply returns data as the normal SoapClient would, but all of the internal querying
is handled by the PHP CURL library.

## Basic Usage

```php

use DCarbone\SoapPlus\SoapClientPlus;

$soapClientPlus = new SoapClientPlus(
    'my.wsdl',
    array(
        'trace' => 1,
        'exceptions' => 1,
        'login' => 'mylogin',
        'password' => 'mypassword',
        'auth_type' => 'ntlm'
    ));

$arguments = <<<XML
<SoapActionMethod>
    <Query>
        <OtherStuff />
    </Query>
</SoapActionMethod>
>>>

// You may execute this traditional way
$response = $soapClientPlus->SoapActionMethod($arguments);

// Or this other traditional way
$response = $soapClientPlus->__soapCall('SoapActionMethod', $arguments);

```