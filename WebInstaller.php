<?php
/**
  +----------------------------------------------------------------------+
  | PHP Version 4                                                        |
  +----------------------------------------------------------------------+
  | Copyright (c) 1997-2003 The PHP Group                                |
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
  |         Pierre-Alain Joye <pajoye@php.net>                           |
  |         Tias Guns <tias@ulyssis.org>                                 |
  +----------------------------------------------------------------------+

 * Web-based PEAR Frontend, include this file to display the fontend.
 * This file does the basic configuration, handles all requests and calls
 * the needed commands.
 *
 * @category   pear
 * @package    PEAR_Frontend_Web
 * @author     Christian Dickmann <dickmann@php.net>
 * @author     Pierre-Alain Joye <pajoye@php.net>
 * @author     Tias Guns <tias@ulyssis.org>
 * @copyright  1997-2007 The PHP Group
 * @license    http://www.php.net/license/2_02.txt  PHP License 2.02
 * @version    CVS: $Id$
 * @link       http://pear.php.net/package/PEAR_Frontend_Web
 * @since      File available since Release 0.1
 */

/**
 * This is PEAR_Frontend_Web
 */
define('PEAR_Frontend_Web',1);
@session_start();

/**
 * base frontend class
 */
require_once 'PEAR/Frontend.php';
require_once 'PEAR/Registry.php';
require_once 'PEAR/Config.php';
require_once 'PEAR/Command.php';

$URL = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'];
$dir = substr(dirname(__FILE__), 0, -strlen('PEAR/PEAR')); // strip PEAR/PEAR
$_ENV['TMPDIR'] = $_ENV['TEMP'] = $dir.'tmp';

if (!isset($pear_user_config)) {
    if (OS_WINDOWS) {
        $conf_name = 'pear.ini';
    } else {
        $conf_name = 'pear.conf';
    }

    $pear_user_config = PEAR_CONFIG_SYSCONFDIR.'/'.$conf_name;
    if (!file_exists($pear_user_config)) {
        // if the global one doesn't exist,
        // try the local one (will be created if unexisting)
        $pear_user_config = $dir.$conf_name;
    }
}

// moving this here allows startup messages and errors to work properly
PEAR_Frontend::setFrontendClass('PEAR_Frontend_Web');
// Init PEAR Installer Code and WebFrontend
$GLOBALS['_PEAR_Frontend_Web_config'] = &PEAR_Config::singleton($pear_user_config, '');
$config  = &$GLOBALS['_PEAR_Frontend_Web_config'];

$ui = &PEAR_Command::getFrontendObject();
$ui->setConfig($config);

PEAR::setErrorHandling(PEAR_ERROR_CALLBACK, array($ui, "displayFatalError"));

// Cient requests an Image/Stylesheet/Javascript
// outputFrontendFile() does exit()
if (isset($_GET["css"])) {
    $ui->outputFrontendFile($_GET["css"], 'css');
}
if (isset($_GET["js"])) {
    $ui->outputFrontendFile($_GET["js"], 'js');
}
if (isset($_GET["img"])) {
    $ui->outputFrontendFile($_GET["img"], 'image');
}

$verbose = $config->get("verbose");
$cmdopts = array();
$opts    = array();
$params  = array();

if (!file_exists($pear_user_config)) {
    // I think PEAR_Frontend_Web is running for the first time!
    // Install it properly ...
    print('<h3>Preparing PEAR_Frontend_Web for its first time use...</h3>');

    print('Saving config file...');
    // First of all set some config-vars:
    $cmd = PEAR_Command::factory('config-set', $config);
    $ok = $cmd->run('config-set', array(), array('php_dir',  $dir.'PEAR'));
    $ok = $cmd->run('config-set', array(), array('doc_dir',  $dir.'docs'));
    $ok = $cmd->run('config-set', array(), array('ext_dir',  $dir.'ext'));
    $ok = $cmd->run('config-set', array(), array('bin_dir',  $dir.'bin'));
    $ok = $cmd->run('config-set', array(), array('data_dir', $dir.'data'));
    $ok = $cmd->run('config-set', array(), array('test_dir', $dir.'test'));
    $ok = $cmd->run('config-set', array(), array('temp_dir', $dir.'temp'));
    $ok = $cmd->run('config-set', array(), array('download_dir', $dir.'temp/download'));
    $ok = $cmd->run('config-set', array(), array('cache_dir', $dir.'PEAR/cache'));
    $ok = $cmd->run('config-set', array(), array('cache_ttl', 300));
    $ok = $cmd->run('config-set', array(), array('default_channel', 'pear.php.net'));
    $ok = $cmd->run('config-set', array(), array('preferred_mirror', 'pear.php.net'));

    print('Checking package registry...');
    // Register packages
    $packages = array(
                                'Archive_Tar',
                                'Console_Getopt',
                                'HTML_Template_IT',
                                'PEAR',
                                'PEAR_Frontend_Web',
                                'Structures_Graph'
                        );
    $reg = &$config->getRegistry();
    if (!file_exists($dir.'PEAR/.registry')) {
        PEAR::raiseError('Directory "'.$dir.'PEAR/.registry" does not exist. please check your installation');
    }

    foreach($packages as $pkg) {
        $info = $reg->packageInfo($pkg);
        foreach($info['filelist'] as $fileName => $fileInfo) {
            if($fileInfo['role'] == "php") {
                $info['filelist'][$fileName]['installed_as'] =
                    str_replace('{dir}',$dir, $fileInfo['installed_as']);
            }
        }
        $reg->updatePackage($pkg, $info, false);
    }

    print('<p><em>PEAR_Frontend_Web configured succesfully !</em></p>');
    $msg = sprintf('<p><a href="%s">Click here to continue</a></p>',
                    $_SERVER['PHP_SELF']);
    die($msg);
}

$cache_dir = $config->get('cache_dir');
if (!is_dir($cache_dir)) {
    include_once 'System.php';
    if (!System::mkDir('-p', $cache_dir)) {
        PEAR::raiseError('Directory "'.$cache_dir.'" does not exist and cannot be created. Please check your installation');
    }
}

if (isset($_GET['command']) && !is_null($_GET['command'])) {
    $command = $_GET['command'];
} else {
    $command = 'list';
}

// Prepare and begin output
$ui->outputBegin($command);

// Handle some different Commands
    switch ($command) {
        case 'install':
        case 'uninstall':
        case 'upgrade':
            if ($_GET['command'] == 'install') {
                // also install dependencies
                $opts['onlyreqdeps'] = true;
                if (isset($_GET['force']) && $_GET['force'] == 'on') {
                    $opts['force'] = true;
                }
            }

            if (strpos($_GET['pkg'], '\\\\') !== false) {
                $_GET['pkg'] = stripslashes($_GET['pkg']);
            }
            $params = array($_GET["pkg"]);
            $cmd = PEAR_Command::factory($command, $config);
            $ok = $cmd->run($command, $opts, $params);

            $ui->finishOutput('Back', array('link' => $URL.'?command=info&pkg='.$_GET['pkg'],
                'text' => 'View package information'));
            break;
        case 'run-scripts' :
            $params = array($_GET["pkg"]);
            $cmd = PEAR_Command::factory($command, $config);
            $ok = $cmd->run($command, $opts, $params);
            break;
        case 'info':
        case 'remote-info':
            $reg = &$config->getRegistry();
            // we decide what it is:
            $pkg = $reg->parsePackageName($_GET['pkg']);
            if ($reg->packageExists($pkg['package'], $pkg['channel'])) {
                $command = 'info';
            } else {
                $command = 'remote-info';
            }

            $params = array(strtolower($_GET['pkg']));
            $cmd = PEAR_Command::factory($command, $config);
            $ok = $cmd->run($command, $opts, $params);

            break;
        case 'search':
            if (!isset($_POST['search']) || $_POST['search'] == '') {
                // unsubmited, show forms
                $ui->outputSearch();
            } else {
                if ($_POST['channel'] == 'all') {
                    $opts['allchannels'] = true;
                } else {
                    $opts['channel'] = $_POST['channel'];
                }

                // submited, do search
                switch ($_POST['search']) {
                    case 'name':
                        $params = array($_POST['input']);
                        break;
                    case 'description':
                        $params = array($_POST['input'], $_POST['input']);
                        break;
                    default:
                        PEAR::raiseError('Can\'t search for '.$_POST['search']);
                        break;
                }

                // Forward compatible (bug #10495)
                require_once('Frontend/Web_Command_Forward_Compatible.php');
                $cmd = new Web_Command_Forward_Compatible($ui, $config);
                $cmd->doSearch($command, $opts, $params);
            }

            break;
        case 'config-show':
            $cmd = PEAR_Command::factory($command, $config);
            $res = $cmd->run($command, $opts, $params);

            // if this code is reached, the config vars are submitted
            $set = PEAR_Command::factory('config-set', $config);
            foreach($GLOBALS['_PEAR_Frontend_Web_Config'] as $var => $value) {
                if ($var == 'Filename') {
                    continue; // I hate obscure bugs
                }
                if ($value != $config->get($var)) {
                    print('Saving '.$var.'... ');
                    $res = $set->run('config-set', $opts, array($var, $value));
                    $config->set($var, $value);
                }
            }
            print('<p><b>Config saved succesfully!</b></p>');

            $URL .= '?command='.$command;
            $ui->finishOutput('Back', array('link' => $URL,
                'text' => 'Back to the config'));
            break;
        case 'list-all':
            // XXX Not used anymore, 'list-categories' is used instead
            //if (isset($_GET["mode"]))
            //    $opts['mode'] = $_GET["mode"];
            // Forward compatible (bug #10495)
            //require_once('Frontend/Web_Command_Forward_Compatible.php');
            //$cmd = new Web_Command_Forward_Compatible($ui, $config);
            //$cmd->doListAll($command, $opts, $params);
            //
            //break;
            $command = 'list-categories';
        case 'list-categories':
        case 'list-packages':
            if (isset($_GET['chan']) && $_GET['chan'] != '') {
                $opts['channel'] = $_GET['chan'];
            } else {
                // show 'table of contents' before all channel output
                $ui->outputTableOfChannels();

                $opts['allchannels'] = true;
            }
            if (isset($_GET['opt']) && $_GET['opt'] == 'packages') {
                $opts['packages'] = true;
            }
            // Forward compatible (bug unsubmitted)
            require_once('Frontend/Web_Command_Forward_Compatible.php');
            $cmd = new Web_Command_Forward_Compatible($ui, $config);
            if ($command == 'list-categories') {
                $cmd->doListCategories($command, $opts, $params);
            } else {
                $cmd->doListPackages($command, $opts, $params);
            }
            break;
        case 'list-category':
            $params = array($_GET['chan'], $_GET['cat']);
            // Forward compatible (bug unsubmitted)
            require_once('Frontend/Web_Command_Forward_Compatible.php');
            $cmd = new Web_Command_Forward_Compatible($ui, $config);
            $cmd->doListCategory($command, $opts, $params);
            break;
        case 'list':
            $opts['allchannels'] = true;
            // Forward compatible (bug #10496)
            require_once('Frontend/Web_Command_Forward_Compatible.php');
            $cmd = new Web_Command_Forward_Compatible($ui, $config);
            $cmd->doList($command, $opts, $params);
            break;
        case 'list-upgrades':
            // Forward compatible (bug #10515)
            require_once('Frontend/Web_Command_Forward_Compatible.php');
            $cmd = new Web_Command_Forward_Compatible($ui, $config);
            $cmd->doListUpgrades($command, $opts, $params);

            $ui->outputUpgradeAll();
            break;
        case 'upgrade-all':
            $cmd = PEAR_Command::factory($command, $config);
            $ok = $cmd->run($command, $opts, $params);

            $ui->finishOutput('Back', array('link' => $URL.'?command=list',
                'text' => 'Click here to go back'));
            break;
        case 'channel-info':
            if (isset($_GET["chan"]))
                $params[] = $_GET["chan"];
            $cmd = PEAR_Command::factory($command, $config);
            $ok = $cmd->run($command, $opts, $params);

            $ui->finishOutput('Delete Channel', array('link' =>
                $_SERVER['PHP_SELF'] . '?command=list-channels',
                'text' => 'Click here to list all channels'));
            break;
        case 'channel-discover':
            if (isset($_GET["chan"]))
                $params[] = $_GET["chan"];
            $cmd = PEAR_Command::factory($command, $config);
            $ui->startSession();
            $ok = $cmd->run($command, $opts, $params);

            $ui->finishOutput('Channel Discovery', array('link' =>
                $_SERVER['PHP_SELF'] . '?command=channel-info&chan=' . urlencode($_GET['chan']),
                'text' => 'Click Here for ' . htmlspecialchars($_GET['chan']) . ' Information'));
            break;
        case 'channel-delete':
            if (isset($_GET["chan"]))
                $params[] = $_GET["chan"];
            $cmd = PEAR_Command::factory($command, $config);
            $ok = $cmd->run($command, $opts, $params);

            $ui->finishOutput('Delete Channel', array('link' =>
                $_SERVER['PHP_SELF'] . '?command=list-channels',
                'text' => 'Click here to list all channels'));
            break;
        case 'list-channels':
            $cmd = PEAR_Command::factory($command, $config);
            $ok = $cmd->run($command, $opts, $params);

            break;
        case 'update-channels':
            // update every channel manually,
            // fixes bug PEAR/#10275 (XML_RPC dependency)
            // will be fixed in next pear release
            $reg = &$config->getRegistry();
            $channels = $reg->getChannels();
            $command = 'channel-update';
            $cmd = PEAR_Command::factory($command, $config);
            
            $success = true;
            $ui->startSession();
            foreach ($channels as $channel) {
                if ($channel->getName() != '__uri') {
                    $success &= $cmd->run($command, $opts,
                                          array($channel->getName()));
                }
            }

            $ui->finishOutput('Update Channel List', array('link' =>
                $_SERVER['PHP_SELF'] . '?command=list-channels',
                'text' => 'Click here to list all channels'));
            break;
        default:
            $cmd = PEAR_Command::factory($command, $config);
            $res = $cmd->run($command, $opts, $params);

            $URL .= '?command='.$_GET["command"];
            header("Location: ".$URL);
            break;
    }

$ui->outputEnd($command);

?>
