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
require_once "Pager/Pager.php";

/**
* PEAR_Frontend_Web is a HTML based Webfrontend for the PEAR Installer
*
* The Webfrontend provides basic functionality of the Installer, such as
* a package list grouped by categories, a search mask, the possibility
* to install/upgrade/uninstall packages and some minor things.
* PEAR_Frontend_Web makes use of the PEAR::HTML_IT Template engine which
* provides the possibillity to skin the Installer.
*
* @author  Christian Dickmann <dickmann@php.net>
* @package PEAR_Frontend_Web
* @access  private
*/

class PEAR_Frontend_Web extends PEAR
{
    // {{{ properties

    /**
     * What type of user interface this frontend is for.
     * @var string
     * @access public
     */
    var $type = 'Web';

    // }}}

    // {{{ constructor

    function PEAR_Frontend_Web()
    {
        parent::PEAR();
    }

    // }}}

    // XXX some methods from CLI following. should be deleted in the near future
    // {{{ displayLine(text)

    function displayLine($text)
    {
        trigger_error("Frontend::display deprecated", E_USER_ERROR);
    }

    function display($text)
    {
        trigger_error("Frontend::display deprecated", E_USER_ERROR);
    }

    // }}}

    // {{{ userConfirm(prompt, [default])

    function userConfirm($prompt, $default = 'yes')
    {
        trigger_error("Frontend::display deprecated", E_USER_ERROR);
        return false;
    }

    // }}}
    
    /**
     * Initialize a TemplateObject, add a title, and icon and add JS and CSS for DHTML 
     *
     * @param string  $file     filename of the template file
     * @param string  $title    (optional) title of the page
     * @param string  $icon     (optional) iconhandle for this page
     * @param boolean $useDHTML (optional) add JS and CSS for DHTML-features
     *
     * @access private
     *
     * @return object Object of HTML/IT - Template - Class
     */
    
    function _initTemplate($file, $title = '', $icon = '', $useDHTML = true)
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
    
    // {{{ displayError(eobj)
    
    /**
     * Display an error page
     * 
     * @param mixed   $eobj  PEAR_Error object or string containing the error message
     * @param string  $title (optional) title of the page
     * @param string  $img   (optional) iconhandle for this page
     *
     * @access public
     * 
     * @return null does not return anything, but exit the script
     */
     
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

        $tpl = $this->_initTemplate("error.tpl.html", $title, $img);
        
        $tpl->setVariable("Error", $msg);
        $command_map = array(
            "install"   => "list-all",
            "uninstall" => "list-all",
            "upgrade"   => "list-all",
            );
        if (isset($_GET['command'])) {
            if (isset($command_map[$_GET['command']]))
                $_GET['command'] = $command_map[$_GET['command']];
            $tpl->setVariable("param", '?command='.$_GET['command']);
        };
        
        $tpl->show();
        exit;
    }

    // }}}
    // {{{ displayFatalError(eobj)

    /**
     * Alias for PEAR_Frontend_Web::displayError()
     * 
     * @see PEAR_Frontend_Web::displayError()
     */

    function displayFatalError($eobj, $title = 'Error', $img = 'error')
    {
        $this->displayError($eobj, $title, $img);
    }

    // }}}
    
    /**
     * Output a list of packages, grouped by categories. Uses Paging
     * 
     * @param array   $data     array containing all data to display the list
     * @param string  $title    (optional) title of the page
     * @param string  $img      (optional) iconhandle for this page
     * @param boolean $useDHTML (optional) add JS and CSS for DHTML-features
     * @param boolean $paging   (optional) use Paging or not 
     *
     * @access private
     * 
     * @return boolean true (yep. i am an optimist)
     */

    function _outputListAll($data, $title = 'Install / Upgrade / Remove PEAR Packages', $img = 'pkglist', $useDHTML = false, $paging = true)
    {
        $tpl = $this->_initTemplate("package.list.tpl.html", $title, $img, $useDHTML);
        
        // Use PEAR::Pager to page Packages
        $pager =& new Pager(array(
            'itemData'  => $data['data'],
            'perPage'   => ($paging ? 5 : count($data['data'])),
            'linkClass' => 'green',
            ));
        $data['data'] = $pager->getPageData();
        $links = $pager->getLinks();
        list($from, $to) = $pager->getOffsetByPageId();
        $links['current'] = '&pageID='.$pager->getCurrentPage();
        
        $tpl->setVariable('Prev', $links['back']);
        $tpl->setVariable('Next', $links['next']);
        $tpl->setVariable('PagerFrom', $from);
        $tpl->setVariable('PagerTo', $to);
        $tpl->setVariable('PagerCount', $pager->numItems());

        foreach($data['data'] as $category => $packages)
        {
            foreach($packages as $row)
            {
                $tpl->setCurrentBlock("Row");
                $tpl->setVariable("ImgPackage", $_SERVER["PHP_SELF"].'?img=package');
                $compare = version_compare($row[1], $row[2]);
                if (!$row[2]) {
                    $inst = sprintf(
                        '<a href="%s?command=install&pkg=%s%s"><img src="%s?img=install" border="0" alt="install"></a>',
                        $_SERVER["PHP_SELF"], $row[0], $links['current'], $_SERVER["PHP_SELF"]);
                    $del = '';
                } else if ($compare == 1) {
                    $inst = sprintf(
                        '<a href="%s?command=upgrade&pkg=%s%s"><img src="%s?img=install" border="0" alt="upgrade"></a>',
                        $_SERVER["PHP_SELF"], $row[0], $links['current'], $_SERVER["PHP_SELF"]);
                    $del = sprintf(
                        '<a href="%s?command=uninstall&pkg=%s%s"><img src="%s?img=uninstall" border="0" alt="uninstall"></a>',
                        $_SERVER["PHP_SELF"], $row[0], $links['current'], $_SERVER["PHP_SELF"]);
                } else {
                    $del = sprintf(
                        '<a href="%s?command=uninstall&pkg=%s%s"><img src="%s?img=uninstall" border="0" alt="uninstall"></a>',
                        $_SERVER["PHP_SELF"], $row[0], $links['current'], $_SERVER["PHP_SELF"]);
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
        
        return true;
    }
    
    /**
     * Output details of one package
     * 
     * @param array $data array containing all information about the package
     *
     * @access private
     * 
     * @return boolean true (yep. i am an optimist)
     */

    function _outputPackageInfo($data)
    {
        $tpl = $this->_initTemplate("package.info.tpl.html", 'Package Management :: '.$data['name'], 'pkglist');
        
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
    
    /**
     * Output all kinds of data depending on the command which called this method
     * 
     * @param mixed  $data    datastructure containing the information to display
     * @param string $command command from which this method was called
     *
     * @access public
     * 
     * @return mixed highly depends on the command
     */
     
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
            return $this->_outputListAll($data, 'Package Search :: Result', 'pkgsearch', false, false);
        case 'remote-info':
            return $this->_outputPackageInfo($data);
        case 'install':
        case 'upgrade':
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

    /**
     * Display a formular and return the given input (yes. needs to requests)
     * 
     * @param string $command  command from which this method was called
     * @param array  $prompts  associative array. keys are the inputfieldnames 
     *                         and values are the description
     * @param array  $types    (optional) array of inputfieldtypes (text, password, 
     *                         etc.) keys have to be the same like in $prompts
     * @param array  $defaults (optional) array of defaultvalues. again keys have 
     *                         to be the same like in $prompts
     * @param string $title    (optional) title of the page
     * @param string $icon     (optional) iconhandle for this page
     *
     * @access public
     * 
     * @return array input sended by the user
     */
     
    function userDialog($command, $prompts, $types = array(), $defaults = array(), $title = '', $icon = '')
    {
        // If this is an POST Request, we can return the userinput
        if (isset($_GET["command"]) && $_GET["command"]==$command
            && $_SERVER["REQUEST_METHOD"] == "POST")
        {
            $result = array();
            foreach($prompts as $key => $prompt)
                $result[$key] = $_POST[$key];
            return $result;
        };
        
        // Assign title and icon to some commands
        switch ($command)
        {
        case 'login':
            $title = 'Login';
            $icon = 'login';
            break;
        };
    
        $tpl = $this->_initTemplate("userDialog.tpl.html", $title, $icon);
        $tpl->setVariable("Command", $command);
        $tpl->setVariable("Headline", nl2br($this->data[$command]));
    
        if (is_array($prompts))
        {
            $maxlen = 0;
            foreach($prompts as $key => $prompt) {
                if (strlen($prompt) > $maxlen) {
                    $maxlen = strlen($prompt);
                };
            };
            
            foreach($prompts as $key => $prompt) {
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
    
    /**
     * Write message to log
     * 
     * @param string $text message which has to written to log
     *
     * @access public
     * 
     * @return boolean true
     */
     
    function log($text)
    {
        $GLOBALS['_PEAR_Frontend_Web_log'] .= $text."\n";
        return true;
    }

    /**
     * Sends the required file along with Headers and exits the script
     * 
     * @param string $handle handle of the requested file
     * @param string $group  group of the requested file
     *
     * @access public
     * 
     * @return null nothing, because script exits
     */
     
    function outputFrontendFile($handle, $group)
    {
        $handles = array(
            "css" => array(
                "style" => "style.css",
                "dhtml" => "dhtml.css",
                ),
            "js" => array(    
                "dhtml" => "dhtml.js",
                "nodhtml" => "nodhtml.js",
                ),
            "image" => array(
                "logout" => array(
                    "type" => "gif",
                    "file" => "logout.gif",
                    ),
                "login" => array(
                    "type" => "gif",
                    "file" => "login.gif",
                    ),
                "config" => array(
                    "type" => "gif",
                    "file" => "config.gif",
                    ),
                "pkglist" => array(
                    "type" => "png",
                    "file" => "pkglist.png",
                    ),
                "pkgsearch" => array(
                    "type" => "png",
                    "file" => "pkgsearch.png",
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
                ),
            );
            
        $file = $handles[$group][$handle];
        switch ($group)
        {
        case 'css':
            Header("Content-Type: text/css");
            readfile(dirname(__FILE__).'/Web/'.$file);
            exit;
        case 'image':
            Header("Content-Type: image/".$file['type']);
            readfile(dirname(__FILE__).'/Web/'.$file['file']);
            exit;
        case 'js':
            Header("Content-Type: text/javascript");
            readfile(dirname(__FILE__).'/Web/'.$file);
            exit;
        };
    }

    // }}}
}

?>
