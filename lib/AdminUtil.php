<?php

namespace woolfg\dokuwiki\plugin\gitbacked;

/*
 * AdminUtil.php
 *
 * PHP common utility function lib
 *
 * @package    AdminUtil.php
 * @version    1.0
 * @author     Markus Hoffrogge
 * @copyright  Copyright 2023 Markus Hoffrogge
 * @repo       https://github.com/woolfg/dokuwiki-plugin-gitbacked
 */

// ------------------------------------------------------------------------

use dokuwiki\plugin\config\core\Loader;
use dokuwiki\plugin\config\core\ConfigParser;

/**
 * GitBacked Admin Utility Class
 *
 * This class provides common utility functions.
 *
 * @class  AdminUtil
 */
class AdminUtil {

    /**
     * Checks, if config for 'datadir' (data/pages) and/or
     * config for 'mediadir' (data/mediadir) is specifically configured
     * in local cofnig or protected config.
     *
     * @access  public
     * @return  bool   true, if there is a specific non default configuration
     */
    public static function isNonDefaultPagesOrMediaConfig() {
        $cfgParser = new ConfigParser();
        $loader = new Loader($cfgParser);
        $localConfig = $loader->loadLocal();
        $protectedConfig = $loader->loadProtected();
        $dataDir = $protectedConfig['datadir'];
        $dataDir = empty($dataDir) ? $localConfig['datadir'] : $dataDir;
        $mediaDir = $protectedConfig['mediadir'];
        $mediaDir = empty($mediaDir) ? $localConfig['mediadir'] : $mediaDir;
        $ret = false;
        if (!empty($dataDir)) {
            // Data dir is explicitly configured
            $ret = true;
        } else {
            // Data dir is default
        }
        if (!empty($mediaDir)) {
            // Media dir is explicitly configured
            $ret = true;
        } else {
            // Media dir is default
        }
        return $ret;
    }

    /**
     * Scan for all Git repos beyond a given path.
     * If the given path is relative, we treat it relative to DOKU_INC!
     *
     * @param string $absPathOrPathBeyondDOKU_INC
     * @param bool   $isAbortOnFirstFound = false
     * @param array  $currentArrayOfGitRepoDirs
     * 
     * @return array
     * 
     */
    public static function getAllGitRepoDirsBeyondPath($absPathOrPathBeyondDOKU_INC = '', $isAbortOnFirstFound = false, $currentArrayOfGitRepoDirs = array()) {
        if ($isAbortOnFirstFound && !empty($ret)) {
            return $ret;
        }
        if (!empty($absPathOrPathBeyondDOKU_INC) && GitBackedUtil::isAbsolutePath($absPathOrPathBeyondDOKU_INC)) {
            $absDirectory = $absPathOrPathBeyondDOKU_INC;
        } else {
            $absDirectory = DOKU_INC . $absPathOrPathBeyondDOKU_INC;
        }
        $ret = $currentArrayOfGitRepoDirs;
        if (!is_dir($absDirectory)) {
            return $ret;
        }

        foreach (scandir($absDirectory) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $currentAbsFile = $absDirectory . '/' . $file;
            if (!is_dir($currentAbsFile)) {
                // No directory => not relevant for us
                continue;
            }
            if ($file === '.git') {
                // $absPathOrPathBeyondDOKU_INC is a git repo dir!
                $ret[] = $absPathOrPathBeyondDOKU_INC;
                if ($isAbortOnFirstFound) {
                    return $ret;
                }
                continue;
            }
            // iterate into next subdir
            $nextSubDir = $absPathOrPathBeyondDOKU_INC . '/' . $file;
            $ret = self::getAllGitRepoDirsBeyondPath($nextSubDir, $isAbortOnFirstFound, $ret);
        }
        return $ret;
    }

    /**
     * Look if at least one Git repo beyond a given path.
     * If the given path is relative, we treat it relative to DOKU_INC!
     * Return true, if first git repo found.
     *
     * @param string $absPathOrPathBeyondDOKU_INC
     * 
     * @return bool
     * 
     */
    public static function isGitRepoExistingBeyondPath($absPathOrPathBeyondDOKU_INC = '') {
        return !empty(self::getAllGitRepoDirsBeyondPath($absPathOrPathBeyondDOKU_INC, true));
    }
}
/* End of file */
