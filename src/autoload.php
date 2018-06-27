<?php

/**
 * Autoloader for Globee\PaymentApi
 */
spl_autoload_register(
    function ($class) {

        $namespaces = [
            'GloBee\\WooCommerce\\' => __DIR__.'/',
            'GloBee\\PaymentApi\\' => __DIR__.'/lib/',
        ];

        foreach ($namespaces as $prefix => $path) {
            // does the class use the namespace prefix?
            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                // no, move to the next namespace / registered autoloader
                continue;
            }

            // get the relative class name
            $relative_class = substr($class, $len);

            // replace the namespace prefix with the base directory, replace namespace
            // separators with directory separators in the relative class name, append
            // with .php
            $file = $path.str_replace('\\', '/', $relative_class).'.php';

            // if the file exists, require it
            if (file_exists($file)) {
                require $file;

                return;
            }
        }
    }
);