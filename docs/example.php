<?php
/**
 * Installation of PEAR_Frontend_Web:
 *
 * 'pear install PEAR_Frontend_Web'
 * Create a __secure__ directory accessable by your webserver
 * put a file like this one in there.
 * Create a directory for PEAR to be installed in and add it to
 * the include path. (Yes. you can use your standard PEAR dir,
 * but the Webserver needs writing access, so I think for this
 * beta software a new directory is more safe)
 * Specify a file for your PEAR config.
 * Have fun ...
 *
 * by Christian Dickmann <dickmann@php.net>
 */
/*
if ($env=getenv('PHP_PEAR_SYSCONF_DIR')) {
    define("PHP_PEAR_SYSCONF_DIR",$env);
} else {
    putenv("PHP_PEAR_SYSCONF_DIR=d:/www/test1");
}
if ($env=getenv('PHP_PEAR_HTTP_PROXY')) {
    define("PHP_PEAR_HTTP_PROXY",$env);
} else {
    putenv("PHP_PEAR_HTTP_PROXY=");
}
*/
if ($env=getenv('PHP_PEAR_INSTALL_DIR')) {
    define("PHP_PEAR_INSTALL_DIR",$env);
} else {
    putenv('PHP_PEAR_INSTALL_DIR=@php_dir@');
}
/*
if ($env=getenv('PHP_PEAR_EXTENSION_DIR')) {
    define("PHP_PEAR_EXTENSION_DIR",$env);
} else {
    putenv("PHP_PEAR_EXTENSION_DIR=d:/www/test1/pear/PEAR/extensions");
}
*/
/*
if ($env=getenv('PHP_PEAR_DOC_DIR')) {
    define("PHP_PEAR_DOC_DIR",$env);
} else {
    putenv("PHP_PEAR_DOC_DIR=d:/www/test1/pear/PEAR/docs");
}
*/
if ($env=getenv('PHP_PEAR_BIN_DIR')) {
    define("PHP_PEAR_BIN_DIR",$env);
} else {
    putenv('PHP_PEAR_BIN_DIR=@bin_dir@');
}
/*
if ($env=getenv('PHP_PEAR_DATA_DIR')) {
    define("PHP_PEAR_DATA_DIR",$env);
} else {
    putenv("PHP_PEAR_DATA_DIR=d:/www/test1/pear/PEAR/data");
}

if ($env=getenv('PHP_PEAR_TEST_DIR')) {
    define("PHP_PEAR_TEST_DIR",$env);
} else {
    putenv("PHP_PEAR_TEST_DIR=d:/www/test1/pear/PEAR/tests");
}
if ($env=getenv('PHP_PEAR_CACHE_DIR')) {
    define("PHP_PEAR_CACHE_DIR",$env);
} else {
    putenv("PHP_PEAR_CACHE_DIR=d:/www/test1/cache");
}
*/
if ($env=getenv('PHP_PEAR_PHP_BIN')) {
    define("PHP_PEAR_PHP_BIN",$env);
} else {
    putenv('PHP_PEAR_PHP_BIN=@php_bin@');
}
$env=getenv('PHP_PEAR_INSTALL_DIR');
require_once($env.'/PEAR.php');
if (OS_WINDOWS) {
    $seperator = ';';
} else {
    $seperator = ':';
};

// Rebuild Includepath (optional)
ini_set('include_path', '@include_path@');
// Configfile
//$pear_user_config = dirname(__FILE__)."/pear.conf";
$useDHTML         = true;
// Include WebInstaller
require_once("PEAR/WebInstaller.php");
?>
