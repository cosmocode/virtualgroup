<?php

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'admin.php');
require_once(DOKU_INC.'inc/common.php');

class admin_plugin_virtualgroup extends DokuWiki_Admin_Plugin {

    var $users;
    var $edit = false;

    var $data = array();

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


    function _save() {
        // clean user and group names
        global $auth;
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
        $userFile = DOKU_INC . 'data/virtualgrp.php';

        // serialize it
        $content = serialize($this->users);

        // save it
        file_put_contents($userFile, $content);
    }


    /**
     * load the users -> group connection
     */
    function _load() {
        // determein the path to the data
        $userFile = DOKU_INC . 'data/virtualgrp.php';

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
    }

    /**
     * output appropriate html
     */
    function html() {
        global $ID;
        ptln('<form action="'.wl($ID).'" method="post">');
        ptln('<input type="hidden" name="cmd" value="'. ($this->edit?'edit':'add').'" />');
        ptln('<input type="hidden" name="sectok" value="'.getSecurityToken().'" />');
        ptln('<input type="hidden" name="page" value="'.$this->getPluginName().'" />');
        ptln('<input type="hidden" name="do" value="admin" />');

        ptln('  <fieldset class="vg">');
        ptln('<p>');
        if ($this->edit) {
            ptln('    <legend>'.hsc($this->getLang('edituser')).'</legend>');
        } else {
            ptln('    <legend>'.hsc($this->getLang('adduser')).'</legend>');
        }
        ptln('    <label for="vg__user">'.hsc($this->getLang('user')).'</label>');
        if ($this->edit) {
            ptln('    <input type="text" name="user" value="'.hsc($this->data['user']).'" disabled="disabled" />');
            ptln('    <input type="hidden" name="uid" value="'.hsc($this->data['user']).'" />');
        } else {
            ptln('    <input type="text" id="vg__user" name="uid" />');
        }
        ptln('</p><p>');
        ptln('    <label for="vg__grp">'.hsc($this->getLang('grp')).'</label>');
        if ($this->edit) {
            ptln('    <input type="text" id="vg__grp" name="grp" value="'.hsc(implode(', ',$this->data['grp'])).'" />');
        } else {
            ptln('    <input type="text" id="vg__grp" name="grp" />');
        }
        ptln('</p>');
        if ($this->edit) {
            ptln('    <input type="submit" value="'.hsc($this->getLang('change')).'" class="send" />');
        } else {
            ptln('    <input type="submit" value="'.hsc($this->getLang('add')).'" class="send" />');
        }
        ptln('  </fieldset>');
        ptln('</form>');

        ptln('<table class="inline" id="vg__show">');
        ptln('  <tr>');
        ptln('    <th class="user">'.hsc($this->getLang('users')).'</th>');
        ptln('    <th class="grp">'.hsc($this->getLang('grps')).'</th>');
        ptln('    <th class="act"> </th>');
        ptln('  </tr>');
        foreach ($this->users as $user => $grps) {
            ptln('  <tr>');
            ptln('    <td>'.hsc($user).'</td>');
            ptln('    <td>'.hsc(implode(', ',$grps)).'</td>');
            ptln('    <td class="act">');
            ptln('      <a href="'.wl($ID,array('do'=>'admin','page'=>$this->getPluginName(),'cmd'=>'edit' ,'uid'=>$user, 'sectok'=>getSecurityToken())).'"><img src="lib/plugins/virtualgroup/images/user_edit.png"> '.hsc($this->getLang('edit')).'</a>');
            ptln(' &bull; ');
            ptln('      <a href="'.wl($ID,array('do'=>'admin','page'=>$this->getPluginName(),'cmd'=>'del','uid'=>$user, 'sectok'=>getSecurityToken())).'"><img src="lib/plugins/virtualgroup/images/user_delete.png"> '.hsc($this->getLang('del')).'</a>');
            ptln('    </td>');
            ptln('  </tr>');
        }

        ptln('</table>');
    }

}
