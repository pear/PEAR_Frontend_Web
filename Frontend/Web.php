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
require_once "Net/UserAgent/Detect.php";

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
    function initTemplate($file, $title = '', $icon = '', $useDHTML = true)
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
            $tpl->setVariable("_InstallerURL", $_SERVER["PHP_SELF"]);
            $tpl->setVariable("_Title", $title);
            $tpl->setVariable("_Icon", $icon);
            $tpl->parseCurrentBlock();
        };
        $tpl->setCurrentBlock();
        
        if ($useDHTML && Net_UserAgent_Detect::getBrowser('ie5up') == 'ie5up')
            $dhtml = true;
        else
            $dhtml = false;
        
        if ($dhtml)
        {
            $tpl->setVariable("JS", 'dhtml');
            $css = '<link rel="stylesheet" href="'.$_SERVER['PHP_SELF'].'?css=dhtml" />';
            $tpl->setVariable("DHTMLcss", $css);
        } else {
            $tpl->setVariable("JS", 'nodhtml');
        };
        
        return $tpl;
    }
    
    function displayError($eobj, $title = 'Error', $img = 'error')
    {
        $msg = '';
        if (trim($GLOBALS['_PEAR_Frontend_Web_log']))
            $msg = trim($GLOBALS['_PEAR_Frontend_Web_log'])."\n\n";
            
        if (PEAR::isError($eobj))
            $msg .= trim($eobj->getMessage());
        else
            $msg .= trim($eobj);
            
        $msg = nl2br($msg."\n");

        $tpl = $this->initTemplate("error.tpl.html", $title, $img);
        
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

    function displayFatalError($eobj, $title = 'Error', $img = 'error')
    {
        $this->displayError($eobj, $title, $img);
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
    
    function _outputListAll($data, $title = 'Install / Upgrade / Remove PEAR Packages', $img = 'pkglist', $useDHTML = true)
    {
        $tpl = $this->initTemplate("package.list.tpl.html", $title, $img, $useDHTML);
        
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
                $info=sprintf('<a href="%s?command=remote-info&pkg=%s"><img src="%s?img=info" border="0" alt="info"></a>',
                    $_SERVER["PHP_SELF"], $row[0], $_SERVER["PHP_SELF"]);
                $infoExt=sprintf('<a href="%s?package=%s"><img src="%s?img=infoplus" border="0" alt="extended info"></a>',
                    'http://pear.php.net/package-info.php', $row[0], $_SERVER["PHP_SELF"]);
                        
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
    
    function _outputPackageInfo($data)
    {
        $tpl = $this->initTemplate("package.info.tpl.html", 'Package Management :: '.$data['name'], 'pkglist');
        
        $tpl->setVariable("Latest", $data['stable']);
        $tpl->setVariable("Installed", $data['installed']);
        $tpl->setVariable("Package", $data['name']);
        $tpl->setVariable("Licence", $data['licence']);
        $tpl->setVariable("Category", $data['category']);
        $tpl->setVariable("Summary", nl2br($data['summary']));
        $tpl->setVariable("Description", nl2br($data['description']));

        $compare = version_compare($data['stable'], $data['installed']);
        if (!$data['installed']) {
            $opt_img_1 = sprintf(
                '<a href="%s?command=install&pkg=%s"><img src="%s?img=install" border="0" alt="install"></a>',
                $_SERVER["PHP_SELF"], $data['name'], $_SERVER["PHP_SELF"]);
            $opt_text_1 = sprintf(
                '<a href="%s?command=install&pkg=%s" class="green">Install</a>',
                $_SERVER["PHP_SELF"], $data['name'], $_SERVER["PHP_SELF"]);
            $opt_img_2 = '';
        } else if ($compare == 1) {
            $opt_img_1 = sprintf(
                '<a href="%s?command=upgrade&pkg=%s"><img src="%s?img=install" border="0" alt="upgrade"></a><br>',
                $_SERVER["PHP_SELF"], $data['name'], $_SERVER["PHP_SELF"]);
            $opt_text_1 = sprintf(
                '<a href="%s?command=upgrade&pkg=%s" class="green">Upgrade</a>',
                $_SERVER["PHP_SELF"], $data['name'], $_SERVER["PHP_SELF"]);
            $opt_img_2 = sprintf(
                '<a href="%s?command=uninstall&pkg=%s"><img src="%s?img=uninstall" border="0" alt="uninstall"></a>',
                $_SERVER["PHP_SELF"], $data['name'], $_SERVER["PHP_SELF"]);
            $opt_text_2 = sprintf(
                '<a href="%s?command=uninstall&pkg=%s" class="green">Delete</a>',
                $_SERVER["PHP_SELF"], $data['name'], $_SERVER["PHP_SELF"]);
        } else {
            $opt_img_1 = sprintf(
                '<a href="%s?command=uninstall&pkg=%s"><img src="%s?img=uninstall" border="0" alt="uninstall"></a>',
                $_SERVER["PHP_SELF"], $data['name'], $_SERVER["PHP_SELF"]);
            $opt_text_1 = sprintf(
                '<a href="%s?command=uninstall&pkg=%s" class="green">Delete</a>',
                $_SERVER["PHP_SELF"], $data['name'], $_SERVER["PHP_SELF"]);
            $opt_img_2 = '';
        };

        if ($opt_img_1)
        {
            $tpl->setVariable("Opt_Img_1", $opt_img_1);
            $tpl->setVariable("Opt_Text_1", $opt_text_1);
        };
        if ($opt_img_2)
        {
            $tpl->setVariable("Opt_Img_2", $opt_img_2);
            $tpl->setVariable("Opt_Text_2", $opt_text_2);
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
            foreach($data['data'] as $group) {
                foreach($group as $row) {
                    $prompt[$row[1]]  = $row[0];
                    $default[$row[1]] = $row[2];
                };
            };
            $title = 'Configuration :: '.$GLOBALS['pear_user_config'];
            $GLOBALS['_PEAR_Frontend_Web_Config'] = 
                $this->userDialog($command, $prompt, array(), $default, $title, 'config');
            return true;
        case 'list-all':
            return $this->_outputListAll($data);
        case 'search':
            return $this->_outputListAll($data, 'Package Search :: Result', 'pkgsearch', false);
        case 'remote-info':
            return $this->_outputPackageInfo($data);
        case 'install':
        case 'uninstall':
            return true;
        case 'login':
            if ($_SERVER["REQUEST_METHOD"] != "POST")
                $this->data[$command] = $data;
            return true;
        case 'logout':
            $this->displayError($data, 'Logout', 'logout');
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
            foreach($prompts as $key => $prompt)
                $result[$key] = $_POST[$key];
            return $result;
        };
    
        $tpl = $this->initTemplate("userDialog.tpl.html", $title, $icon);
        $tpl->setVariable("Command", $command);
        $tpl->setVariable("Headline", nl2br($this->data[$command]));
    
        if (is_array($prompts))
        {
            $maxlen = 0;
            foreach($prompts as $key => $prompt)
            {
                if (strlen($prompt) > $maxlen) {
                    $maxlen = strlen($prompt);
                };
            };
            
            foreach($prompts as $key => $prompt)
            {
                $tpl->setCurrentBlock("InputField");
                $type = (isset($types[$key]) ? $types[$key] : 'text');
                $tpl->setVariable("prompt", $prompt);
                $tpl->setVariable("name", $key);
                $tpl->setVariable("default", $defaults[$key]);
                $tpl->setVariable("type", $type);
                if ($maxlen > 25)
                    $tpl->setVariable("width", 'width="275"');
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
