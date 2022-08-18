<?php declare(strict_types=1);

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

if (is_dir(sys_get_temp_dir().'/SoapClientPlus')) {
    foreach(glob(sys_get_temp_dir().'/SoapClientPlus/*.xml') as $wsdl) {
        @unlink($wsdl);
    }

    rmdir(sys_get_temp_dir().'/SoapClientPlus');
}

if (is_dir(__DIR__.'/wsdl-cache')) {
    foreach(glob(__DIR__.'/wsdl-cache/*.xml') as $wsdl) {
        @unlink($wsdl);
    }

    @rmdir(__DIR__.'/wsdl-cache');
}