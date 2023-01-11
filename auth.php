<?php
/**
 * DokuWiki Plugin authimap (Auth Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class auth_plugin_authimap extends auth_plugin_authplain {

    // region standard auth methods

    /** @inheritDoc */
    public function __construct() {
        parent::__construct(); // for compatibility

        if(!function_exists('imap_open')) {
            msg('PHP IMAP extension not available, IMAP auth not available.', -1);
            return;
        }

        if(!$this->getConf('server')) {
            msg('IMAP auth is missing server configuration', -1);
            return;
        }

        if(!$this->getConf('domain')) {
            msg('IMAP auth is missing domain configuration', -1);
            return;
        }

        $this->cando['addUser']      = true; // can Users be created?
        $this->cando['delUser']      = true; // can Users be deleted?
        $this->cando['modLogin']     = true; // can login names be changed?
        $this->cando['modPass']      = true; // can passwords be changed?
        $this->cando['modName']      = true; // can real names be changed?
        $this->cando['modMail']      = true; // can emails be changed?
        $this->cando['modGroups']    = true; // can groups be changed?
        $this->cando['getUsers']     = true; // can a (filtered) list of users be retrieved?
        $this->cando['getUserCount'] = true; // can the number of users be retrieved?
        $this->cando['getGroups']    = true; // can a list of available groups be retrieved?
        $this->cando['external']     = false; // does the module do external auth checking?
        $this->cando['logout']       = true; // can the user logout again? (eg. not possible with HTTP auth)
        
        // FIXME intialize your auth system and set success to true, if successful
        $this->success = true;
    }

    /**
     * Check user+password
     *
     * May be ommited if trustExternal is used.
     *
     * @param   string $user the user name
     * @param   string $pass the clear text password
     * @return  bool
     */
    public function checkPass($user, $pass) {
        $user   = $this->cleanUser($user);
        $domain = $this->getConf('domain');
        $server = $this->getConf('server');

        // some servers want the local part, others want the full address as username
        if($this->getConf('usedomain')) {
            $login = "$user@$domain";
        } else {
            $login = $user;
        }
        
        $userinfo = $this->getUserData($user);
        if ($userinfo === false) return false;

        // check at imap server
        $imap_login = @imap_open($server, $login, $pass, OP_READONLY);
        if($imap_login) {
            imap_close($imap_login);
            return true;
        } else {
            return parent::checkPass($user, $pass);
        }
        return false;
    }
    
    public function getUserData($user, $requireGroups = false) {
        return parent::getUserData($this->cleanUser($user), $requireGroups);
    }
    
    /**
     * Enhance function to check against duplicate emails
     *
     * @inheritdoc
     */
    public function createUser($user, $pwd, $name, $mail, $grps = null)
    {
        if ($this->getUserByEmail($mail)) {
            msg($this->getLang('emailduplicate'), -1);
            return false;
        }

        return parent::createUser($user, $pwd, $name, $mail, $grps);
    }
    
    /**
     * Enhance function to check against duplicate emails
     *
     * @inheritdoc
     */
    public function modifyUser($user, $changes)
    {
        global $conf;

        if (isset($changes['mail'])) {
            $found = $this->getUserByEmail($changes['mail']);
            if ($found && $found != $user) {
                msg($this->getLang('emailduplicate'), -1);
                return false;
            }
        }

        return parent::modifyUser($user, $changes);
    }
    
    // endregion
    
    /**
     * Find a user by email address
     *
     * @param $mail
     * @return bool|string
     */
    public function getUserByEmail($mail)
    {
        if ($this->users === null) {
            parent::loadUserData();
        }
        $mail = strtolower($mail);

        foreach ($this->users as $user => $userinfo) {
            if (strtolower($userinfo['mail']) == $mail) return $user;
        }

        return false;
    }

    /**
     * Fall back to plain auth strings
     *
     * @inheritdoc
     */
    public function getLang($id)
    {
        $result = parent::getLang($id);
        if ($result) return $result;

        $parent = new auth_plugin_authplain();
        return $parent->getLang($id);
    }

    /**
     * Return case sensitivity of the backend
     *
     * When your backend is caseinsensitive (eg. you can login with USER and
     * user) then you need to overwrite this method and return false
     *
     * @return bool
     */
    public function isCaseSensitive() {
        return false;
    }

    /**
     * Sanitize a given username
     *
     * This function is applied to any user name that is given to
     * the backend and should also be applied to any user name within
     * the backend before returning it somewhere.
     *
     * This should be used to enforce username restrictions.
     *
     * @param string $user username
     * @return string the cleaned username
     */
    public function cleanUser($user) {
        list($local) = explode('@', $user); // we only use the local part
        return strtolower($local);
    }

}

// vim:ts=4:sw=4:et:
