<?php
namespace Alice\Configuration;

use Illuminate\Support\Facades\Facade;

class AliceConfigurationFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'alice-configuration'; // This should match the key used in the service provider
    }
}
