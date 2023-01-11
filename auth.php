<?php
/**
 * DokuWiki Plugin authimap (Auth Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class auth_plugin_authimap extends DokuWiki_Auth_Plugin {

    /** @var class auth plain */
    protected $authplain = null;

    /**
     * Constructor.
     */
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
        
        $this->$authplain = new auth_plugin_authplain();
        
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

        // check at imap server
        $imap_login = @imap_open($server, $login, $pass, OP_READONLY);
        if($imap_login) {
            imap_close($imap_login);
            return true;
        } else {
            return $this->$authplain->checkPass($user, $pass);
        }
        return false;
    }

    /**
     * Return user info
     *
     * Returns info about the given user needs to contain
     * at least these fields:
     *
     * name string  full name of the user
     * mail string  email addres of the user
     * grps array   list of groups the user is in
     *
     * @param   string $user the user name
     * @return  array containing user data or false
     */
    public function getUserData($user, $requireGroups = false) {
        global $conf;
        $user   = $this->cleanUser($user);
        $domain = $this->getConf('domain');
        
        $userinfo = $this->$authplain->getUserData($user);
        
        if ($userinfo === false) {
            return array(
                'name' => utf8_ucwords(strtr($user, '_-.', '   ')),
                'mail' => "$user@$domain",
                'grps' => array($conf['defaultgroup'])
            );
        } else {
            return array (
                'name' => $userinfo['name'],
                'mail' => $userinfo['mail'],
                'grps' => $userinfo['grps']
            );
        }
    }
    
    public function createUser($user, $pwd, $name, $mail, $grps = null) {
        return $this->$authplain->createUser($user, $pwd, $name, $mail, $grps);
    }
    
    public function modifyUser($user, $changes) {
        return $this->$authplain->modifyUser($user, $changes);
    }
    
    public function deleteUsers($users) {
        return $this->$authplain->deleteUsers($users);
    }
    
    public function getUserCount($filter = array()) {
        return $this->$authplain->getUserCount($filter);
    }
    
    public function retrieveUsers($start = 0, $limit = 0, $filter = array())
    {
        return $this->$authplain->retrieveUsers($start, $limit, $filter);
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
