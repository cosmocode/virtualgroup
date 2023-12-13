<?php

use dokuwiki\Extension\AdminPlugin;
use dokuwiki\plugin\virtualgroup\VirtualGroups;

/**
 * DokuWiki Plugin virtualgroup (Admin Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 */
class admin_plugin_virtualgroup extends AdminPlugin
{
    public $users;
    public $groups;
    public $_auth;        // auth object

    public $editgroup = false;
    public $edit = false;

    public $data = [];

    /** @var VirtualGroups */
    protected $virtualGroups;

    public function __construct()
    {
        $this->virtualGroups = new VirtualGroups();
    }

    // region handlers

    /** @inheritdoc */
    public function handle()
    {
        global $INPUT;
        global $auth;

        if ($INPUT->has('addusergroups')) {
            $this->addUserGroups($INPUT->str('user'), $INPUT->str('groups'));
        } elseif ($INPUT->has('addgroupusers')) {
            $this->addGroupUsers($INPUT->str('group'), $INPUT->str('users'));
        } elseif ($INPUT->has('deleteuser')) {
            $this->deleteUser($INPUT->str('user'));
        } elseif ($INPUT->has('deletegroup')) {
            $this->deleteGroup($INPUT->str('group'));
        } elseif ($INPUT->has('editusergroups')) {
            $this->editUserGroups($INPUT->str('user'), $INPUT->str('groups'));
        } elseif ($INPUT->has('editgroupusers')) {
            $this->editGroupUsers($INPUT->str('group'), $INPUT->str('users'));
        }

        // remove all input to avoid re-submitting on reload
        $INPUT->remove('user');
        $INPUT->remove('users');
        $INPUT->remove('group');
        $INPUT->remove('groups');

        // load user data if requested
        if ($INPUT->has('loaduser')) {
            $INPUT->set('user', $auth->cleanUser($INPUT->str('loaduser')));
            $INPUT->set('groups', implode(',',
                    $this->virtualGroups->getUserGroups($auth->cleanUser($INPUT->str('loaduser')))
                )
            );
        }

        // load group data if requested
        if ($INPUT->has('loadgroup')) {
            $INPUT->set('group', $auth->cleanGroup($INPUT->str('loadgroup')));
            $INPUT->set('users', implode(',',
                    $this->virtualGroups->getGroupUsers($auth->cleanGroup($INPUT->str('loadgroup')))
                )
            );
        }

    }

    /**
     * Add groups to a user
     *
     * @param string $user user name
     * @param string $groups comma separated list of groups
     * @return void
     */
    public function addUserGroups($user, $groups)
    {
        global $auth;

        if (!checkSecurityToken()) return;
        $user = $auth->cleanUser($user);
        $groups = array_unique(array_map(
            function ($group) use ($auth) {
                return $auth->cleanGroup($group);
            },
            explode(',', $groups)
        ));

        if ($user && $groups) {
            $this->virtualGroups->addGroupsToUser($user, $groups);
        }
    }

    /**
     * Add users to a group
     *
     * @param string $group group name
     * @param string $users comma separated list of users
     * @return void
     */
    public function addGroupUsers($group, $users)
    {
        global $auth;

        if (!checkSecurityToken()) return;
        $group = $auth->cleanGroup($group);
        $users = array_unique(array_map(
            function ($user) use ($auth) {
                return $auth->cleanUser($user);
            },
            explode(',', $users)
        ));

        if ($group && $users) {
            $this->virtualGroups->addUsersToGroup($group, $users);
        }
    }

    /**
     * Delete a user
     *
     * @param string $user user name
     * @return void
     */
    public function deleteUser($user)
    {
        global $auth;

        if (!checkSecurityToken()) return;
        $user = $auth->cleanUser($user);

        if ($user) {
            $this->virtualGroups->removeUser($user);
        }
    }

    /**
     * Delete a group
     *
     * @param string $group group name
     * @return void
     */
    public function deleteGroup($group)
    {
        global $auth;

        if (!checkSecurityToken()) return;
        $group = $auth->cleanGroup($group);

        if ($group) {
            $this->virtualGroups->removeGroup($group);
        }
    }

    /**
     * Set the groups of a user
     *
     * @param string $user user name
     * @param string $groups comma separated list of groups
     * @return void
     */
    public function editUserGroups($user, $groups)
    {
        global $auth;

        if (!checkSecurityToken()) return;
        $user = $auth->cleanUser($user);
        $groups = array_unique(array_map(
            function ($group) use ($auth) {
                return $auth->cleanGroup($group);
            },
            explode(',', $groups)
        ));

        if ($user && $groups) {
            $this->virtualGroups->setUserGroups($user, $groups);
        }
    }

    /**
     * Set the users of a group
     *
     * @param string $group group name
     * @param string $users comma separated list of users
     * @return void
     */
    public function editGroupUsers($group, $users)
    {
        global $auth;

        if (!checkSecurityToken()) return;
        $group = $auth->cleanGroup($group);
        $users = array_unique(array_map(
            function ($user) use ($auth) {
                return $auth->cleanUser($user);
            },
            explode(',', $users)
        ));

        if ($group && $users) {
            $this->virtualGroups->setGroupUsers($group, $users);
        }
    }

    // endregion

    // region HTML output

    /** @inheritdoc */
    public function html()
    {
        global $INPUT;

        $tab = $INPUT->str('tab', 'byuser');


        $this->tabNavigation($tab);
        if ($tab == 'bygroup') {
            $this->listByGroup();
        } else {
            $this->listByUser();
        }
    }

    /**
     * Print the tab navigation
     *
     * @param string $tab currently active tab
     * @return void
     */
    protected function tabNavigation($tab)
    {
        global $ID;

        echo '<ul class="tabs">';
        echo sprintf(
            '<li class="%s"><a href="%s">%s</a></li>',
            $tab == 'byuser' ? 'active' : '',
            wl($ID, ['do' => 'admin', 'page' => $this->getPluginName(), 'tab' => 'byuser']),
            $this->getLang('byuser')
        );
        echo sprintf(
            '<li class="%s"><a href="%s">%s</a></li>',
            $tab == 'byuser' ? 'active' : '',
            wl($ID, ['do' => 'admin', 'page' => $this->getPluginName(), 'tab' => 'bygroup']),
            $this->getLang('bygroup')
        );
        echo '</ul>';

    }

    /**
     * Print the by user tab
     *
     * @return void
     */
    protected function listByUser()
    {
        global $INPUT;
        global $ID;

        if ($INPUT->has('loaduser')) {
            echo $this->formEditUserGroups();
        } else {
            echo $this->formAddUserGroups();
        }

        echo '<table class="inline">';
        echo '  <tr>';
        echo '    <th class="user">' . hsc($this->getLang('user')) . '</th>';
        echo '    <th class="grp">' . hsc($this->getLang('grps')) . '</th>';
        echo '    <th> </th>';
        echo '  </tr>';

        foreach ($this->virtualGroups->getUserStructure() as $user => $groups) {
            echo '<tr>';
            echo '  <td>' . hsc($user) . '</td>';
            echo '  <td>' . hsc(implode(', ', $groups)) . '</td>';
            echo '  <td class="act">';
            echo $this->buttonDeleteUser($user);
            echo '<a class="button" href="' . wl($ID, [
                    'do' => 'admin',
                    'page' => 'virtualgroup',
                    'tab' => 'byuser',
                    'loaduser' => $user
                ]) . '">' . hsc($this->getLang('edit')) . '</a>';
            echo '  </td>';
            echo '</tr>';
        }
        echo '</table>';
    }

    /**
     * Print the by group tab
     *
     * @return void
     */
    protected function listByGroup()
    {
        global $INPUT;
        global $ID;

        if ($INPUT->has('loadgroup')) {
            echo $this->formEditGroupUsers();
        } else {
            echo $this->formAddGroupUsers();
        }

        echo '<table class="inline">';
        echo '  <tr>';
        echo '    <th class="grp">' . hsc($this->getLang('grp')) . '</th>';
        echo '    <th class="user">' . hsc($this->getLang('users')) . '</th>';
        echo '    <th class="act"> </th>';
        echo '  </tr>';

        foreach ($this->virtualGroups->getGroupStructure() as $group => $users) {
            echo '<tr>';
            echo '  <td>' . hsc($group) . '</td>';
            echo '  <td>' . hsc(implode(', ', $users)) . '</td>';
            echo '  <td class="act">';
            echo $this->buttonDeleteGroup($group);
            echo '<a class="button" href="' . wl($ID, [
                    'do' => 'admin',
                    'page' => 'virtualgroup',
                    'tab' => 'bygroup',
                    'loadgroup' => $group
                ]) . '">' . hsc($this->getLang('edit')) . '</a>';
            echo '  </td>';
            echo '</tr>';
        }
        echo '</table>';
    }

    /**
     * Return the form to add groups to a user
     *
     * @return string
     */
    protected function formAddUserGroups()
    {
        global $ID;
        $form = new dokuwiki\Form\Form(
            ['action' => wl($ID, ['do' => 'admin', 'page' => 'virtualgroup', 'tab' => 'byuser'], false, '&')]
        );
        $form->addFieldsetOpen($this->getLang('addUserGroups'));
        $form->addTextInput('user', $this->getLang('user'));
        $form->addTextInput('groups', $this->getLang('grps'));
        $form->addButton('addusergroups', $this->getLang('add'))->attr('type', 'submit');
        $form->addFieldsetClose();
        return $form->toHTML();
    }

    /**
     * Return the form to edit the groups of a user
     *
     * @return string
     */
    protected function formEditUserGroups()
    {
        global $ID;
        $form = new dokuwiki\Form\Form(
            ['action' => wl($ID, ['do' => 'admin', 'page' => 'virtualgroup', 'tab' => 'byuser'], false, '&')]
        );
        $form->addFieldsetOpen($this->getLang('editUserGroups'));
        $form->addTextInput('user', $this->getLang('user'))->attr('readonly', 'readonly');
        $form->addTextInput('groups', $this->getLang('grps'));
        $form->addButton('editusergroups', $this->getLang('change'))->attr('type', 'submit');
        $form->addFieldsetClose();
        return $form->toHTML();
    }

    /**
     * Return the form to delete a user
     *
     * @return string
     */
    protected function buttonDeleteUser($user)
    {
        global $ID;
        $form = new dokuwiki\Form\Form(
            ['action' => wl($ID, ['do' => 'admin', 'page' => 'virtualgroup', 'tab' => 'byuser'], false, '&')]
        );
        $form->setHiddenField('user', $user);
        $form->addButton('deleteuser', $this->getLang('del'))->attr('type', 'submit');
        return $form->toHTML();
    }

    /**
     * Return the form to add users to a group
     *
     * @return string
     */
    protected function formAddGroupUsers()
    {
        global $ID;
        $form = new dokuwiki\Form\Form(
            ['action' => wl($ID, ['do' => 'admin', 'page' => 'virtualgroup', 'tab' => 'bygroup'], false, '&')]
        );
        $form->addFieldsetOpen($this->getLang('addGroupUsers'));
        $form->addTextInput('group', $this->getLang('grp'));
        $form->addTextInput('users', $this->getLang('users'));
        $form->addButton('addgroupusers', $this->getLang('add'))->attr('type', 'submit');
        $form->addFieldsetClose();
        return $form->toHTML();
    }

    /**
     * Return the form to edit the users of a group
     *
     * @return string
     */
    protected function formEditGroupUsers()
    {
        global $ID;
        $form = new dokuwiki\Form\Form(
            ['action' => wl($ID, ['do' => 'admin', 'page' => 'virtualgroup', 'tab' => 'bygroup'], false, '&')]
        );
        $form->addFieldsetOpen($this->getLang('editGroupUsers'));
        $form->addTextInput('group', $this->getLang('grp'))->attr('readonly', 'readonly');
        $form->addTextInput('users', $this->getLang('users'));
        $form->addButton('editgroupusers', $this->getLang('change'))->attr('type', 'submit');
        $form->addFieldsetClose();
        return $form->toHTML();
    }

    /**
     * Return the form to delete a group
     *
     * @return string
     */
    protected function buttonDeleteGroup($group)
    {
        global $ID;
        $form = new dokuwiki\Form\Form(
            ['action' => wl($ID, ['do' => 'admin', 'page' => 'virtualgroup', 'tab' => 'bygroup'], false, '&')]
        );
        $form->setHiddenField('group', $group);
        $form->addButton('deletegroup', $this->getLang('del'))->attr('type', 'submit');
        return $form->toHTML();
    }

    // endregion
}
