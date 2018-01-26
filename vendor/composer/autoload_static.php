<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit59326ced126251fe7e556fbc1858f556
{
    public static $files = array (
        '5b154887902198b16314243c6e0e3e19' => __DIR__ . '/..' . '/pguardiario/phpuri/phpuri.php',
        'b45b351e6b6f7487d819961fef2fda77' => __DIR__ . '/..' . '/jakeasmith/http_build_url/src/http_build_url.php',
    );

    public static $prefixLengthsPsr4 = array (
        'p' => 
        array (
            'pcfreak30\\' => 10,
        ),
        'D' => 
        array (
            'Dice\\' => 5,
        ),
        'C' => 
        array (
            'ComposePress\\Core\\' => 18,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'pcfreak30\\' => 
        array (
            0 => __DIR__ . '/..' . '/pcfreak30/wordpress-cache-store/src',
        ),
        'Dice\\' => 
        array (
            0 => __DIR__ . '/..' . '/level-2/dice',
        ),
        'ComposePress\\Core\\' => 
        array (
            0 => __DIR__ . '/..' . '/composepress/core/src',
        ),
    );

    public static $fallbackDirsPsr4 = array (
        0 => __DIR__ . '/../..' . '/lib',
        1 => __DIR__ . '/../..' . '/tests',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit59326ced126251fe7e556fbc1858f556::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit59326ced126251fe7e556fbc1858f556::$prefixDirsPsr4;
            $loader->fallbackDirsPsr4 = ComposerStaticInit59326ced126251fe7e556fbc1858f556::$fallbackDirsPsr4;

        }, null, ClassLoader::class);
    }
}
