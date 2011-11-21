<?php
/**
 * Centurion
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@centurion-project.org so we can send you a copy immediately.
 *
 * @category    Centurion
 * @package     Centurion_Loader
 * @copyright   Copyright (c) 2008-2011 Octave & Octave (http://www.octaveoctave.com)
 * @license     http://centurion-project.org/license/new-bsd     New BSD License
 * @version    $Id$
 */

/**
 * @category    Centurion
 * @package     Centurion_Loader
 * @copyright   Copyright (c) 2008-2011 Octave & Octave (http://www.octaveoctave.com)
 * @license     http://centurion-project.org/license/new-bsd     New BSD License
 * @author      Laurent Chenay <lc@octaveoctave.com>
 */
class Centurion_Loader_PluginLoader extends Zend_Loader_PluginLoader
{
    protected static $_shutdownRegister = false;

    protected static $_staticCachePlugin = array();

    protected static $_includeFileCacheBis;

    /**
     * Retrieve class file cache path
     *
     * @return string|null
     */
    public static function getIncludeFileCache()
    {
        return self::$_includeFileCacheBis;
    }

    public static function setStaticLoaderPluginPaths($staticLoadedPluginPaths)
    {
        self::$_staticLoadedPluginPaths = $staticLoadedPluginPaths;
    }

    public static function setStaticLoadedPlugins($staticLoadedPlugins)
    {
        self::$_staticLoadedPlugins = $staticLoadedPlugins;
    }

    public static function clean()
    {
        self::cleanCache(self::getIncludeFileCache());
        self::setStaticCachePlugin(null);
    }

    public static function cleanCache($file = null)
    {
        self::$_staticLoadedPluginPaths = array();
        self::$_staticLoadedPlugins = array();

        if (null !== $file && file_exists($file)) {
            unlink($file);
        }
    }

    public static function setStaticCachePlugin($staticCachePlugin)
    {
        self::$_staticCachePlugin = $staticCachePlugin;
    }

    /**
     * 
     * Shutdown function
     * Save all cache in plugins cache loader file.
     */
    public static function shutdown()
    {
    	$file = self::getIncludeFileCache();
        if (null !== $file) {
            $fp = fopen($file, 'w+');
            flock($fp, LOCK_EX);
            //We truncate the file to be shure it's now empty (in case someone write in it between fopen and flock) (bug:#1019)
            ftruncate($fp, 0);

            $serialised = str_replace('\'', '\\\'', serialize(self::$_staticCachePlugin));
            $file = <<<EOS
{$serialised}
EOS;
            fwrite($fp, $file);
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    public static function setIncludeFileCache($file)
    {
    	self::$_includeFileCacheBis = $file;
    }

    /**
     * Append an include_once statement to the class file cache
     *
     * @return void
     */
    protected static function _appendIncFile($incFile)
    {
        self::_registerShutDown();
    }

    protected static function _registerShutDown()
    {
        if (!self::$_shutdownRegister) {
            register_shutdown_function(array('Centurion_Loader_PluginLoader', 'shutdown'));
            self::$_shutdownRegister = true;
        }
    }

    public function load($name, $throwExceptions = true)
    {
        $formatedName = $this->_formatName($name);
        if ($this->isLoaded($formatedName)) {
            return $this->getClassName($formatedName);
        }

        if ($this->isCached($name)) {
            return $this->getClassName($formatedName);
        }

        $result = parent::load($name, $throwExceptions);

        self::_cache($formatedName, $result, $this->getClassPath($name));

        return $result;
    }

    public function _cache($name, $classname, $path)
    {
        if ($this->_useStaticRegistry) {
            if (!isset(self::$_staticCachePlugin[$this->_useStaticRegistry]))
                self::$_staticCachePlugin[$this->_useStaticRegistry] = array();

            self::$_staticCachePlugin[$this->_useStaticRegistry][$name] = array($classname, $path);
            self::_registerShutDown();
        }

        return $this;
    }

    public function isCached($name, $automaticallyLoad = true)
    {
        if (!isset(self::$_staticCachePlugin[$this->_useStaticRegistry])
            || !isset(self::$_staticCachePlugin[$this->_useStaticRegistry][$name]))
            return false;

        if ($automaticallyLoad) {
            list($className, $path) = self::$_staticCachePlugin[$this->_useStaticRegistry][$name];

            include_once $path;

            if ($this->_useStaticRegistry) {
                self::$_staticLoadedPlugins[$this->_useStaticRegistry][$name] = $className;
            } else {
                $this->_loadedPlugins[$name] = $className;
            }
        }
        
        return true;
    }
}