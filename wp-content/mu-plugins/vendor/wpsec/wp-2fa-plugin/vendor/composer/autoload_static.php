<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitb5aee7d15f693db2dfd71761750300fe
{
    public static $prefixLengthsPsr4 = array (
        'W' => 
        array (
            'Wpsec\\twofa\\' => 12,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Wpsec\\twofa\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitb5aee7d15f693db2dfd71761750300fe::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitb5aee7d15f693db2dfd71761750300fe::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInitb5aee7d15f693db2dfd71761750300fe::$classMap;

        }, null, ClassLoader::class);
    }
}
