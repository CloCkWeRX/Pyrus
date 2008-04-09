<?php
/**
 * PEAR2_Pyrus_Installer_Role
 *
 * PHP version 5
 * 
 * @category  PEAR2
 * @package   PEAR2_Pyrus
 * @author    Greg Beaver <cellog@php.net>
 * @copyright 2008 The PEAR Group
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @version   SVN: $Id$
 * @link      http://svn.pear.php.net/wsvn/PEARSVN/Pyrus/
 */

/**
 * Base class for installation roles for files.
 *
 * @category  PEAR2
 * @package   PEAR2_Pyrus
 * @author    Greg Beaver <cellog@php.net>
 * @copyright 2008 The PEAR Group
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @link      http://svn.pear.php.net/wsvn/PEARSVN/Pyrus/
 */
class PEAR2_Pyrus_Installer_Role
{
    static private $_roles;
    /**
     * Set up any additional configuration variables that file roles require
     *
     * Never call this directly, it is called by the PEAR_Config constructor
     * @param PEAR2_Pyrus_Config
     * @access private
     * @static
     */
    public static function initializeConfig(PEAR2_Pyrus_Config $config)
    {
        if (!isset(self::$_roles)) {
            self::registerRoles();
        }
        foreach (self::$_roles as $class => $info) {
            if (!$info['config_vars']) {
                continue;
            }
            $config->addConfigValue($info['config_vars']);
        }
    }

    /**
     * @param PEAR2_Pyrus_PackageFile_v2
     * @param string role name
     * @param PEAR2_Pyrus_Config
     * @return PEAR2_Pyrus_Installer_Role_Common
     * @static
     */
    static function factory($pkg, $role)
    {
        if (!isset(self::$_roles)) {
            self::registerRoles();
        }
        if (!in_array($role, self::getValidRoles($pkg->getPackageType()))) {
            return $a;
        }
        $a = 'PEAR2_Pyrus_Installer_Role_' . ucfirst($role);
        return new $a(PEAR2_Pyrus_Config::current());
    }

    /**
     * Get a list of file roles that are valid for the particular release type.
     *
     * For instance, src files serve no purpose in regular php releases.
     * @param string
     * @param bool clear cache
     * @return array
     * @static
     */
    static function getValidRoles($release, $clear = false)
    {
        if (!isset(self::$_roles)) {
            self::registerRoles();
        }
        static $ret = array();
        if ($clear) {
            $ret = array();
        }
        if (isset($ret[$release])) {
            return $ret[$release];
        }
        $ret[$release] = array();
        foreach (self::$_roles as $role => $okreleases) {
            if (in_array($release, $okreleases['releasetypes'])) {
                $ret[$release][] = strtolower(str_replace('PEAR2_Pyrus_Installer_Role_', '', $role));
            }
        }
        return $ret[$release];
    }

    /**
     * Get a list of roles that require their files to be installed
     *
     * Most roles must be installed, but src and package roles, for instance
     * are pseudo-roles.  src files are compiled into a new extension.  Package
     * roles are actually fully bundled releases of a package
     * @param bool clear cache
     * @return array
     * @static
     */
    static function getInstallableRoles($clear = false)
    {
        if (!isset(self::$_roles)) {
            self::registerRoles();
        }
        static $ret;
        if ($clear) {
            unset($ret);
        }
        if (!isset($ret)) {
            $ret = array();
            foreach (self::$_roles as $role => $okreleases) {
                if ($okreleases['installable']) {
                    $ret[] = strtolower(str_replace('PEAR2_Pyrus_Installer_Role_', '', $role));
                }
            }
        }
        return $ret;
    }

    /**
     * Return an array of roles that are affected by the baseinstalldir attribute
     *
     * Most roles ignore this attribute, and instead install directly into:
     * PackageName/filepath
     * so a tests file tests/file.phpt is installed into PackageName/tests/filepath.php
     * @param bool clear cache
     * @return array
     * @static
     */
    static function getBaseinstallRoles($clear = false)
    {
        if (!isset(self::$_roles)) {
            self::registerRoles();
        }
        static $ret;
        if ($clear) {
            unset($ret);
        }
        if (!isset($ret)) {
            $ret = array();
            foreach (self::$_roles as $role => $okreleases) {
                if ($okreleases['honorsbaseinstall']) {
                    $ret[] = strtolower(str_replace('PEAR2_Pyrus_Installer_Role_', '', $role));
                }
            }
        }
        return $ret;
    }

    /**
     * Return an array of file roles that should be analyzed for PHP content at package time,
     * like the "php" role.
     * @param bool clear cache
     * @return array
     * @static
     */
    static function getPhpRoles($clear = false)
    {
        if (!isset(self::$_roles)) {
            self::registerRoles();
        }
        static $ret;
        if ($clear) {
            unset($ret);
        }
        if (!isset($ret)) {
            $ret = array();
            foreach (self::$_roles as $role => $okreleases) {
                if ($okreleases['phpfile']) {
                    $ret[] = strtolower(str_replace('PEAR2_Pyrus_Installer_Role_', '', $role));
                }
            }
        }
        return $ret;
    }

    /**
     * Scan through the Command directory looking for classes
     * and see what commands they implement.
     * @param string which directory to look for classes, defaults to
     *               the Installer/Roles subdirectory of
     *               the directory from where this file (__FILE__) is
     *               included.
     *
     * @return bool TRUE on success, a PEAR error on failure
     * @access public
     * @static
     */
    static function registerRoles($dir = null)
    {
        self::$_roles = array();
        $parser = new PEAR2_Pyrus_XMLParser;
        if ($dir === null) {
            $dir = dirname(__FILE__) . '/Role';
        }
        if (!file_exists($dir) || !is_dir($dir)) {
            throw new PEAR2_Pyrus_Installer_Role_Exception("registerRoles: opendir($dir) failed");
        }
        $dp = @opendir($dir);
        if (empty($dp)) {
            throw new PEAR2_Pyrus_Installer_Role_Exception("registerRoles: opendir($dir) failed");
        }
        while ($entry = readdir($dp)) {
            if ($entry{0} == '.' || substr($entry, -4) != '.xml') {
                continue;
            }
            $class = "PEAR2_Pyrus_Installer_Role_".substr($entry, 0, -4);
            // List of roles
            if (!isset(self::$_roles[$class])) {
                $file = "$dir/$entry";
                $data = $parser->parse($file);
                $data = $data['role'];
                if (!is_array($data['releasetypes'])) {
                    $data['releasetypes'] = array($data['releasetypes']);
                }
                self::$_roles[$class] = $data;
            }
        }
        closedir($dp);
        $roles = self::$_roles;
        ksort($roles);
        self::$_roles = $roles;
        self::getBaseinstallRoles(true);
        self::getInstallableRoles(true);
        self::getPhpRoles(true);
        self::getValidRoles('****', true);
        return true;
    }

    /**
     * Retrieve configuration information about a file role from its XML info
     *
     * @param string $role Role Classname, as in "PEAR2_Pyrus_Installer_Role_Data"
     * @return array
     */
    static function getInfo($role)
    {
        if (!isset(self::$_roles)) {
            self::registerRoles();
        }
        if (empty(self::$_roles[$role])) {
            throw new PEAR2_Pyrus_Installer_Role_Exception('Unknown Role class: "' . $role . '"');
        }
        return self::$_roles[$role];
    }
}
?>
