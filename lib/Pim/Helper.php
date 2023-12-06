<?php

namespace Blackbit\BundleEnabler\lib\Pim;

use Composer\InstalledVersions;

class Helper
{
    public static function getPimcoreVersion() {
        if(class_exists(InstalledVersions::class)) {
            return InstalledVersions::getVersion('pimcore/pimcore');
        }
        return Pimcore\Version::getVersion();
    }
}
