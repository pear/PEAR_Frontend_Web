<?php
/*
  +----------------------------------------------------------------------+
  | PHP Version 4                                                        |
  +----------------------------------------------------------------------+
  | Copyright (c) 1997-2002 The PHP Group                                |
  +----------------------------------------------------------------------+
  | This source file is subject to version 2.02 of the PHP license,      |
  | that is bundled with this package in the file LICENSE, and is        |
  | available at through the world-wide-web at                           |
  | http://www.php.net/license/2_02.txt.                                 |
  | If you did not receive a copy of the PHP license and are unable to   |
  | obtain it through the world-wide-web, please send a note to          |
  | license@php.net so we can mail you a copy immediately.               |
  +----------------------------------------------------------------------+
  | Author: Christian Dickmann <dickmann@php.net>                        |
  +----------------------------------------------------------------------+

  $Id$
*/
    define('PEAR_Frontend_Web',1);
    @session_start();

    // Include needed files
    require_once 'PEAR.php';
    require_once 'PEAR/Config.php';
    require_once 'PEAR/Command.php';

    // Init PEAR Installer Code and WebFrontend
    PEAR_Command::setFrontendType("Web");
    $ui = &PEAR_Command::getFrontendObject();
    PEAR::setErrorHandling(PEAR_ERROR_CALLBACK, array($ui, "displayFatalError"));
    
    // Cient requests an Image/Stylesheet/Javascript
    if (isset($_GET["css"])) {
        $ui->outputFrontendFile($_GET["css"], 'css');
    };
    if (isset($_GET["js"])) {
        $ui->outputFrontendFile($_GET["js"], 'js');
    };
    if (isset($_GET["img"])) {
        $ui->outputFrontendFile($_GET["img"], 'image');
    };
  
    $config  = &PEAR_Config::singleton($pear_user_config, '');
    $verbose = $config->get("verbose");
    $cmdopts = array();
    $opts    = array();
    $params  = array();
    $URL = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'];
    
    
    // Handle some diffrent Commands
    if (isset($_GET["command"]))
    {
        switch ($_GET["command"]) 
        {
        case 'install':
        case 'uninstall':
        case 'upgrade':
            $command = $_GET["command"];
            $params = array($_GET["pkg"]);
            $cmd = PEAR_Command::factory($command, $config);
            $ok = $cmd->run($command, $opts, $params);
            
            $URL .= '?pageID='.$_GET['pageID'].'#'.$_GET["pkg"];
            Header("Location: ".$URL);
            exit;
        case 'remote-info':
            $command = $_GET["command"];
            $params = array($_GET["pkg"]);
            $cmd = PEAR_Command::factory($command, $config);
            $ok = $cmd->run($command, $opts, $params);
            
            exit;
        case 'search':
            list($name, $description) = $ui->userDialog('search',
                array('Package Name', 'Package Info'), // Prompts
                array(), array(), // Types, Defaults
                'Package Search', 'pkgsearch' // Title, Icon
                );
            
            $command = $_GET["command"];
            $params = array($name, $description);
            $cmd = PEAR_Command::factory($command, $config);
            $ok = $cmd->run($command, $opts, $params);
            
            exit;
        case 'config-show':
            $cmd = PEAR_Command::factory($command, $config);
            $res = $cmd->run($command, $opts, $params);
            foreach($GLOBALS['_PEAR_Frontend_Web_Config'] as $var => $value) {
                $command = 'config-set';
                $params = array($var, $value);
                $cmd = PEAR_Command::factory($command, $config);
                $res = $cmd->run($command, $opts, $params);
            };
            
            $URL .= '?command=config-show';
            Header("Location: ".$URL);
            exit;
        default:
            $cmd = PEAR_Command::factory($command, $config);
            $res = $cmd->run($command, $opts, $params);
            
            $URL .= '?command='.$_GET["command"];
            Header("Location: ".$URL);
            exit;
        }
    };
    
    // If no other command is specified, the standard command 'list-all' is called
    $command = "list-all";
    $params = array();
    if (isset($_GET["mode"]))
        $opts['mode'] = $_GET["mode"];
    $cmd = PEAR_Command::factory($command, $config);
    $ok = $cmd->run($command, $opts, $params);
    
?>