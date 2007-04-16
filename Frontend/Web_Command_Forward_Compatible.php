<?php
/**
 * Web_Command_Forward_Compatible
 * Slightly different implementations of PEAR Commands,
 * Forward compatible class for submited bugs that will be fixed 'later'
 *
 * PHP versions 4 and 5
 *
 * LICENSE: This source file is subject to version 3.0 of the PHP license
 * that is available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_0.txt.  If you did not receive a copy of
 * the PHP License and are unable to obtain it through the web, please
 * send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category   pear
 * @package    PEAR_Frontend_Web
 * @author     Tias Guns <tias@ulyssis.org>
 * @copyright  1997-2007 The PHP Group
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    CVS: $Id$
 * @link       http://pear.php.net/package/PEAR_Frontend_Web
 * @since      File available since Release 0.6.0
 */

/**
 * base class
 */
require_once 'PEAR/Command/Common.php';

/**
 * Slightly different implementations of PEAR Commands,
 * Forward compatible class for submited bugs that will be fixed 'later'
 *
 * @category   pear
 * @package    PEAR_Frontend_Web
 * @author     Tias Guns <tias@ulyssis.org>
 * @copyright  1997-2007 The PHP Group
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    Release: TODO
 * @link       http://pear.php.net/package/PEAR_Frontend_Web
 * @since      File available since Release 0.6.0
 */
class Web_Command_Forward_Compatible extends PEAR_Command_Common
{
    // {{{ properties

    var $commands = array();

    // }}}
    // {{{ constructor

    /**
     * PEAR_Command_Registry constructor.
     *
     * @access public
     */
    function Web_Command_Forward_Compatible(&$ui, &$config)
    {
        parent::PEAR_Command_Common($ui, $config);
    }

    // }}}

    // {{{ doList()
    // Reported in bug #10496 :: list with channel information
    // Original file: Command/Registry.php
    function doList($command, $options, $params)
    {
        require_once 'PEAR/Command/Registry.php';
        $cmd = new PEAR_Command_Registry(&$this->ui, &$this->config);

        if (isset($options['allchannels'])) {
            return $this->reg_doListAll($command, array(), $params);
        }
        $reg = &$this->config->getRegistry();
        if (count($params) == 1) {
            return $cmd->doFileList($command, $options, $params);
        }
        if (isset($options['channel'])) {
            if ($reg->channelExists($options['channel'])) {
                $channel = $reg->channelName($options['channel']);
            } else {
                return $this->raiseError('Channel "' . $options['channel'] .'" does not exist');
            }
        } else {
            $channel = $this->config->get('default_channel');
        }
        $installed = $reg->packageInfo(null, null, $channel);
        usort($installed, array(&$cmd, '_sortinfo'));
        $i = $j = 0;
        $data = array(
            'caption' => 'Installed packages, channel ' .
                $channel . ':',
            'border' => true,
            'headline' => array('Channel', 'Package', 'Version', 'State'),
            'channel' => $channel,
            );
        foreach ($installed as $package) {
            $pobj = $reg->getPackage(isset($package['package']) ?
                                        $package['package'] : $package['name'], $channel);
            $data['data'][] = array($channel,
                                    $pobj->getPackage(),
                                    $pobj->getVersion(),
                                    $pobj->getState() ? $pobj->getState() : null);
        }
        if (count($installed)==0) {
            unset($data['headline']);
            $data['data'] = '(no packages installed)';
        }
        $this->ui->outputData($data, $command);
        return true;
    }
    function reg_doListAll($command, $options, $params)
    {
        $reg = &$this->config->getRegistry();
        $channels = $reg->getChannels();
        foreach($channels as $channel) {
            $options['channel'] = $channel->getName();
            $this->doList($command, $options, $params);
        }
        return true;
    }

    // }}}
    // {{{ doListUpgrades()
    // Reported in bug #10515 :: list-upgrades with channel information
    // Original file: Command/Remote.php
    function doListUpgrades($command, $options, $params)
    {
        require_once 'PEAR/Command/Remote.php';
        $cmd = new PEAR_Command_Remote(&$this->ui, &$this->config);

        require_once 'PEAR/Common.php';
        if (isset($params[0]) && !is_array(PEAR_Common::betterStates($params[0]))) {
            return $this->raiseError($params[0] . ' is not a valid state (stable/beta/alpha/devel/etc.) try "pear help list-upgrades"');
        }
        $savechannel = $channel = $this->config->get('default_channel');
        $reg = &$this->config->getRegistry();
        foreach ($reg->listChannels() as $channel) {
            $inst = array_flip($reg->listPackages($channel));
            if ($channel == '__uri') {
                continue;
            }
            $this->config->set('default_channel', $channel);
            if (empty($params[0])) {
                $state = $this->config->get('preferred_state');
            } else {
                $state = $params[0];
            }
            $caption = $channel . ' Available Upgrades';
            $chan = $reg->getChannel($channel);
            if (PEAR::isError($e = $cmd->_checkChannelForStatus($channel, $chan))) {
                return $e;
            }
            if ($chan->supportsREST($this->config->get('preferred_mirror')) &&
                  $base = $chan->getBaseURL('REST1.0', $this->config->get('preferred_mirror'))) {
                $rest = &$this->config->getREST('1.0', array());
                if (empty($state) || $state == 'any') {
                    $state = false;
                } else {
                    $caption .= ' (' . implode(', ', PEAR_Common::betterStates($state, true)) . ')';
                }
                PEAR::staticPushErrorHandling(PEAR_ERROR_RETURN);
                $latest = $rest->listLatestUpgrades($base, $state, $inst, $channel, $reg);
                PEAR::staticPopErrorHandling();
            } else {
                $remote = &$this->config->getRemote();
                $remote->pushErrorHandling(PEAR_ERROR_RETURN);
                if (empty($state) || $state == 'any') {
                    $latest = $remote->call("package.listLatestReleases");
                } else {
                    $latest = $remote->call("package.listLatestReleases", $state);
                    $caption .= ' (' . implode(', ', PEAR_Common::betterStates($state, true)) . ')';
                }
                $remote->popErrorHandling();
            }
            if (PEAR::isError($latest)) {
                $this->ui->outputData($latest->getMessage());
                continue;
            }
            $caption .= ':';
            if (PEAR::isError($latest)) {
                $this->config->set('default_channel', $savechannel);
                return $latest;
            }
            $data = array(
                'caption' => $caption,
                'border' => 1,
                'headline' => array('Channel', 'Package', 'Local', 'Remote', 'Size'),
                'channel' => $channel,
                );
            foreach ((array)$latest as $pkg => $info) {
                $package = strtolower($pkg);
                if (!isset($inst[$package])) {
                    // skip packages we don't have installed
                    continue;
                }
                extract($info);
                $inst_version = $reg->packageInfo($package, 'version', $channel);
                $inst_state   = $reg->packageInfo($package, 'release_state', $channel);
                if (version_compare("$version", "$inst_version", "le")) {
                    // installed version is up-to-date
                    continue;
                }
                if ($filesize >= 20480) {
                    $filesize += 1024 - ($filesize % 1024);
                    $fs = sprintf("%dkB", $filesize / 1024);
                } elseif ($filesize > 0) {
                    $filesize += 103 - ($filesize % 103);
                    $fs = sprintf("%.1fkB", $filesize / 1024.0);
                } else {
                    $fs = "  -"; // XXX center instead
                }
                $data['data'][] = array($channel, $pkg, "$inst_version ($inst_state)", "$version ($state)", $fs);
            }
            if (empty($data['data'])) {
                unset($data['headline']);
                if (count($inst) == 0) {
                    $data['data'] = '(no packages installed)';
                } else {
                    $data['data'] = '(no upgrades available)';
                }
            }
            $this->ui->outputData($data, $command);
        }
        $this->config->set('default_channel', $savechannel);
        return true;
    }

    // }}}
    // {{{ doListPackages()
    // Reported in bug !UNSBUMITTED!
    // Original file will be: Command/Remote.php
    function doListPackages($command, $options, $params)
    {
        require_once 'PEAR/Command/Remote.php';
        $cmd = new PEAR_Command_Remote(&$this->ui, &$this->config);

        $reg = &$this->config->getRegistry();
        if ($options['allchannels'] == true) {
            // over all channels
            unset($options['allchannels']);
            $channels = $reg->getChannels();
            foreach ($channels as $channel) {
                if ($channel->getName() != '__uri') {
                    $options['channel'] = $channel->getName();
                    $ret = $this->doListPackages($command, $options, $params);
                    if ($ret !== true) {
                        return $ret;
                    }
                }
            }
            return true;
        }

        $savechannel = $channel = $this->config->get('default_channel');
        if (isset($options['channel'])) {
            $channel = $options['channel'];
            if ($reg->channelExists($channel)) {
                $this->config->set('default_channel', $channel);
            } else {
                return $this->raiseError("Channel \"$channel\" does not exist");
            }
        }
        $chan = $reg->getChannel($channel);
        if (PEAR::isError($e = $cmd->_checkChannelForStatus($channel, $chan))) {
            return $e;
        }
        if ($chan->supportsREST($this->config->get('preferred_mirror')) &&
              $base = $chan->getBaseURL('REST1.0', $this->config->get('preferred_mirror'))) {
            $rest = &$this->config->getREST('1.0', array());
            $packages = $rest->listPackages($base);
        } else {
            return PEAR::raiseError($command.' only works for REST servers');
        }
        if (PEAR::isError($packages)) {
            $this->config->set('default_channel', $savechannel);
            return $this->raiseError('The package list could not be fetched from the remote server. Please try again. (Debug info: "' . $packages->getMessage() . '")');
        }

        $data = array(
            'caption' => 'Channel ' . $channel . ' All packages:',
            'border' => true,
            'headline' => array('Channel', 'Package'),
            'channel' => $channel,
            );

        if (count($packages) === 0) {
            unset($data['headline']);
            $data['data'] = 'No packages registered';
        } else {
            $data['data'] = array();
            foreach($packages as $item) {
                $array = array(
                        $channel,
                        $item,
                            );
                $data['data'][] = $array;
            }
        }

        $this->config->set('default_channel', $savechannel);
        $this->ui->outputData($data, $command);
        return true;
    }


    // }}}
    // {{{ doListCategories()
    // Reported in bug !UNSBUMITTED!
    // Original file will be: Command/Remote.php
    function doListCategories($command, $options, $params)
    {
        require_once 'PEAR/Command/Remote.php';
        $cmd = new PEAR_Command_Remote(&$this->ui, &$this->config);

        $reg = &$this->config->getRegistry();
        if ($options['allchannels'] == true) {
            // over all channels
            unset($options['allchannels']);
            $channels = $reg->getChannels();
            foreach ($channels as $channel) {
                if ($channel->getName() != '__uri') {
                    $options['channel'] = $channel->getName();
                    $ret = $this->doListCategories($command, $options, $params);
                    if ($ret !== true) {
                        return $ret;
                    }
                }
            }
            return true;
        }

        $savechannel = $channel = $this->config->get('default_channel');
        if (isset($options['channel'])) {
            $channel = $options['channel'];
            if ($reg->channelExists($channel)) {
                $this->config->set('default_channel', $channel);
            } else {
                return $this->raiseError("Channel \"$channel\" does not exist");
            }
        }
        $chan = $reg->getChannel($channel);
        if (PEAR::isError($e = $cmd->_checkChannelForStatus($channel, $chan))) {
            return $e;
        }
        if ($chan->supportsREST($this->config->get('preferred_mirror')) &&
              $base = $chan->getBaseURL('REST1.0', $this->config->get('preferred_mirror'))) {
            $rest = &$this->config->getREST('1.0', array());
            $categories = $this->REST_listCategories(&$rest, $base);
        } else {
            return PEAR::raiseError($command.' only works for REST servers');
        }
        if (PEAR::isError($categories)) {
            $this->config->set('default_channel', $savechannel);
            return $this->raiseError('The category list could not be fetched from the remote server. Please try again. (Debug info: "' . $categories->getMessage() . '")');
        }

        $data = array(
            'caption' => 'Channel ' . $channel . ' All categories:',
            'border' => true,
            'headline' => array('Channel', 'Category'),
            'channel' => $channel,
            );

        if (count($categories) === 0) {
            unset($data['headline']);
            $data['data'] = 'No categories registered';
        } else {
            $data['data'] = array();
            foreach($categories as $item) {
                $array = array(
                        $channel,
                        $item['_content'],
                            );
                if (isset($options['packages']) && $options['packages']) {
                    // get packagenames
                    // TODO
                    //$array[] = array('Tias1', 'Tias2');
                }
                $data['data'][] = $array;
            }
        }

        $this->config->set('default_channel', $savechannel);
        $this->ui->outputData($data, $command);
        return true;
    }

    // }}}
    // {{{ doListCategory()
    // Reported in bug !UNSBUMITTED!
    // Original file will be: Command/Remote.php
    function doListCategory($command, $options, $params)
    {
        require_once 'PEAR/Command/Remote.php';
        $cmd = new PEAR_Command_Remote(&$this->ui, &$this->config);

        $channel = array_shift($params);
        if (count($params) > 1) {
            foreach($params as $pkg) {
                $ret = $this->doListCategory($command, $options, array($channel, $pkg));
                if ($ret !== true) {
                    return $ret;
                }
            }
            return $ret;
        }
        $category = $params[0];
            
        $savechannel = $this->config->get('default_channel');
        $reg = &$this->config->getRegistry();
        if ($reg->channelExists($channel)) {
            $this->config->set('default_channel', $channel);
        } else {
            return $this->raiseError("Channel \"$channel\" does not exist");
        }

        $chan = $reg->getChannel($channel);
        if (PEAR::isError($e = $cmd->_checkChannelForStatus($channel, $chan))) {
            return $e;
        }
        if ($chan->supportsREST($this->config->get('preferred_mirror')) &&
              $base = $chan->getBaseURL('REST1.0', $this->config->get('preferred_mirror'))) {
            $rest = &$this->config->getREST('1.0', array());
        } else {
            return PEAR::raiseError($command.' only works for REST servers');
        }
        if (PEAR::isError($categories)) {
            $this->config->set('default_channel', $savechannel);
            return $this->raiseError('The category list could not be fetched from the remote server. Please try again. (Debug info: "' . $categories->getMessage() . '")');
        }

        $data = array(
            'caption' => 'Channel '.$channel.' Category '.$category.' All packages:',
            'border' => true,
            'headline' => array('Channel', 'Package'),
            'channel' => $channel,
            );
        $packages = $this->REST_listCategory(&$rest, $base, $category);
        if (count($packages) === 0) {
            unset($data['headline']);
            $data['data'] = 'No packages registered';
        } else {
            $data['data'] = array();
            foreach($packages as $item) {
                $array = array(
                        $channel,
                        $item['_content'],
                            );
                $data['data'][] = $array;
            }
        }

        $this->config->set('default_channel', $savechannel);
        $this->ui->outputData($data, $command);
        return true;
    }

    /**
     * List a category of a REST server
     *
     * @param string $base base URL of the server
     * @param string $category name of the category
     * @param boolean $info also download full package info TODO
     * @return array of packagenames
     */
    // Reported in bug !UNREPORTED!
    // Original file: REST/10.php
    function REST_listCategory(&$rest, $base, $category, $info=false)
    {
        // gives '404 Not Found' error when category doesn't exist
        $packagelist = $rest->_rest->retrieveData($base.'c/'.urlencode($category).'/packages.xml');
        if (PEAR::isError($packagelist)) {
            return $packagelist;
        }
        if (!is_array($packagelist) || !isset($packagelist['p'])) {
            return array();
        }
        if (!is_array($packagelist['p']) ||
            !isset($packagelist['p'][0])) { // only 1 pkg
            $packagelist['p'] = array($packagelist['p']);
        }
        return $packagelist['p'];
    }

    /**
     * List all categories of a REST server
     *
     * @param string $base base URL of the server
     * @return array of categorynames
     */
    // Reported in bug !UNREPORTED!
    // Original file: REST/10.php
    function REST_listCategories(&$rest, $base)
    {
        $categorylist = $rest->_rest->retrieveData($base . 'c/categories.xml');
        if (PEAR::isError($categorylist)) {
            return $categorylist;
        }
        if (!is_array($categorylist) || !isset($categorylist['c'])) {
            return array();
        }
        if (!is_array($categorylist['c'])) {
            $categorylist['c'] = array($categorylist['c']);
        }
        return $categorylist['c'];
    }

    // }}}
    // {{{ doListAll()
    // Reported in bug #10495 :: list-all with channel information etc
    // Original file: Command/Remote.php
    function doListAll($command, $options, $params)
    {
        require_once 'PEAR/Command/Remote.php';
        $cmd = new PEAR_Command_Remote(&$this->ui, &$this->config);

        $savechannel = $channel = $this->config->get('default_channel');
        $reg = &$this->config->getRegistry();
        if (isset($options['channel'])) {
            $channel = $options['channel'];
            if ($reg->channelExists($channel)) {
                $this->config->set('default_channel', $channel);
            } else {
                return $this->raiseError("Channel \"$channel\" does not exist");
            }
        }
        $list_options = false;
        if ($this->config->get('preferred_state') == 'stable') {
            $list_options = true;
        }
        $chan = $reg->getChannel($channel);
        if (PEAR::isError($e = $cmd->_checkChannelForStatus($channel, $chan))) {
            return $e;
        }
        if ($chan->supportsREST($this->config->get('preferred_mirror')) &&
              $base = $chan->getBaseURL('REST1.1', $this->config->get('preferred_mirror'))) {
            // use faster list-all if available
            $rest = &$this->config->getREST('1.1', array());
            $available = $rest->listAll($base, $list_options, false);
        } elseif ($chan->supportsREST($this->config->get('preferred_mirror')) &&
              $base = $chan->getBaseURL('REST1.0', $this->config->get('preferred_mirror'))) {
            $rest = &$this->config->getREST('1.0', array());
            $available = $rest->listAll($base, $list_options, false);
        } else {
            $r = &$this->config->getRemote();
            if ($channel == 'pear.php.net') {
                // hack because of poor pearweb design
                $available = $r->call('package.listAll', true, $list_options, false);
            } else {
                $available = $r->call('package.listAll', true, $list_options);
            }
        }
        if (PEAR::isError($available)) {
            $this->config->set('default_channel', $savechannel);
            return $this->raiseError('The package list could not be fetched from the remote server. Please try again. (Debug info: "' . $available->getMessage() . '")');
        }
        $data = array(
            'caption' => 'Channel ' . $channel . ' All packages:',
            'border' => true,
            'headline' => array('Channel', 'Package', 'Latest', 'Local', 'Description', 'Dependencies'),
            'channel' => $channel,
            );
        $local_pkgs = $reg->listPackages($channel);

        foreach ($available as $name => $info) {
            $installed = $reg->packageInfo($name, null, $channel);
            if (is_array($installed['version'])) {
                $installed['version'] = $installed['version']['release'];
            }
            $desc = $info['summary'];
            if (isset($params[$name])) {
                $desc .= "\n\n".$info['description'];
            }
            if (isset($options['mode']))
            {
                if ($options['mode'] == 'installed' && !isset($installed['version'])) {
                    continue;
                }
                if ($options['mode'] == 'notinstalled' && isset($installed['version'])) {
                    continue;
                }
                if ($options['mode'] == 'upgrades'
                      && (!isset($installed['version']) || version_compare($installed['version'],
                      $info['stable'], '>='))) {
                    continue;
                }
            }
            $pos = array_search(strtolower($name), $local_pkgs);
            if ($pos !== false) {
                unset($local_pkgs[$pos]);
            }

            if (isset($info['stable']) && !$info['stable']) {
                $info['stable'] = null;
            }
            if ($info['stable'] === $info['unstable']) {
                $state = $info['state'];
            } else {
                $state = 'stable';
            }
            $latest = $info['stable'].' ('.$state.')';
            $local = '';
            if (isset($installed['version'])) {
                $inst_state = $reg->packageInfo($name, 'release_state', $channel);
                $local = $installed['version'].' ('.$inst_state.')';
            }

            $data['data'][$info['category']][] = array(
                $channel,
                $reg->channelAlias($channel) . '/' . $name,
                $latest,
                $local,
                isset($desc) ? $desc : null,
                isset($info['deps']) ? $info['deps'] : null,
                );
        }

        if (isset($options['mode']) && in_array($options['mode'], array('notinstalled', 'upgrades'))) {
            $this->config->set('default_channel', $savechannel);
            $this->ui->outputData($data, $command);
            return true;
        }
        foreach ($local_pkgs as $name) {
            $info = &$reg->getPackage($name, $channel);
            $data['data']['Local'][] = array(
                $reg->channelAlias($channel) . '/' . $info->getPackage(),
                '',
                $info->getVersion(),
                $info->getSummary(),
                $info->getDeps()
                );
        }

        $this->config->set('default_channel', $savechannel);
        $this->ui->outputData($data, $command);
        return true;
    }

    // }}}
    // {{{ REST::listAll()
    // Reported in bug #10599 :: search packagename: quick
    // Original file: Rest/10.php
    function REST_listAll(&$rest, $base, $dostable, $basic = true, $searchpackage = false, $searchsummary = false)
    {
        $packagelist = $rest->_rest->retrieveData($base . 'p/packages.xml');
        if (PEAR::isError($packagelist)) {
            return $packagelist;
        }
        if ($rest->_rest->config->get('verbose') > 0) {
            $ui = &PEAR_Frontend::singleton();
            $ui->log('Retrieving data...0%', false);
        }
        $ret = array();
        if (!is_array($packagelist) || !isset($packagelist['p'])) {
            return $ret;
        }
        if (!is_array($packagelist['p'])) {
            $packagelist['p'] = array($packagelist['p']);
        }

        // only search-packagename = quicksearch !
        if ($searchpackage && (!$searchsummary || empty($searchpackage))) {
            $newpackagelist = array();
            foreach ($packagelist['p'] as $package) {
                if (!empty($searchpackage) && stristr($package, $searchpackage) !== false) {
                    $newpackagelist[] = $package;
                }
            }
            $packagelist['p'] = $newpackagelist;
        }

        PEAR::pushErrorHandling(PEAR_ERROR_RETURN);
        $next = .1;
        foreach ($packagelist['p'] as $progress => $package) {
            if ($rest->_rest->config->get('verbose') > 0) {
                if ($progress / count($packagelist['p']) >= $next) {
                    if ($next == .5) {
                        $ui->log('50%', false);
                    } else {
                        $ui->log('.', false);
                    }
                    $next += .1;
                }
            }
            if ($basic) { // remote-list command
                if ($dostable) {
                    $latest = $rest->_rest->retrieveData($base . 'r/' . strtolower($package) .
                        '/stable.txt');
                } else {
                    $latest = $rest->_rest->retrieveData($base . 'r/' . strtolower($package) .
                        '/latest.txt');
                }
                if (PEAR::isError($latest)) {
                    $latest = false;
                }
                $info = array('stable' => $latest);
            } else { // list-all command
                $inf = $rest->_rest->retrieveData($base . 'p/' . strtolower($package) . '/info.xml');
                if (PEAR::isError($inf)) {
                    PEAR::popErrorHandling();
                    return $inf;
                }
                if ($searchpackage) {
                    $found = (!empty($searchpackage) && stristr($package, $searchpackage) !== false);
                    if (!$found && !(isset($searchsummary) && !empty($searchsummary)
                        && (stristr($inf['s'], $searchsummary) !== false
                            || stristr($inf['d'], $searchsummary) !== false)))
                    {
                        continue;
                    };
                }
                $releases = $rest->_rest->retrieveData($base . 'r/' . strtolower($package) .
                    '/allreleases.xml');
                if (PEAR::isError($releases)) {
                    continue;
                }
                if (!isset($releases['r'][0])) {
                    $releases['r'] = array($releases['r']);
                }
                unset($latest);
                unset($unstable);
                unset($stable);
                unset($state);
                foreach ($releases['r'] as $release) {
                    if (!isset($latest)) {
                        if ($dostable && $release['s'] == 'stable') {
                            $latest = $release['v'];
                            $state = 'stable';
                        }
                        if (!$dostable) {
                            $latest = $release['v'];
                            $state = $release['s'];
                        }
                    }
                    if (!isset($stable) && $release['s'] == 'stable') {
                        $stable = $release['v'];
                        if (!isset($unstable)) {
                            $unstable = $stable;
                        }
                    }
                    if (!isset($unstable) && $release['s'] != 'stable') {
                        $latest = $unstable = $release['v'];
                        $state = $release['s'];
                    }
                    if (isset($latest) && !isset($state)) {
                        $state = $release['s'];
                    }
                    if (isset($latest) && isset($stable) && isset($unstable)) {
                        break;
                    }
                }
                $deps = array();
                if (!isset($unstable)) {
                    $unstable = false;
                    $state = 'stable';
                    if (isset($stable)) {
                        $latest = $unstable = $stable;
                    }
                } else {
                    $latest = $unstable;
                }
                if (!isset($latest)) {
                    $latest = false;
                }
                if ($latest) {
                    $d = $rest->_rest->retrieveCacheFirst($base . 'r/' . strtolower($package) . '/deps.' .
                        $latest . '.txt');
                    if (!PEAR::isError($d)) {
                        $d = unserialize($d);
                        if ($d) {
                            if (isset($d['required'])) {
                                if (!class_exists('PEAR_PackageFile_v2')) {
                                    require_once 'PEAR/PackageFile/v2.php';
                                }
                                if (!isset($pf)) {
                                    $pf = new PEAR_PackageFile_v2;
                                }
                                $pf->setDeps($d);
                                $tdeps = $pf->getDeps();
                            } else {
                                $tdeps = $d;
                            }
                            foreach ($tdeps as $dep) {
                                if ($dep['type'] !== 'pkg') {
                                    continue;
                                }
                                $deps[] = $dep;
                            }
                        }
                    }
                }
                if (!isset($stable)) {
                    $stable = '-n/a-';
                }
                if (!$searchpackage) {
                    $info = array('stable' => $latest, 'summary' => $inf['s'], 'description' =>
                        $inf['d'], 'deps' => $deps, 'category' => $inf['ca']['_content'],
                        'unstable' => $unstable, 'state' => $state);
                } else {
                    $info = array('stable' => $stable, 'summary' => $inf['s'], 'description' =>
                        $inf['d'], 'deps' => $deps, 'category' => $inf['ca']['_content'],
                        'unstable' => $unstable, 'state' => $state);
                }
            }
            $ret[$package] = $info;
        }
        PEAR::popErrorHandling();
        return $ret;
    }

    // }}}
    // {{{ doSearch()
    // Needed for bug #10599 :: search packagename: quick
    // Needed for bug #10659 :: search allchannels
    // Original file: Command/Remote.php
    function doSearch($command, $options, $params)
    {
        if ((!isset($params[0]) || empty($params[0]))
            && (!isset($params[1]) || empty($params[1])))
        {
            return $this->raiseError('no valid search string supplied');
        };

        $reg = &$this->config->getRegistry();
        if ($options['allchannels'] == true) {
            // search all channels
            unset($options['allchannels']);
            $channels = $reg->getChannels();
            foreach ($channels as $channel) {
                if ($channel->getName() != '__uri') {
                    $options['channel'] = $channel->getName();
                    $ret = $this->doSearch($command, $options, $params);
                    if ($ret !== true) {
                        return $ret;
                    }
                }
            }
            return true;
        }

        require_once 'PEAR/Command/Remote.php';
        $cmd = new PEAR_Command_Remote(&$this->ui, &$this->config);

        $savechannel = $channel = $this->config->get('default_channel');
        $package = $params[0];
        $summary = isset($params[1]) ? $params[1] : false;
        if (isset($options['channel'])) {
            $reg = &$this->config->getRegistry();
            $channel = $options['channel'];
            if ($reg->channelExists($channel)) {
                $this->config->set('default_channel', $channel);
            } else {
                return $this->raiseError('Channel "' . $channel . '" does not exist');
            }
        }
        $chan = $reg->getChannel($channel);
        if (PEAR::isError($e = $cmd->_checkChannelForStatus($channel, $chan))) {
            return $e;
        }
        if ($chan->supportsREST($this->config->get('preferred_mirror')) &&
              $base = $chan->getBaseURL('REST1.0', $this->config->get('preferred_mirror'))) {
            $rest = &$this->config->getREST('1.0', array());
            $available = $this->REST_listAll(&$rest, $base, false, false, $package, $summary);
        } else {
            $r = &$this->config->getRemote();
            $available = $r->call('package.search', $package, $summary, true, 
                $this->config->get('preferred_state') == 'stable', true);
        }
        if (PEAR::isError($available)) {
            $this->config->set('default_channel', $savechannel);
            return $this->raiseError($available);
        }
        $data = array(
            'caption' => 'Matched packages, channel ' . $channel . ':',
            'border' => true,
            'headline' => array('Channel', 'Package', 'Stable/(Latest)', 'Local'),
            );
        // clean exit, no error !
        if (!$available) {
            unset($data['headline']);
            $data['data'] = 'No packages found that match pattern "'.$package.'".';
        } else {
            foreach ($available as $name => $info) {
                $installed = $reg->packageInfo($name, null, $channel);
                $desc = $info['summary'];
                if (isset($params[$name]))
                    $desc .= "\n\n".$info['description'];

                if (!isset($info['stable']) || !$info['stable']) {
                    $version_remote = 'none';
                } else {
                    if ($info['unstable']) {
                        $version_remote = $info['unstable'];
                    } else {
                        $version_remote = $info['stable'];
                    }
                    $version_remote .= ' ('.$info['state'].')';
                }
                $version = is_array($installed['version']) ? $installed['version']['release'] :
                    $installed['version'];
                $data['data'][$info['category']][] = array(
                    $channel,
                    $name,
                    $version_remote,
                    $version,
                    $desc,
                    );
            }
        }
        $this->ui->outputData($data, $command);
        $this->config->set('default_channel', $channel);
        return true;
    }

}

?>
