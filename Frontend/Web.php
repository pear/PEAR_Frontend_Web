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

require_once "PEAR.php";
require_once "HTML/IT.php";

class PEAR_Frontend_Web extends PEAR
{
    // {{{ properties

    /**
     * What type of user interface this frontend is for.
     * @var string
     * @access public
     */
    var $type = 'Web';
    var $lp = ''; // line prefix
    var $tpl;
    var $table;
    var $data = array();
    var $_log;
    
    var $params = array();

    // }}}

    // {{{ constructor

    function PEAR_Frontend_Web()
    {
        parent::PEAR();
    }

    // }}}

    // {{{ displayLine(text)

    function displayLine($text)
    {
//        print "$this->lp$text<br>\n";
    }

    function display($text)
    {
        print $text;
    }

    // }}}
    // {{{ displayError(eobj)
    function initTemplate($file, $title = '', $icon = '')
    {
        $tpl = new IntegratedTemplate(dirname(__FILE__)."/Web");
        $tpl->loadTemplateFile($file);
        $tpl->setVariable("InstallerURL", $_SERVER["PHP_SELF"]);
        $tpl->setVariable("ImgPEAR", $_SERVER["PHP_SELF"].'?img=pear');
        if ($title)
            $tpl->setVariable("Title", $title);
        if ($icon)
        {
            $tpl->setCurrentBlock("TitleBlock");
            $tpl->setVariable("_Title", $title);
            $tpl->setVariable("_Icon", $icon);
        };
        return $tpl;
    }
    
    function displayError($eobj)
    {
        $msg = '';
        if (trim($GLOBALS['_PEAR_Frontend_Web_log']))
            $msg = trim($GLOBALS['_PEAR_Frontend_Web_log'])."\n\n";
        $msg .= trim($eobj->getMessage());
        $msg = nl2br($msg."\n");

        $tpl = $this->initTemplate("error.tpl.html", "Error", 'error');
        
        $tpl->setVariable("Error", $msg);
        $command_map = array(
            "install" => "list-all",
            "uninstall" => "list-all",
            "upgrade" => "list-all",
            );
        if (isset($_GET['command'])) {
            if (isset($command_map[$_GET['command']]))
                $_GET['command'] = $command_map[$_GET['command']];
            $tpl->setVariable("param", '?command='.$_GET['command']);
        };
        
        $tpl->show();
        exit;
        return true;
    }

    // }}}
    // {{{ displayFatalError(eobj)

    function displayFatalError($eobj)
    {
        $this->displayError($eobj);
        exit(1);
    }

    // }}}
    // {{{ userConfirm(prompt, [default])

    function userConfirm($prompt, $default = 'yes')
    {
        static $positives = array('y', 'yes', 'on', '1');
        static $negatives = array('n', 'no', 'off', '0');
        print "$this->lp$prompt [$default] : ";
        $fp = fopen("php://stdin", "r");
        $line = fgets($fp, 2048);
        fclose($fp);
        $answer = strtolower(trim($line));
        if (empty($answer)) {
            $answer = $default;
        }
        if (in_array($answer, $positives)) {
            return true;
        }
        if (in_array($answer, $negatives)) {
            return false;
        }
        if (in_array($default, $positives)) {
            return true;
        }
        return false;
    }

    // }}}
    
    function _outputListAll($data)
    {
        $tpl = $this->initTemplate("package.list.tpl.html", 'Install / Upgrade / Remove PEAR Packages', 'pkglist');
        
        foreach($data['data'] as $category => $packages)
        {
            foreach($packages as $row)
            {
                $tpl->setCurrentBlock("Row");
                $tpl->setVariable("ImgPackage", $_SERVER["PHP_SELF"].'?img=package');
                $compare = version_compare($row[1], $row[2]);
                if (!$row[2]) {
                    $inst = sprintf(
                        '<a href="%s?command=install&pkg=%s"><img src="%s?img=install" border="0" alt="install"></a>',
                        $_SERVER["PHP_SELF"], $row[0], $_SERVER["PHP_SELF"]);
                    $del = '';
                } else if ($compare == 1) {
                    $inst = sprintf(
                        '<a href="%s?command=upgrade&pkg=%s"><img src="%s?img=install" border="0" alt="upgrade"></a>',
                        $_SERVER["PHP_SELF"], $row[0], $_SERVER["PHP_SELF"]);
                    $del = sprintf(
                        '<a href="%s?command=uninstall&pkg=%s"><img src="%s?img=uninstall" border="0" alt="uninstall"></a>',
                        $_SERVER["PHP_SELF"], $row[0], $_SERVER["PHP_SELF"]);
                } else {
                    $del = sprintf(
                        '<a href="%s?command=uninstall&pkg=%s"><img src="%s?img=uninstall" border="0" alt="uninstall"></a>',
                        $_SERVER["PHP_SELF"], $row[0], $_SERVER["PHP_SELF"]);
                    $inst = '';
                };
                $info=sprintf('<a href="%s?info=%s#%s"><img src="%s?img=info" border="0" alt="info"></a>',
                    $_SERVER["PHP_SELF"], $row[0], $row[0], $_SERVER["PHP_SELF"]);
                $infoExt=sprintf('<a href="%s?pacid=%s"><img src="%s?img=infoplus" border="0" alt="extended info"></a>',
                    'http://pear.php.net/package-info.php', $row[4], $_SERVER["PHP_SELF"]);
                        
                if ($row[0] == 'PEAR' || $row[0] == 'Archive_Tar')
                    $del = '';
                        
                $tpl->setVariable("Version", $row[1]);
                $tpl->setVariable("Installed", $row[2]);
                $tpl->setVariable("Install", $inst);
                $tpl->setVariable("Delete", $del);
                $tpl->setVariable("Info", $info);
                $tpl->setVariable("InfoExt", $infoExt);
                $tpl->setVariable("Package", $row[0]);
                $tpl->setVariable("Summary", nl2br($row[3]));
                $tpl->parseCurrentBlock();
            };
            $tpl->setCurrentBlock("Category");
            $tpl->setVariable("categoryName", $category);
            $tpl->setVariable("ImgCategory", $_SERVER["PHP_SELF"].'?img=category');
            $tpl->parseCurrentBlock();
        };
        $tpl->show();
    
    }
    
    function outputData($data, $command)
    {
        switch ($command)
        {
        case 'config-show':
            $prompt  = array();
            $default = array();
            foreach($data['data'] as $row) {
                $prompt[]  = $row[0];
                if ($row[1] != "<not set>")
                    $default[] = $row[1];
                else
                    $default[] = '';
            };
            $GLOBALS['_PEAR_Frontend_Web_Config'] = 
                $this->userDialog($command, $prompt, array(), $default, 'Configuration', 'config');
            return true;
        case 'list-all':
            return $this->_outputListAll($data);
        case 'install':
        case 'uninstall':
            return true;
        case 'login':
            if ($_SERVER["REQUEST_METHOD"] != "POST")
                $this->data[$command] = $data;
            return true;
        case 'logout':
            PEAR::raiseError($data);
            break;
        case 'package':
            echo $data;
            break;
        default:
            echo $data;
        };
        
        return true;
    }

    function userDialog($command, $prompts, $types = array(), $defaults = array(), $title = '', $icon = '')
    {
        if (isset($_GET["command"]) && $_GET["command"]==$command
            && $_SERVER["REQUEST_METHOD"] == "POST")
        {
            $result = array();
            foreach($prompts as $prompt)
                $result[$prompt] = $_POST[$prompt];
            return $result;
        };
    
        $tpl = $this->initTemplate("userDialog.tpl.html", $title, $icon);
        $tpl->setVariable("Command", $command);
        $tpl->setVariable("Headline", nl2br($this->data[$command]));
    
        if (is_array($prompts))
        {
            foreach($prompts as $key => $prompt)
            {
                $tpl->setCurrentBlock("InputField");
                $type = (isset($types[$key]) ? $types[$key] : 'text');
                $tpl->setVariable("prompt", $prompt);
                $tpl->setVariable("default", $defaults[$key]);
                $tpl->setVariable("type", $type);
                $tpl->parseCurrentBlock();
            };
        };
    
        $tpl->show();
        exit;
    }
    
    function log($text)
    {
        $GLOBALS['_PEAR_Frontend_Web_log'] .= $text."\n";
    }

    // }}}
}

?>
