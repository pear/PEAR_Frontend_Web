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
require_once "HTML/Template/IT.php";
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

    /**
     * Container, where values can be saved temporary
     * @var array
     * @access private
     */
    var $_data = array();
    
    // }}}

    // {{{ constructor

    function PEAR_Frontend_Web()
    {
        parent::PEAR();
        $GLOBALS['_PEAR_Frontend_Web_log'] = '';
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
        $tpl = new HTML_Template_IT(dirname(__FILE__)."/Web");
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
        if (isset($GLOBALS['_PEAR_Frontend_Web_log']) && trim($GLOBALS['_PEAR_Frontend_Web_log']))
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
        
        if (!isset($data['data'])) {
            $data['data'] = array();
        };
        
        // Use PEAR::Pager to page Packages
        $pager =& new Pager(array(
            'itemData'  => $data['data'],
            'perPage'   => ($paging ? 5 : count($data['data'])),
            'linkClass' => 'green',
            ));
        $data['data'] = $pager->getPageData();
        $links = $pager->getLinks();
        list($from, $to) = $pager->getOffsetByPageId();
        // Generate Linkinformation to redirect to _this_ page after performing an action
        $links['current'] = '&pageID='.$pager->getCurrentPage();
        if (isset($_GET['mode']))
            $links['current'] .= '&mode='.$_GET['mode'];
        else
            $_GET['mode'] = '';
        if (isset($_GET['command']) && $_GET['command'] == 'search')
            $links['current'] .= '&redirect=search&0='.$_REQUEST[0].'&1='.$_REQUEST[1];
            
        
        $modes = array(
            'installed'    => 'list installed packages',
            ''             => 'list all packages',
            'notinstalled' => 'list not installed packages',
            'upgrades'     => 'list avail. upgrades',
            );
        unset($modes[$_GET['mode']]);
        
        $i = 1;
        foreach($modes as $mode => $text) {
            $tpl->setVariable('mode'.$i, ((!empty($mode)) ? '?mode='.$mode : ''));
            $tpl->setVariable('mode'.$i.'text', $text);
            $i++;
        };
        
        $tpl->setVariable('Prev', $links['back']);
        $tpl->setVariable('Next', $links['next']);
        $tpl->setVariable('PagerFrom', $from);
        $tpl->setVariable('PagerTo', $to);
        $tpl->setVariable('PagerCount', $pager->numItems());

        if (is_array($data['data']))
        foreach($data['data'] as $category => $packages)
        {
            foreach($packages as $row)
            {
                list($pkgName, $pkgVersionLatest, $pkgVersionInstalled, $pkgSummary) = $row;
                $tpl->setCurrentBlock("Row");
                $tpl->setVariable("ImgPackage", $_SERVER["PHP_SELF"].'?img=package');
                $images = array(
                    'install' => '<img src="'.$_SERVER["PHP_SELF"].'?img=install" border="0" alt="install">',
                    'uninstall' => '<img src="'.$_SERVER["PHP_SELF"].'?img=uninstall" border="0" alt="uninstall">',
                    'upgrade' => '<img src="'.$_SERVER["PHP_SELF"].'?img=install" border="0" alt="upgrade">',
                    'info' => '<img src="'.$_SERVER["PHP_SELF"].'?img=info" border="0" alt="info">',
                    'infoExt' => '<img src="'.$_SERVER["PHP_SELF"].'?img=infoplus" border="0" alt="extended info">',
                    );
                $compare = version_compare($pkgVersionLatest, $pkgVersionInstalled);
                if (!$pkgVersionInstalled || $pkgVersionInstalled == "- no -") {
                    $inst = sprintf(
                        '<a href="%s?command=install&pkg=%s%s">%s</a>',
                        $_SERVER["PHP_SELF"], $pkgName, $links['current'], $images['install']);
                    $del = '';
                } else if ($compare == 1) {
                    $inst = sprintf(
                        '<a href="%s?command=upgrade&pkg=%s%s">%s</a>',
                        $_SERVER["PHP_SELF"], $pkgName, $links['current'], $images['upgrade']);
                    $del = sprintf(
                        '<a href="%s?command=uninstall&pkg=%s%s" %s>%s</a>',
                        $_SERVER["PHP_SELF"], $pkgName, $links['current'], 
                        'onClick="return confirm(\'Do you really want to uninstall \\\''.$row[0].'\\\'?\')"',
                        $images['uninstall']);
                } else {
                    $del = sprintf(
                        '<a href="%s?command=uninstall&pkg=%s%s" %s>%s</a>',
                        $_SERVER["PHP_SELF"], $pkgName, $links['current'], 
                        'onClick="return confirm(\'Do you really want to uninstall \\\''.$row[0].'\\\'?\')"',
                        $images['uninstall']);
                    $inst = '';
                };
                $info=sprintf('<a href="%s?command=remote-info&pkg=%s">%s</a>',
                    $_SERVER["PHP_SELF"], $pkgName, $images['info']);
                $infoExt=sprintf('<a href="%s?package=%s">%s</a>',
                    'http://pear.php.net/package-info.php', $row[0], $images['infoExt']);
                        
                if ($pkgName == 'PEAR' || $pkgName == 'Archive_Tar')
                    $del = '';
                        
                $tpl->setVariable("Version", $pkgVersionLatest);
                $tpl->setVariable("Installed", $pkgVersionInstalled);
                $tpl->setVariable("Install", $inst);
                $tpl->setVariable("Delete", $del);
                $tpl->setVariable("Info", $info);
                $tpl->setVariable("InfoExt", $infoExt);
                $tpl->setVariable("Package", $pkgName);
                $tpl->setVariable("Summary", nl2br($pkgSummary));
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
        $tpl->setVariable("License", $data['license']);
        $tpl->setVariable("Category", $data['category']);
        $tpl->setVariable("Summary", nl2br($data['summary']));
        $tpl->setVariable("Description", nl2br($data['description']));

        $compare = version_compare($data['stable'], $data['installed']);
        $opt_img = array();
        $opt_text = array();
        if (!$data['installed'] || $data['installed'] == "- no -") {
            $opt_img[] = sprintf(
                '<a href="%s?command=install&pkg=%s&redirect=info"><img src="%s?img=install" border="0" alt="install"></a>',
                $_SERVER["PHP_SELF"], $data['name'], $_SERVER["PHP_SELF"]);
            $opt_text[] = sprintf(
                '<a href="%s?command=install&pkg=%s&redirect=info" class="green">Install package</a>',
                $_SERVER["PHP_SELF"], $data['name'], $_SERVER["PHP_SELF"]);
        } else if ($compare == 1) {
            $opt_img[] = sprintf(
                '<a href="%s?command=upgrade&pkg=%s&redirect=info"><img src="%s?img=install" border="0" alt="upgrade"></a><br>',
                $_SERVER["PHP_SELF"], $data['name'], $_SERVER["PHP_SELF"]);
            $opt_text[] = sprintf(
                '<a href="%s?command=upgrade&pkg=%s&redirect=info" class="green">Upgrade package</a>',
                $_SERVER["PHP_SELF"], $data['name'], $_SERVER["PHP_SELF"]);
            $opt_img[] = sprintf(
                '<a href="%s?command=uninstall&pkg=%s&redirect=info" %s><img src="%s?img=uninstall" border="0" alt="uninstall"></a>',
                $_SERVER["PHP_SELF"], $data['name'], 
                'onClick="return confirm(\'Do you really want to uninstall \\\''.$data['name'].'\\\'?\')"',
                $_SERVER["PHP_SELF"]);
            $opt_text[] = sprintf(
                '<a href="%s?command=uninstall&pkg=%s&redirect=info" class="green" %s>Uninstall package</a>',
                $_SERVER["PHP_SELF"], $data['name'], 
                'onClick="return confirm(\'Do you really want to uninstall \\\''.$data['name'].'\\\'?\')"',
                $_SERVER["PHP_SELF"]);
        } else {
            $opt_img[] = sprintf(
                '<a href="%s?command=uninstall&pkg=%s&redirect=info" %s><img src="%s?img=uninstall" border="0" alt="uninstall"></a>',
                $_SERVER["PHP_SELF"], $data['name'], 
                'onClick="return confirm(\'Do you really want to uninstall \\\''.$data['name'].'\\\'?\')"',
                $_SERVER["PHP_SELF"]);
            $opt_text[] = sprintf(
                '<a href="%s?command=uninstall&pkg=%s&redirect=info" class="green" %s>Uninstall package</a>',
                $_SERVER["PHP_SELF"], $data['name'], 
                'onClick="return confirm(\'Do you really want to uninstall \\\''.$data['name'].'\\\'?\')"',
                $_SERVER["PHP_SELF"]);
        };

        if (isset($opt_img[0]))
        {
            $tpl->setVariable("Opt_Img_1", $opt_img[0]);
            $tpl->setVariable("Opt_Text_1", $opt_text[0]);
        };
        if (isset($opt_img[1]))
        {
            $tpl->setVariable("Opt_Img_2", $opt_img[1]);
            $tpl->setVariable("Opt_Text_2", $opt_text[1]);
        };
        
        $tpl->show();
        return true;
    }
    
    /**
     * Output all kinds of data depending on the command which called this method
     * 
     * @param mixed  $data    datastructure containing the information to display
     * @param string $command (optional) command from which this method was called
     *
     * @access public
     * 
     * @return mixed highly depends on the command
     */
     
    function outputData($data, $command = '_default')
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
                $this->_data[$command] = $data;
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
        // If this is an Answer GET Request , we can return the userinput
        if (isset($_GET["command"]) && $_GET["command"]==$command
            && isset($_GET["userDialogResult"]) && $_GET["userDialogResult"]=='get')
        {
            $result = array();
            foreach($prompts as $key => $prompt)
                $result[$key] = $_GET[$key];
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
        if (isset($this->_data[$command]))
            $tpl->setVariable("Headline", nl2br($this->_data[$command]));
    
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
                $type    = (isset($types[$key])    ? $types[$key]    : 'text');
                $default = (isset($defaults[$key]) ? $defaults[$key] : '');
                $tpl->setVariable("prompt", $prompt);
                $tpl->setVariable("name", $key);
                $tpl->setVariable("default", $default);
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
                "manual" => array(
                    "type" => "gif",
                    "file" => "manual.gif",
                    ),
                "download" => array(
                    "type" => "gif",
                    "file" => "download.gif",
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
