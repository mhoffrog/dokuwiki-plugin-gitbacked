<?php

/**
 * Default settings for the gitbacked plugin
 *
 * @author Wolfgang Gassler <wolfgang@gassler.org>
 */

$conf['pushAfterCommit'] = 0;
$conf['periodicPull'] = 0;
$conf['periodicMinutes'] = 60;
$conf['updateIndexOnPull'] = 0;
$conf['commitPageMsg'] = 'Page %ns:page% changed by %user%: [%summary%]';
$conf['commitPageMsgDel'] = 'Page %ns:page% deleted by %user%: [%summary%]';
$conf['commitMediaMsg'] = 'Media %media% uploaded by %user%';
$conf['commitMediaMsgDel'] = 'Media %media% deleted by %user%';
$conf['repoPath'] = $GLOBALS['conf']['savedir'];
$conf['repoWorkDir'] = '';
$conf['gitPath'] = '';
$conf['addParams'] = '-c user.name="%user%" -c user.email="<%mail%>"';
$conf['ignorePaths'] = '';
$conf['emailAddressOnError'] = '';
$conf['notifyByMailOnSuccess'] = 0;
