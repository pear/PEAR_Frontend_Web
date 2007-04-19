<?php
/**
 * Installation of PEAR_Frontend_Web: 'pear install PEAR_Frontend_Web'
 *
 * Create a __secure__ directory accessable by your webserver
 * put a file like this one in there.
 * Create a directory for PEAR to be installed in and add it to
 * the include path. (Yes. you can use your standard PEAR dir,
 * but the Webserver needs writing access, so I think for this
 * beta software a new directory is more safe)
 * Specify a file for your PEAR config.
 * Have fun ...
 *
 * @category   pear
 * @package    PEAR_Frontend_Web
 * @author     Christian Dickmann <dickmann@php.net>
 * @copyright  1997-2007 The PHP Group
 * @license    http://www.php.net/license/2_02.txt  PHP License 2.02
 * @version    CVS: $Id$
 * @link       http://pear.php.net/package/PEAR_Frontend_Web
 * @since      File available since Release 0.2
 */

if (!getenv('PHP_PEAR_SYSCONF_DIR')) {
    // Use this config instead of the global one
    if (is_file(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'pear.conf')) {
        putenv('PHP_PEAR_SYSCONF_DIR=' . dirname(__FILE__));
        $pear_user_config = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'pear.conf';
    }
}

/**
 * set PHP_PEAR_INSTALL_DIR (php_dir if unset)
 */
if ($env=getenv('PHP_PEAR_INSTALL_DIR')) {
    define("PHP_PEAR_INSTALL_DIR",$env);
} else {
    putenv('PHP_PEAR_INSTALL_DIR=@php_dir@');
}

/**
 * set PHP_PEAR_BIN_DIR (bin_dir if unset)
 */
if ($env=getenv('PHP_PEAR_BIN_DIR')) {
    define("PHP_PEAR_BIN_DIR",$env);
} else {
    putenv('PHP_PEAR_BIN_DIR=@bin_dir@');
}
/**
 * set PHP_PEAR_BIN_DIR (php_bin if unset)
 */
if ($env=getenv('PHP_PEAR_PHP_BIN')) {
    define("PHP_PEAR_PHP_BIN",$env);
} else {
    putenv('PHP_PEAR_PHP_BIN=@php_bin@');
}

// Default for sig_bin
putenv('PHP_PEAR_SIG_BIN=""');
// Default for sig_keydir
putenv('PHP_PEAR_SIG_KEYDIR=""');

$env=getenv('PHP_PEAR_INSTALL_DIR');
require_once($env.'/PEAR.php');
if (OS_WINDOWS) {
    $seperator = ';';
} else {
    $seperator = ':';
};

ini_set('include_path', '@include_path@');

// Include WebInstaller
require_once("PEAR/WebInstaller.php");
?>
