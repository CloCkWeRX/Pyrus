<?php
class PEAR2_Pyrus_PackageFile_v2Iterator_PackagingIterator extends ArrayIterator
{
    static private $_parent;
    static function setParent(PEAR2_Pyrus_PackageFile_v2 $parent)
    {
        self::$_parent = $parent;
    }

    function key()
    {
        $curfile = $this->current();
        $a = 'PEAR2_Pyrus_Installer_Role_' . ucfirst($curfile['attribs']['role']);
        $role = new $a(PEAR2_Pyrus_Config::current());
        // add the install-as attribute to retrieve packaging location
        return $role->getPackagingLocation(self::$_parent, $curfile['attribs']);
    }

    function current()
    {
        $curfile = parent::current();
        if ($base = self::$_parent->getBaseInstallDir($curfile['attribs']['name'])) {
            $curfile['attribs']['baseinstalldir'] = $base;
        } elseif (!isset($curfile['attribs']['baseinstalldir'])) {
            $curfile['attribs']['baseinstalldir'] =
                (dirname($curfile['attribs']['name']) === '.' ?
                    '/' :
                    dirname($curfile['attribs']['name']));
        }
        if (isset($curfile['attribs']['md5sum'])) {
            unset($curfile['attribs']['md5sum']);
        }
        return $curfile;
    }
}