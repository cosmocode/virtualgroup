<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\Event;
use dokuwiki\Extension\EventHandler;
use dokuwiki\plugin\virtualgroup\VirtualGroups;

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
        if (!$user) return;

        $virtualGroups = new VirtualGroups();
        $groupinfo = $virtualGroups->getUserStructure();
        if (!isset($groupinfo[$user])) return;

        if (!isset($USERINFO['grps'])) $USERINFO['grps'] = [];
        $grps = array_unique(array_merge($USERINFO['grps'], $groupinfo[$user]));
        $USERINFO['grps'] = $grps;
        $_SESSION[DOKU_COOKIE]['auth']['info']['grps'] = $grps;
        $INFO = pageinfo();
    }
}
