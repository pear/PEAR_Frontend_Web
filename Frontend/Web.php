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

  $id$
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

    function displayError($eobj)
    {
        return $this->displayLine($eobj->getMessage());
    }

    // }}}
    // {{{ displayFatalError(eobj)

    function displayFatalError($eobj)
    {
        $this->displayError($eobj);
        exit(1);
    }

    // }}}
    // {{{ displayHeading(title)

    function displayHeading($title)
    {
//        print $this->lp.$this->bold($title)."\n";
//        print $this->lp.str_repeat("=", strlen($title))."\n";
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
    
    function outputListAll($data)
    {
        $tpl = new IntegratedTemplate(dirname(__FILE__)."/Web");
        $tpl->loadTemplateFile("package.list.tpl.html");
        $tpl->setVariable("InstallerURL", $_SERVER["PHP_SELF"]);
        $tpl->setVariable("ImgPEAR", $_SERVER["PHP_SELF"].'?img=pear');
        
        foreach($data['data'] as $category => $packages)
        {
            foreach($packages as $row)
            {
                $tpl->setCurrentBlock("Row");
                $tpl->setVariable("ImgPackage", $_SERVER["PHP_SELF"].'?img=package');
                $compare = version_compare($row[1], $row[4]);
                if (!$row[4]) {
                    $inst = sprintf('<a href="%s?install=%s"><img src="%s?img=install" border="0"></a>',
                        $_SERVER["PHP_SELF"], $row[0], $_SERVER["PHP_SELF"]);
                    $del = '';
                } else if ($compare == 1) {
                    $inst = sprintf('<a href="%s?upgrade=%s"><img src="%s?img=install" border="0"></a>',
                        $_SERVER["PHP_SELF"], $row[0], $_SERVER["PHP_SELF"]);
                    $del = sprintf('<a href="%s?uninstall=%s"><img src="%s?img=uninstall" border="0"></a>',
                        $_SERVER["PHP_SELF"], $row[0], $_SERVER["PHP_SELF"]);
                } else {
                    $del = sprintf('<a href="%s?uninstall=%s"><img src="%s?img=uninstall" border="0"></a>',
                        $_SERVER["PHP_SELF"], $row[0], $_SERVER["PHP_SELF"]);
                    $inst = '';
                };
                $info=sprintf('<a href="%s?info=%s#%s"><img src="%s?img=info" border="0"></a>',
                    $_SERVER["PHP_SELF"], $row[0], $row[0], $_SERVER["PHP_SELF"]);
                        
                $tpl->setVariable("Version", $row[1]);
                $tpl->setVariable("Installed", $row[4]);
                $tpl->setVariable("Install", $inst);
                $tpl->setVariable("Delete", $del);
                $tpl->setVariable("Info", $info);
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
        case 'list-all':
            return $this->outputListAll($data);
        };
    }

    function userDialog($command, $prompts, $types = array(), $defaults = array())
    {
        if (isset($_GET["command"]) && $_GET["command"]==$command
            && $_SERVER["REQUEST_METHOD"] == "POST")
        {
            $result = array();
            foreach($prompts as $prompt)
                $result[] = $_POST[$prompt];
            return $result;
        };
    
        $tpl = new IntegratedTemplate(dirname(__FILE__)."/Web");
        $tpl->loadTemplateFile("userDialog.tpl.html");
        $tpl->setVariable("ImgPEAR", $_SERVER["PHP_SELF"].'?img=pear');
        $tpl->setVariable("InstallerURL", $_SERVER["PHP_SELF"]);
        $tpl->setVariable("Command", $command);
    
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

    // }}}
}

?>
