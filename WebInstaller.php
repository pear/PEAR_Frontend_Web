<?php

    require_once 'PEAR.php';
    require_once 'PEAR/Config.php';
    require_once 'PEAR/Command.php';

    // Cient requests an Image/Stylesheet
    if (isset($_GET["css"]))
    {
        $images = array(
            "style" => "style.css",
            );
        Header("Content-Type: text/css");
        readfile(dirname(__FILE__).'/Frontend/Web/'.$images[$_GET["css"]]);
        exit;
    };
    if (isset($_GET["img"]))
    {
        $images = array(
            "config" => array(
                "type" => "gif",
                "file" => "config.gif",
                ),
            "pkglist" => array(
                "type" => "gif",
                "file" => "pkglist.gif",
                ),
            "package" => array(
                "type" => "jpeg",
                "file" => "package.jpg",
                ),
            "category" => array(
                "type" => "jpeg",
                "file" => "category.jpg",
                ),
            "install" => array(
                "type" => "gif",
                "file" => "install.gif",
                ),
            "uninstall" => array(
                "type" => "gif",
                "file" => "trash.gif",
                ),
            "info" => array(
                "type" => "gif",
                "file" => "info.gif",
                ),
            "infoplus" => array(
                "type" => "gif",
                "file" => "infoplus.gif",
                ),
            "pear" => array(
                "type" => "gif",
                "file" => "pearsmall.gif",
                ),
            "error" => array(
                "type" => "gif",
                "file" => "error.gif",
                ),
            );
        Header("Content-Type: image/".$images[$_GET["img"]]['type']);
        readfile(dirname(__FILE__).'/Frontend/Web/'.$images[$_GET["img"]]['file']);
        exit;
    };

    // Init PEAR Installer Code and WebFrontend
    PEAR_Command::setFrontendType("Web");
    $ui = &PEAR_Command::getFrontendObject();
    PEAR::setErrorHandling(PEAR_ERROR_CALLBACK, array($ui, "displayFatalError"));
  
    $config  = &PEAR_Config::singleton($pear_user_config, '');
//    $config->store('user');
    $verbose = $config->get("verbose");
    $cmdopts = array();
    $opts    = array();
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
            
            if (!$ok) {
                PEAR::raiseError('');
            };
            
            $URL .= '#'.$_GET["pkg"];
            Header("Location: ".$URL);
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
        }
    };
    
    $command = "list-all";
    $params = array();
    if (isset($_GET["info"]))
        $params[$_GET["info"]] = 1;
    $cmd = PEAR_Command::factory($command, $config);
    $ok = $cmd->run($command, $opts, $params);
    
?>