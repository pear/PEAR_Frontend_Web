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

    require_once(dirname(__FILE__).'/PEAR/PEAR.php');
    if (OS_WINDOWS) {
        $seperator = ';';
    } else {
        $seperator = ':';
    };
    
    // Rebuild Includepath (optional)
    $include_path = array(
        '.',
        dirname(__FILE__).'/PEAR',
        );
    ini_set('include_path', implode($seperator, $include_path));

    // Configfile
    $pear_user_config    = dirname(__FILE__)."/pear.conf";
    
    // Include WebInstaller
    require_once("PEAR/WebInstaller.php");
?>