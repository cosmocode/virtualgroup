<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\Event;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Logger;

/**
 * DokuWiki Plugin virtualgroup (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 */
class action_plugin_virtualgroup extends ActionPlugin
{
    /** @inheritdoc */
    public function register(EventHandler $controller)
    {
        $controller->register_hook('DOKUWIKI_INIT_DONE', 'BEFORE', $this, 'start');
    }

    /**
     * Add the virtual groups to the current user
     *
     * @param Event $event DOKUWIKI_INIT_DONE
     * @return void
     */
    public function start(Event $event)
    {
        global $USERINFO;
        global $INFO;
        global $INPUT;

        $user = $INPUT->server->str('REMOTE_USER');

        if (!$user) {
            return;
        }

        $groupinfo = $this->loadConfiguration();
        if (!isset($groupinfo[$user])) {
            return;
        }
        if (!isset($USERINFO['grps'])) {
            $USERINFO['grps'] = [];
        }
        $grps = array_unique(array_merge($USERINFO['grps'], $groupinfo[$user]));
        $USERINFO['grps'] = $grps;
        $_SESSION[DOKU_COOKIE]['auth']['info']['grps'] = $grps;
        $INFO = pageinfo();
    }

    /**
     * load the users -> group connection
     *
     * @return array [user => [group1, group2, ...], ...]
     */
    public function loadConfiguration()
    {
        // determine the path to the data
        $userFile = DOKU_CONF . 'virtualgrp.json';

        // if there is no file, try loading (and converting) from the old location
        if (!is_readable($userFile)) {
            return $this->loadLegacyConfiguration();
        }

        // read the file
        $content = trim(file_get_contents($userFile));

        // if its empty we have no data also
        if (empty($content)) return [];

        try {
            return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            Logger::error('Failed to parse virtualgrp.json: ' . $e->getMessage());
            // Do NOT delete malformed configuration file here,
            // otherwise compat mode will restore possibly outdated permissions.
        }
        return [];
    }

    /**
     * Load users from the old location and try to convert them to the new location.
     *
     * @return array [user => [group1, group2, ...], ...]
     */
    protected function loadLegacyConfiguration()
    {
        global $conf;
        // determine the path to the data
        $userFile = $conf['savedir'] . '/virtualgrp.php';

        // if there is no file we hava no data ;-)
        if (!is_file($userFile)) return [];

        // read the file
        $content = trim(file_get_contents($userFile));

        // if its empty we have no data also
        if (empty($content)) return [];

        $users = unserialize($content);
        // check for invalid data
        if ($users === false) {
            Logger::error('Failed to parse virtualgrp.php configuration file. File will be deleted.');
            @unlink($userFile);
            return[];
        }

        // try to reencode $users in json format, give up if not possible
        $json = json_encode($users, JSON_PRETTY_PRINT);
        if ($json === false) {
            return $users;
        }

        // try to write in new location, give up if not possible
        $newUserFile = DOKU_CONF . 'virtualgrp.json';
        $written = file_put_contents($newUserFile, $json);
        if ($written === false) {
            Logger::error('Failed to write virtualgrp.json configuration file.');
            return $users;
        }

        // try to remove old location, ignoring errors
        @unlink($userFile);
        return $users;
    }
}
