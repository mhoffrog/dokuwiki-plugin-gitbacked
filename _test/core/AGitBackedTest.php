<?php

namespace dokuwiki\plugin\gitbacked\test\core;

use Exception;

use DokuWikiTest;
use TestUtils;

use action_plugin_gitbacked_editcommit;

/**
 * Helper class to provide basic functionality for gitbacked tests
 */
abstract class AGitBackedTest extends DokuWikiTest
{
    protected const DOKU_GITBACKED_REPO = DOKU_TMP_DATA . 'gitrepo';
    protected const DOKU_GITBACKED_DATA = self::DOKU_GITBACKED_REPO . '/wiki/';

    /**
     * tests can override this
     *
     * @var array plugins to enable for test class
     */
    protected $pluginsEnabled = ['gitbacked'];

    /**
     * Setup the data directory
     *
     * This is ran before each test class
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Check environment settings required
        //Preconditions::envIsNotEmpty('GIT_BIN');
        // Copy gitbacked datadir and mediadir protected config:
        TestUtils::rcopy(DOKU_CONF . '..', __DIR__ . '/../conf');
        // Create gitbacked repo data dir:
        io_mkdir_p(self::DOKU_GITBACKED_DATA);
        // Move original datadir and mediadir to gitbacked repo dirs:
        TestUtils::rcopy(self::DOKU_GITBACKED_DATA, DOKU_TMP_DATA . 'pages');
        TestUtils::rcopy(self::DOKU_GITBACKED_DATA, DOKU_TMP_DATA . 'media');
        TestUtils::rdelete(DOKU_TMP_DATA . 'pages');
        TestUtils::rdelete(DOKU_TMP_DATA . 'media');
    }

    /**
     * Reset the DokuWiki environment before each test run. Makes sure loaded config,
     * language and plugins are correct.
     *
     * @throws Exception if plugin actions fail
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        // init overall config
        global $conf;
        $conf['plugin']['gitbacked']['repoPath'] = self::DOKU_GITBACKED_REPO;
        $conf['plugin']['gitbacked']['gitPath'] = isWindows() ? 'git.exe' : 'git';
        //$conf['plugin']['gitbacked']['addParams'] = default ...

        // Mock the user logged in - see inc\auth.php method auth_tokenlogin()
        global $USERINFO, $auth;
        $USERINFO = $auth->getUserData('testuser');
    }
}
