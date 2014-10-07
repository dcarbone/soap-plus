<?php

if (is_dir(sys_get_temp_dir().'/SoapClientPlus'))
{
    foreach(glob(sys_get_temp_dir().'/SoapClientPlus/*.xml') as $wsdl)
        @unlink($wsdl);

    rmdir(sys_get_temp_dir().'/SoapClientPlus');
}

if (is_dir(__DIR__.'/wsdl-cache'))
{
    foreach(glob(__DIR__.'/wsdl-cache/*.xml') as $wsdl)
        @unlink($wsdl);

    @rmdir(__DIR__.'/wsdl-cache');
}