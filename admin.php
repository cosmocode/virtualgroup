<?php

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'admin.php');
require_once(DOKU_INC.'inc/common.php');

class admin_plugin_virtualgroup extends DokuWiki_Admin_Plugin {

    var $users;
    var $groups;
    var $_auth = null;        // auth object
    
    var $editgroup = false;
    var $edit = false;

    var $data = array();

    function admin_plugin_virtualgroup () {
        global $auth;

        $this->setupLocale();

        if (isset($auth)) {
      
           // we're good to go
          $this->_auth = & $auth;

        }

    }

    function getInfo(){
        return confToHash(dirname(__FILE__).'/plugin.info.txt');
    }

    function getMenuSort() {
      return 999;
    }

    /**
     * handle user request
     */
    function handle() {
        global $auth;
        $this->_load();

        $act  = $_REQUEST['cmd'];
        $uid  = $_REQUEST['uid'];
        switch ($act) {
            case 'del' :$this->del($uid);break;
            case 'edit':$this->edit($uid);break;
            case 'add' :$this->add($uid);break;
            case 'addgroup':$this->addgroup($uid);break;
            case 'editgroup':$this->editgroup($uid);break;
            case 'delgroup' :$this->delgroup($uid);break;
        }

    }

    function edit($user) {
        if (!checkSecurityToken()) return false;
        $grp = array();
        // on input change the data
        if (isset($_REQUEST['grp']) && isset($this->users[$user])) {

            $grp = $_REQUEST['grp'];

            // get the groups as array
            $grp = str_replace(' ','',$grp);
            $grps = array_unique(explode(',',$grp));
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

    function editgroup($group) {
        if (!checkSecurityToken()) return false;
        
        // on input change the data
        if (isset($_REQUEST['users']) && isset($this->groups[$group])) {

            // get the users as array
            $users = str_replace(' ','',$_REQUEST['users']);
            $users = array_unique(explode(',',$users));

            // delete removed users from group 
            foreach (array_diff($this->groups[$group],$users) as $user) {
                $idx = array_search($group,$this->users[$user]);
                if ($idx !== false) {
                    unset($this->users[$user][$idx]);
                    $this->users[$user]=array_values($this->users[$user]);
                    if (!count($this->users[$user])) {
                        unset($this->users[$user]);
                    }
                }
            }

            // add new users to group 
            foreach (array_diff($users,$this->groups[$group]) as $user) {
                if ($user && (!isset($this->users[$user]) || !in_array($group,$this->users[$user]))) {
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

    function del($user) {
        if (!checkSecurityToken()) return false;
        // user don't exist
        if (!$this->users[$user]) {
            return;
        }

        // delete the user
        unset($this->users[$user]);
        $this->_save();
    }

    function delgroup($group) {
        if (!checkSecurityToken()) return false;
        // group doesn't exist
        if (!$this->groups[$group]) {
            return;
        }

        // delete all users from group 
        foreach ($this->groups[$group] as $user) {
            $idx = array_search($group,$this->users[$user]);
            if ($idx !== false) {
                unset($this->users[$user][$idx]);
                $this->users[$user]=array_values($this->users[$user]);
                if (!count($this->users[$user])) {
                    unset($this->users[$user]);
                }
            }
        }
        $this->_save();
    }

function add($user) {
        if (!checkSecurityToken()) return false;
        $grp = $_REQUEST['grp'];
        if (empty($user)) {
            msg($this->getLang('nouser'),-1);
            return;
        }
        if (empty($grp)) {
            msg($this->getLang('nogrp'),-1);
            return;
        }

        // get the groups as array
        $grp = str_replace(' ','',$grp);
        $grps = explode(',',$grp);

        // append the groups to the user
        if ($this->users[$user]) {
            $this->users[$user] = array_merge($this->users[$user],$grps);
            $this->users[$user] = array_unique($this->users[$user]);
        } else {
            $this->users[$user] = $grps;
        }

        // save the changes
        $this->_save();

    }

    function addgroup($group) {
        if (!checkSecurityToken()) return false;

        if (empty($group)) {
            msg($this->getLang('nogrp'),-1);
            return;
        }
        if (empty($_REQUEST['users'])) {
            msg($this->getLang('nouser'),-1);
            return;
        }

        // get the users as array
        $users = str_replace(' ','',$_REQUEST['users']);
        $users = array_unique(explode(',',$users));

        // add new users to group 
        foreach ($users as $user) {
                if ($user && (!isset($this->users[$user]) || !in_array($group,$this->users[$user]))) {
                    $this->users[$user][] = $group;
                }
        }
        $this->_save();
        return;
    }


    function _save() {
        global $auth;
        global $conf;
        foreach ($this->users as $u => $grps) {
            $cleanUser = $auth->cleanUser($u);
            if ($u != $cleanUser) {
                if (empty($cleanUser)) {
                    msg($this->getLang('usercharerr'),-1);
                    unset($this->users[$u]);
                    continue;
                }
                $this->users[ $cleanUser ] = $this->users[$u];
                unset($this->users[$u]);
            }

            $groupCount = count($this->users[$cleanUser]);
            for ($i=0; $i<$groupCount; $i++) {
                $clean = $auth->cleanGroup($this->users[$cleanUser][$i]);

                if (empty($clean)) {
                    msg($this->getLang('grpcharerr'),-1);
                    unset($this->users[$cleanUser][$i]);
                } else {
                    if ($clean != $this->users[$cleanUser][$i]) {
                        $this->users[$cleanUser][$i] = $clean;
                    }
                }
            }

            if (count($this->users[$cleanUser]) == 0) {
                unset($this->users[$cleanUser]);
            }
        }

        // determein the path to the data
        $userFile = $conf['savedir'] . '/virtualgrp.php';

        // serialize it
        $content = serialize($this->users);

        // save it
        file_put_contents($userFile, $content);

        // update groups-array, since the users-array probably has changed.
        $this->groups = $this->translateUsers();
    }


    /**
     * load the users -> group connection
     */
    function _load() {
        global $conf;
        // determein the path to the data
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
        $this->groups = $this->translateUsers();
    }

    /**
     * translate the users-Array (groups a user is in) to a group-array (users in a group) and sort the user lists
     */
    function translateUsers() {
        $groups = array();

        foreach ($this->users as $user => $grps) {
            foreach ($grps as $grp) {
                $groups[$grp][]=$user;
            }
        }

        foreach ($groups as $group => $users) {
            sort($users);
            $groups[$group]=$users;
        }

        return $groups;
    }

    /**
     * output appropriate html
     */
    function html() {
        global $ID;
        $form = new Doku_Form(array('id' => 'vg', 'action' => wl($ID)));
        $form->addHidden('cmd', $this->edit?'edit':'add');
        $form->addHidden('sectok', getSecurityToken());
        $form->addHidden('page', $this->getPluginName());
        $form->addHidden('do', 'admin');
        $form->startFieldset($this->getLang($this->edit ? 'edituser' : 'adduser'));
        if ($this->edit) {
            $form->addElement(form_makeField('text', 'user', $this->data['user'],
                                             $this->getLang('user'), '', '',
                                             array('disabled' => 'disabled')));
            $form->addHidden('uid', $this->data['user']);
        } else {
            $form->addElement(form_makeField('text', 'uid', '',
                                             $this->getLang('user')));
        }
        $form->addElement(form_makeField('text', 'grp',
                                         $this->edit ? implode(', ',$this->data['grp'])
                                                     : '',
                                         $this->getLang('grp')));
        $form->addElement(form_makeButton('submit', '',
                                          $this->getLang($this->edit?'change':'add')));
        $form->printForm();


        ptln('<table class="inline" id="vg__show">');
        ptln('  <tr>');
        ptln('    <th class="user">'.hsc($this->getLang('users')).'</th>');
        ptln('    <th class="grp">'.hsc($this->getLang('grps')).'</th>');
        ptln('    <th> </th>');
        ptln('  </tr>');
        foreach ($this->users as $user => $grps) {
            $userdata=$this->_auth->getUserData($user);

            ptln('  <tr>');
            ptln('    <td>'.hsc($user).(isset($userdata['name'])?hsc(' ('.$userdata['name'].')'):'').'</td>');
            ptln('    <td>'.hsc(implode(', ',$grps)).'</td>');
            ptln('    <td class="act">');
            ptln('      <a class="vg_edit" href="'.wl($ID,array('do'=>'admin','page'=>$this->getPluginName(),'cmd'=>'edit' ,'uid'=>$user, 'sectok'=>getSecurityToken())).'">'.hsc($this->getLang('edit')).'</a>');
            ptln(' &bull; ');
            ptln('      <a class="vg_del" href="'.wl($ID,array('do'=>'admin','page'=>$this->getPluginName(),'cmd'=>'del','uid'=>$user, 'sectok'=>getSecurityToken())).'">'.hsc($this->getLang('del')).'</a>');
            ptln('    </td>');
            ptln('  </tr>');
        }

        ptln('</table>');

        $form = new Doku_Form(array('id' => 'vg', 'action' => wl($ID)));
        $form->addHidden('cmd', $this->editgroup?'editgroup':'addgroup');
        $form->addHidden('sectok', getSecurityToken());
        $form->addHidden('page', $this->getPluginName());
        $form->addHidden('do', 'admin');
        if ($this->editgroup) {
            $form->startFieldset($this->getLang('editgroup'));
            $form->addElement(form_makeField('text', 'group', $this->data['group'], 
                                             $this->getLang('grp'), '', '',
                                             array('disabled' => 'disabled')));
            $form->addHidden('uid', $this->data['group']);
            $form->addElement(form_makeField('text', 'users',implode(', ',$this->data['users']),$this->getLang('users')));
        } else {
            $form->startFieldset($this->getLang('addgroup'));
            $form->addElement(form_makeField('text', 'uid','', $this->getLang('grp')));
            $form->addElement(form_makeField('text', 'users', '', $this->getLang('users')));
        }
        $form->addElement(form_makeButton('submit', '',
                                          $this->getLang($this->editgroup?'change':'add')));
        $form->printForm();


        ptln('<table class="inline" id="vg__show">');
        ptln('  <tr>');
        ptln('    <th class="grp">'.hsc($this->getLang('grps')).'</th>');
        ptln('    <th class="user">'.hsc($this->getLang('users')).'</th>');
        ptln('    <th class="act"> </th>');
        ptln('  </tr>');
        foreach ($this->groups as $group => $users) {
            ptln('  <tr>');
            ptln('    <td>'.hsc($group).'</td>');
            ptln('    <td>'.hsc(implode(', ',$users)).'</td>');
            ptln('    <td class="act">');
            ptln('      <a class="vg_edit" href="'.wl($ID,array('do'=>'admin','page'=>$this->getPluginName(),'cmd'=>'editgroup' ,'uid'=>$group, 'sectok'=>getSecurityToken())).'">'.hsc($this->getLang('edit')).'</a>');
            ptln(' &bull; ');
            ptln('      <a class="vg_del" href="'.wl($ID,array('do'=>'admin','page'=>$this->getPluginName(),'cmd'=>'delgroup','uid'=>$group, 'sectok'=>getSecurityToken())).'">'.hsc($this->getLang('del')).'</a>');
            ptln('    </td>');
            ptln('  </tr>');
        }
    
        ptln('</table>');
    }
}
