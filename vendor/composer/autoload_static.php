<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInite69a772e915154d18729e1d7ddb585ea
{
    public static $prefixLengthsPsr4 = array (
        'T' => 
        array (
            'TapTree\\WooCommerce\\' => 20,
        ),
        'P' => 
        array (
            'Psr\\Log\\' => 8,
            'Psr\\Container\\' => 14,
        ),
        'M' => 
        array (
            'Monolog\\' => 8,
        ),
        'I' => 
        array (
            'Inpsyde\\Modularity\\' => 19,
            'Inpsyde\\EnvironmentChecker\\' => 27,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'TapTree\\WooCommerce\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
        'Psr\\Log\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/log/Psr/Log',
        ),
        'Psr\\Container\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/container/src',
        ),
        'Monolog\\' => 
        array (
            0 => __DIR__ . '/..' . '/monolog/monolog/src/Monolog',
        ),
        'Inpsyde\\Modularity\\' => 
        array (
            0 => __DIR__ . '/..' . '/inpsyde/modularity/src',
        ),
        'Inpsyde\\EnvironmentChecker\\' => 
        array (
            0 => __DIR__ . '/../..' . '/pluginEnvironmentChecker',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInite69a772e915154d18729e1d7ddb585ea::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInite69a772e915154d18729e1d7ddb585ea::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInite69a772e915154d18729e1d7ddb585ea::$classMap;

        }, null, ClassLoader::class);
    }
}