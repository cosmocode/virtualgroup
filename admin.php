<?php

use dokuwiki\Extension\AdminPlugin;

if (!defined('DOKU_INC')) define('DOKU_INC', realpath(__DIR__ . '/../../') . '/');
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_PLUGIN . 'admin.php');
require_once(DOKU_INC . 'inc/common.php');

class admin_plugin_virtualgroup extends AdminPlugin
{
    public $users;
    public $groups;
    public $_auth;        // auth object

    public $editgroup = false;
    public $edit = false;

    public $data = [];

    public function __construct()
    {
        global $auth;

        $this->setupLocale();

        if (isset($auth)) {
           // we're good to go
            $this->_auth = & $auth;
        }
    }

    public function getInfo()
    {
        return confToHash(__DIR__ . '/plugin.info.txt');
    }

    public function getMenuSort()
    {
        return 999;
    }

    /**
     * handle user request
     */
    public function handle()
    {
        global $auth;
        $this->_load();

        $act  = $_REQUEST['cmd'];
        $uid  = $_REQUEST['uid'];
        switch ($act) {
            case 'del':
                $this->del($uid);
                break;
            case 'edit':
                $this->edit($uid);
                break;
            case 'add':
                $this->add($uid);
                break;
            case 'addgroup':
                $this->addgroup($uid);
                break;
            case 'editgroup':
                $this->editgroup($uid);
                break;
            case 'delgroup':
                $this->delgroup($uid);
                break;
        }
    }

    public function edit($user)
    {
        if (!checkSecurityToken()) return false;
        $grp = [];
        // on input change the data
        if (isset($_REQUEST['grp']) && isset($this->users[$user])) {
            $grp = $_REQUEST['grp'];

            // get the groups as array
            $grp = str_replace(' ', '', $grp);
            $grps = array_unique(explode(',', $grp));
            $this->users[$user] = $grps;
            $this->_save();
            return;
        } else {
            $grp = $this->users[$user];
        }

        // go to edit mode ;-)
        $this->edit = true;
        $this->data['user'] = $user;
        $this->data['grp'] = $grp;
    }

    public function editgroup($group)
    {
        if (!checkSecurityToken()) return false;

        // on input change the data
        if (isset($_REQUEST['users']) && isset($this->groups[$group])) {
            // get the users as array
            $users = str_replace(' ', '', $_REQUEST['users']);
            $users = array_unique(explode(',', $users));

            // delete removed users from group
            foreach (array_diff($this->groups[$group], $users) as $user) {
                $idx = array_search($group, $this->users[$user]);
                if ($idx !== false) {
                    unset($this->users[$user][$idx]);
                    $this->users[$user] = array_values($this->users[$user]);
                    if ($this->users[$user] === []) {
                        unset($this->users[$user]);
                    }
                }
            }

            // add new users to group
            foreach (array_diff($users, $this->groups[$group]) as $user) {
                if ($user && (!isset($this->users[$user]) || !in_array($group, $this->users[$user]))) {
                    $this->users[$user][] = $group;
                }
            }
            $this->_save();
            return;
        }

        // go to edit mode ;-)
        $this->editgroup = true;
        $this->data['users'] = $this->groups[$group];
        $this->data['group'] = $group;
    }

    public function del($user)
    {
        if (!checkSecurityToken()) return false;
        // user don't exist
        if (!$this->users[$user]) {
            return;
        }

        // delete the user
        unset($this->users[$user]);
        $this->_save();
    }

    public function delgroup($group)
    {
        if (!checkSecurityToken()) return false;
        // group doesn't exist
        if (!$this->groups[$group]) {
            return;
        }

        // delete all users from group
        foreach ($this->groups[$group] as $user) {
            $idx = array_search($group, $this->users[$user]);
            if ($idx !== false) {
                unset($this->users[$user][$idx]);
                $this->users[$user] = array_values($this->users[$user]);
                if ($this->users[$user] === []) {
                    unset($this->users[$user]);
                }
            }
        }
        $this->_save();
    }

    public function add($user)
    {
        if (!checkSecurityToken()) return false;
        $grp = $_REQUEST['grp'];
        if (empty($user)) {
            msg($this->getLang('nouser'), -1);
            return;
        }
        if (empty($grp)) {
            msg($this->getLang('nogrp'), -1);
            return;
        }

        // get the groups as array
        $grp = str_replace(' ', '', $grp);
        $grps = explode(',', $grp);

        // append the groups to the user
        if ($this->users[$user]) {
            $this->users[$user] = array_merge($this->users[$user], $grps);
            $this->users[$user] = array_unique($this->users[$user]);
        } else {
            $this->users[$user] = $grps;
        }

        // save the changes
        $this->_save();
    }

    public function addgroup($group)
    {
        if (!checkSecurityToken()) return false;

        if (empty($group)) {
            msg($this->getLang('nogrp'), -1);
            return;
        }
        if (empty($_REQUEST['users'])) {
            msg($this->getLang('nouser'), -1);
            return;
        }

        // get the users as array
        $users = str_replace(' ', '', $_REQUEST['users']);
        $users = array_unique(explode(',', $users));

        // add new users to group
        foreach ($users as $user) {
            if ($user && (!isset($this->users[$user]) || !in_array($group, $this->users[$user]))) {
                $this->users[$user][] = $group;
            }
        }
        $this->_save();
    }


    public function _save()
    {
        global $auth;
        global $conf;
        foreach ($this->users as $u => $grps) {
            $cleanUser = $auth->cleanUser($u);
            if ($u != $cleanUser) {
                if (empty($cleanUser)) {
                    msg($this->getLang('usercharerr'), -1);
                    unset($this->users[$u]);
                    continue;
                }
                $this->users[ $cleanUser ] = $this->users[$u];
                unset($this->users[$u]);
            }

            $groupCount = count($this->users[$cleanUser]);
            for ($i = 0; $i < $groupCount; $i++) {
                $clean = $auth->cleanGroup($this->users[$cleanUser][$i]);

                if (empty($clean)) {
                    msg($this->getLang('grpcharerr'), -1);
                    unset($this->users[$cleanUser][$i]);
                } elseif ($clean != $this->users[$cleanUser][$i]) {
                    $this->users[$cleanUser][$i] = $clean;
                }
            }

            if (count($this->users[$cleanUser]) == 0) {
                unset($this->users[$cleanUser]);
            }
        }

        // determine the path to the data
        $userFile = DOKU_CONF . 'virtualgrp.json';

        // serialize it
        $content = json_encode($this->users, 2);

        // save it
        file_put_contents($userFile, $content);

        // update groups-array, since the users-array probably has changed.
        $this->groups = $this->translateUsers();
    }


    /**
     * load the users -> group connection
     */
    public function _load()
    {
        global $conf;
        // determein the path to the data
        $userFile = DOKU_CONF . 'virtualgrp.json';

        // if there is no file we hava no data ;-)
        if (!is_file($userFile)) {
            $this->users = [];
            return;
        }

        // read the file
        $content = file_get_contents($userFile);

        // if its empty we have no data also
        if (empty($content)) {
            $this->users = [];
            return;
        }

        $users = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        // check for invalid data
        if ($users === false) {
            $this->users = [];
            // Do NOT delete malformed configuration file here,
            // otherwise compat mode will restore possibly outdated permissions.
            return;
        }

        // place the users array
        $this->users = $users;
        $this->groups = $this->translateUsers();
    }

    /**
     * translate the users-Array (groups a user is in) to a group-array (users in a group) and sort the user lists
     */
    public function translateUsers()
    {
        $groups = [];

        foreach ($this->users as $user => $grps) {
            foreach ($grps as $grp) {
                $groups[$grp][] = $user;
            }
        }

        foreach ($groups as $group => $users) {
            sort($users);
            $groups[$group] = $users;
        }

        return $groups;
    }

    /**
     * output appropriate html
     */
    public function html()
    {
        global $ID;
        $form = new Doku_Form(['id' => 'vg', 'action' => wl($ID)]);
        $form->addHidden('cmd', $this->edit ? 'edit' : 'add');
        $form->addHidden('sectok', getSecurityToken());
        $form->addHidden('page', $this->getPluginName());
        $form->addHidden('do', 'admin');
        $form->startFieldset($this->getLang($this->edit ? 'edituser' : 'adduser'));
        if ($this->edit) {
            $form->addElement(form_makeField(
                'text',
                'user',
                $this->data['user'],
                $this->getLang('user'),
                '',
                '',
                ['disabled' => 'disabled']
            ));
            $form->addHidden('uid', $this->data['user']);
        } else {
            $form->addElement(form_makeField(
                'text',
                'uid',
                '',
                $this->getLang('user')
            ));
        }
        $form->addElement(form_makeField(
            'text',
            'grp',
            $this->edit ? implode(', ', $this->data['grp'])
                                                     : '',
            $this->getLang('grp')
        ));
        $form->addElement(form_makeButton(
            'submit',
            '',
            $this->getLang($this->edit ? 'change' : 'add')
        ));
        $form->printForm();


        echo '<table class="inline" id="vg__show">';
        echo '  <tr>';
        echo '    <th class="user">' . hsc($this->getLang('users')) . '</th>';
        echo '    <th class="grp">' . hsc($this->getLang('grps')) . '</th>';
        echo '    <th> </th>';
        echo '  </tr>';
        foreach ($this->users as $user => $grps) {
            $userdata = $this->_auth->getUserData($user);

            echo '  <tr>';
            echo '    <td>' . hsc($user) . (isset($userdata['name']) ? hsc(' (' . $userdata['name'] . ')') : '') . '</td>';
            echo '    <td>' . hsc(implode(', ', $grps)) . '</td>';
            echo '    <td class="act">';
            echo '      <a class="vg_edit" href="' . wl($ID, ['do' => 'admin', 'page' => $this->getPluginName(), 'cmd' => 'edit', 'uid' => $user, 'sectok' => getSecurityToken()]) . '">' . hsc($this->getLang('edit')) . '</a>';
            echo ' &bull; ';
            echo '      <a class="vg_del" href="' . wl($ID, ['do' => 'admin', 'page' => $this->getPluginName(), 'cmd' => 'del', 'uid' => $user, 'sectok' => getSecurityToken()]) . '">' . hsc($this->getLang('del')) . '</a>';
            echo '    </td>';
            echo '  </tr>';
        }

        echo '</table>';

        $form = new Doku_Form(['id' => 'vg', 'action' => wl($ID)]);
        $form->addHidden('cmd', $this->editgroup ? 'editgroup' : 'addgroup');
        $form->addHidden('sectok', getSecurityToken());
        $form->addHidden('page', $this->getPluginName());
        $form->addHidden('do', 'admin');
        if ($this->editgroup) {
            $form->startFieldset($this->getLang('editgroup'));
            $form->addElement(form_makeField(
                'text',
                'group',
                $this->data['group'],
                $this->getLang('grp'),
                '',
                '',
                ['disabled' => 'disabled']
            ));
            $form->addHidden('uid', $this->data['group']);
            $form->addElement(form_makeField('text', 'users', implode(', ', $this->data['users']), $this->getLang('users')));
        } else {
            $form->startFieldset($this->getLang('addgroup'));
            $form->addElement(form_makeField('text', 'uid', '', $this->getLang('grp')));
            $form->addElement(form_makeField('text', 'users', '', $this->getLang('users')));
        }
        $form->addElement(form_makeButton(
            'submit',
            '',
            $this->getLang($this->editgroup ? 'change' : 'add')
        ));
        $form->printForm();


        echo '<table class="inline" id="vg__show">';
        echo '  <tr>';
        echo '    <th class="grp">' . hsc($this->getLang('grps')) . '</th>';
        echo '    <th class="user">' . hsc($this->getLang('users')) . '</th>';
        echo '    <th class="act"> </th>';
        echo '  </tr>';
        foreach ($this->groups as $group => $users) {
            echo '  <tr>';
            echo '    <td>' . hsc($group) . '</td>';
            echo '    <td>' . hsc(implode(', ', $users)) . '</td>';
            echo '    <td class="act">';
            echo '      <a class="vg_edit" href="' . wl($ID, ['do' => 'admin', 'page' => $this->getPluginName(), 'cmd' => 'editgroup', 'uid' => $group, 'sectok' => getSecurityToken()]) . '">' . hsc($this->getLang('edit')) . '</a>';
            echo ' &bull; ';
            echo '      <a class="vg_del" href="' . wl($ID, ['do' => 'admin', 'page' => $this->getPluginName(), 'cmd' => 'delgroup', 'uid' => $group, 'sectok' => getSecurityToken()]) . '">' . hsc($this->getLang('del')) . '</a>';
            echo '    </td>';
            echo '  </tr>';
        }

        echo '</table>';
    }
}
