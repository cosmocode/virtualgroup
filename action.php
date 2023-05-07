<?php
if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once DOKU_PLUGIN.'action.php';
class action_plugin_virtualgroup extends DokuWiki_Action_Plugin {

    var $users;

    function getInfo(){
        return confToHash(dirname(__FILE__).'/plugin.info.txt');
    }

    function register(Doku_Event_Handler $controller) {
        $controller->register_hook('DOKUWIKI_INIT_DONE', 'BEFORE', $this,'start');
    }

    function start(&$event, $param) {
        global $USERINFO;
        global $auth;
        global $INFO;
        if (!$_SERVER['REMOTE_USER']) {
            return;
        }

        $this->_load();
        if (!isset($this->users[$_SERVER['REMOTE_USER']])) {
            return;
        }
        if (!isset($USERINFO['grps'])) {
            $USERINFO['grps'] = array();
        }
        $grps = array_unique(array_merge($USERINFO['grps'],$this->users[$_SERVER['REMOTE_USER']]));
        $USERINFO['grps']       = $grps;
        $_SESSION[DOKU_COOKIE]['auth']['info']['grps'] = $grps;
        $INFO = pageinfo();
    }

    /**
     * load the users -> group connection
     */
    function _load() {
        // determine the path to the data
        $userFile = DOKU_CONF . 'virtualgrp.json';

        // if there is no file, try loading (and converting) from the old location
        if (!is_readable($userFile)) {
            $this->_compat_load();
            return;
        }

        // read the file
        $content = file_get_contents($userFile);

        // if its empty we have no data also
        if (empty($content)) {
            $this->users = array();
            return;
        }

        $users = json_decode($content, true);
        // check for invalid data
        if ($users === FALSE) {
            $this->users = array();
            // Do NOT delete malformed configuration file here,
            // otherwise compat mode will restore possibly outdated permissions.
            return;
        }

        $this->users = $users;
    }


    function _compat_load() {
        global $conf;
        // determine the path to the data
        $userFile = $conf['savedir'] . '/virtualgrp.php';

        // if there is no file we hava no data ;-)
        if (!is_file($userFile)) {
            $this->users = array();
            return;
        }

        // read the file
        $content = file_get_contents($userFile);

        // if its empty we have no data also
        if (empty($content)) {
            $this->users = array();
            return;
        }

        $users = unserialize($content);
        // check for invalid data
        if ($users === FALSE) {
            $this->users = array();
            @unlink($userFile);
            return;
        }

        // place the users array
        $this->users = $users;

        // try to reencode $users in json format, give up if not possible
        $json = json_encode($users, 2);
        if ($json === FALSE) {
            return;
        }

        // try to write in new location, give up if not possible
        $newUserFile = DOKU_CONF . 'virtualgrp.json';
        $written = file_put_contents($newUserFile, $json);
        if ($written === FALSE) {
            return;
        }

        // try to remove old location, ignoring errors
        @unlink($userFile);
    }
}
?>
