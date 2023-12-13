<?php

namespace dokuwiki\plugin\virtualgroup;

use dokuwiki\Logger;

class VirtualGroups
{
    const CONFIG_FILE = DOKU_CONF . 'virtualgrp.conf';

    /**
     * Get the configuration by user
     *
     * @return array [user => [group1, group2, ...], ...]
     */
    public function getUserStructure()
    {
        $config = $this->loadConfig();
        ksort($config);
        return $config;
    }

    /**
     * Get the groups for a user
     *
     * @param string $user
     * @return string[]
     */
    public function getUserGroups($user) {
        $config = $this->loadConfig();
        if (isset($config[$user])) {
            return $config[$user];
        }
        return [];
    }

    /**
     * Get all users in a group
     *
     * @param string $group
     * @return string[]
     */
    public function getGroupUsers($group) {
        $config = $this->loadConfig();
        $users = [];
        foreach ($config as $user => $groups) {
            if (in_array($group, $groups)) {
                $users[] = $user;
            }
        }
        return $users;
    }


    /**
     * Get the configuration by group
     *
     * @return array [group => [user1, user2, ...], ...]
     */
    public function getGroupStructure()
    {
        $config = $this->loadConfig();
        $groups = [];
        foreach ($config as $user => $usergroups) {
            foreach ($usergroups as $group) {
                if (!isset($groups[$group])) {
                    $groups[$group] = [];
                }
                $groups[$group][] = $user;
            }
        }
        ksort($groups);
        return $groups;
    }

    // region individual user/group management

    /**
     * Remove a user from all groups
     *
     * @param string $user
     * @return void
     */
    public function removeUser($user)
    {
        $config = $this->loadConfig();
        if (isset($config[$user])) unset($config[$user]);
        $this->saveConfig($config);
    }

    /**
     * Add a user to one or more groups
     *
     * @param string $user
     * @param string[] $groups
     * @return void
     */
    public function addGroupsToUser($user, $groups)
    {
        $config = $this->loadConfig();
        if (!isset($config[$user])) {
            $config[$user] = [];
        }
        $config[$user] = array_filter(array_unique(array_merge($config[$user], $groups)));
        $this->saveConfig($config);
    }

    /**
     * Set the groups for a user
     *
     * @param string $user
     * @param string[] $groups
     * @return void
     */
    public function setUserGroups($user, $groups)
    {
        $config = $this->loadConfig();
        $config[$user] = array_filter($groups);
        if($config[$user] === []) {
            unset($config[$user]);
        }
        $this->saveConfig($config);
    }

    /**
     * Remove a group from all users
     *
     * @param string $group
     * @return void
     */
    public function removeGroup($group)
    {
        $config = $this->loadConfig();
        foreach ($config as $user => $groups) {
            if (($key = array_search($group, $groups)) !== false) {
                unset($config[$user][$key]);
            }
        }
        $this->saveConfig($config);
    }

    /**
     * Add one or more users to a group
     *
     * @param string $group
     * @param string[] $users
     * @return void
     */
    public function addUsersToGroup($group, $users)
    {
        $config = $this->loadConfig();
        foreach ($users as $user) {
            if (!isset($config[$user])) {
                $config[$user] = [];
            }
            $config[$user][] = $group;
            $config[$user] = array_filter(array_unique($config[$user]));
        }
        $this->saveConfig($config);
    }

    public function setGroupUsers($group, $users)
    {
        $config = $this->loadConfig();
        foreach ($users as $user) {
            if (!isset($config[$user])) {
                $config[$user] = [];
            }
            $config[$user][] = $group;
            $config[$user] = array_filter(array_unique($config[$user]));
            if($config[$user] === []) {
                unset($config[$user]);
            }
        }
        $this->saveConfig($config);
    }

    // endregion

    // region file management

    /**
     * Load the configuration
     *
     * @return array [user => [group1, group2, ...], ...]
     */
    protected function loadConfig()
    {
        if (!file_exists(self::CONFIG_FILE)) return $this->loadLegacyConfig();

        $config = [];
        $raw = linesToHash(file(self::CONFIG_FILE));
        foreach ($raw as $key => $value) {
            $user = rawurldecode($key);
            $groups = array_map(function ($group) {
                return rawurldecode(trim($group));
            }, explode(',', $value));
            $config[$user] = $groups;
        }

        return $config;
    }

    /**
     * Save the configuration
     *
     * @param array $config [user => [group1, group2, ...], ...]
     * @return boolean
     */
    protected function saveConfig($config)
    {
        $lines = [];
        foreach ($config as $user => $groups) {
            $lines[] = auth_nameencode($user) . "\t" . implode(',', array_map(function ($group) {
                    return auth_nameencode($group);
                }, $groups));
        }

        # FIXME add comment
        $ok = file_put_contents(self::CONFIG_FILE, join("\n", $lines));
        if($ok === false) {
            msg('Failed to save virtual group configuration', -1);
        }
        return (bool) $ok;
    }

    /**
     * Load the legacy configuration
     *
     * @deprecated
     * @return array [user => [group1, group2, ...], ...]
     */
    protected function loadLegacyConfig()
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

        // save in new format
        $ok = $this->saveConfig($users);
        if($ok) {
            @unlink($userFile);
        }

        return $users;
    }

    // endregion
}
