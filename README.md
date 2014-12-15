soap-plus
=========

Build status:
- master: [![Build Status](https://travis-ci.org/dcarbone/soap-plus.svg?branch=master)](https://travis-ci.org/dcarbone/soap-plus)
- 0.7.2: [![Build Status](https://travis-ci.org/dcarbone/soap-plus.svg?tag=0.7.2)](https://travis-ci.org/dcarbone/soap-plus)

In a nutshell, this class replaces the internal query mechanism used by PHP's <a href="http://www.php.net/manual/en/class.soapclient.php">SoapClient</a> class.
The main reason for this wrapper is to enable consumption of NTLM-authenticated SharePoint SOAP services in a PHP application.
This is not a data-mapper, it simply returns data as the normal SoapClient would, but all of the internal querying
is handled by the PHP CURL library.


## Constructor

The constructor has been overloaded to allow for a few new nifty features.  All of the base construction options are
usable.  To see a list of built-in available options, see here <a href="http://www.php.net/manual/en/soapclient.soapclient.php" target="_blank">http://www.php.net/manual/en/soapclient.soapclient.php</a>.

### Additional Options

**auth_type**

* basic
* ntlm
* digest
* any
* anysafe
* NULL / undefined

These directly relate to the built-in CURLAUTH_XXX options available (<a href="http://www.php.net//manual/en/function.curl-setopt.php" target="_blank">see here</a>, search for "CURLAUTH")
It is entirely optional, and should only be set if you also define `"login"` and `"password"` options.

If you define a remote WSDL, the same parameters will be used for WSDL retrieval as well as querying.

**user_agent**

This property is optional, and allows you to define a custom <a href="http://en.wikipedia.org/wiki/User_agent" target="_blank">User Agent</a> header value in requests.

**debug**

By setting `"debug" => true` in your configuration array, every query and result will be stored in an internally array that can
be accessed via the methods `getDebugQueries()` and `getDebugResults()`. You may also enable/disable debugging post-construct with
`enableDebug()` and `disableDebug()`.

One word of caution on debugging.  SOAP results can often be quite large, meaning you could potentially have lots of memory
being sucked up for the strings that are saved in the internal array. I would recommend NOT enabling this feature anywhere
outside of a dev / local dev environment.  I have also provided a `resetDebugValue()` method which will empty the arrays.

**wsdl_cache_path**

This option will allow you to specify the directory in which the WSDL cache files will be generated.  If no value is passed
for this option, [sys_get_temp_dir](http://php.net/manual/en/function.sys-get-temp-dir.php) value is used.

## Querying

The typical mechanism by which PHP's SoapClient expects results is for them to be in an array.  Coming from the SharePoint world, where most
documentation outlines how to construct queries in XML, this can be a bit tedious.  As such, I have provided a simple XML -> array
conversion functionality in this library.  For example:

**SharePoint Lists GetListItems**

Below is an example XML SOAP query against SharePoint's GetListItems action on the Lists WSDL.

```xml
<GetListItems xmlns='http://schemas.microsoft.com/sharepoint/soap/'>
  <listName>My List Name</listName>
  <rowLimit>150</rowLimit>
  <query>
    <any>
      <Query xmlns=''>
        <Where>
          <And>
            <Eq>
              <FieldRef Name='Column1' />
              <Value Type='Integer'>1</Value>
            </Eq>
            <And>
              <Neq>
                <FieldRef Name='Column3' />
                <Value Type='Text'>value i don't want</Value>
              </Neq>
              <IsNotNull>
                <FieldRef Name='Column3' />
              </IsNotNull>
            </And>
          </And>
        </Where>
        <OrderBy>
          <FieldRef Name='Column1' Ascending='True' />
          <FieldRef Name='Column2' Ascending='True' />
          <FieldRef Name='Column3' Ascending='True' />
        </OrderBy>
      </Query>
    </any>
  </query>
  <viewFields>
    <any>
      <ViewFields>
        <FieldRef Name='Column1' />
        <FieldRef Name='Column2' />
        <FieldRef Name='Column3' />
      </ViewFields>
    </any>
  </viewFields>
  <queryOptions>
    <any>
      <QueryOptions xmlns=''>
        <DateInUtc>TRUE</DateInUtc>
        <IncludeMandatoryColumns>FALSE</IncludeMandatoryColumns>
      </QueryOptions>
    </any>
  </queryOptions>
</GetListItems>
```

Using SoapClientPlus, this is transformed into...

```php
array (
  'GetListItems' =>
  array (
    'listName' => 'My List Name',
    'rowLimit' => '150',
    'query' =>
    array (
      'any' => '<Query xmlns=""><Where><And><Eq><FieldRef Name="Column1"/><Value Type="Integer">1</Value></Eq><And><Neq><FieldRef Name="Column3"/><Value Type="Text">value i don\'t want/Value></Neq><IsNotNull><FieldRef Name="Column3"/></IsNotNull></And></And></Where><OrderBy><FieldRef Name="Column1" Ascending="True"/><FieldRef Name="Column2" Ascending="True"/><FieldRef Name="Column3" Ascending="True"/></OrderBy></Query>',
    ),
    'viewFields' =>
    array (
      'any' => '<ViewFields><FieldRef Name="Column1"/><FieldRef Name="Column2"/><FieldRef Name="Column3"/></ViewFields>',
    ),
    'queryOptions' =>
    array (
      'any' => '<QueryOptions xmlns=""><DateInUtc>TRUE</DateInUtc><IncludeMandatoryColumns>FALSE</IncludeMandatoryColumns></QueryOptions>',
    ),
  ),
)
```

... which is then passed into the standard SoapClient's own `__soapCall` method.

**Note** You do not HAVE to use XML, you may pass in an array.  It is simply there as some people might find it easier to use.
I use PHP's <a href="http://www.php.net//manual/en/class.simplexmlelement.php" target="_blank">SimpleXMLElement</a> implementation
to handle the transformation.

## Questions / Comments

As I stated in the buff, I created this library to help ease the pain of SharePoint services consumption in PHP wherein I had to use
the NTLM auth mechanism.  I am always open to new feature ideas from the community, so if you are using this library and have a
suggestion, please let me know.  I always enjoy a good challenge :)

```
