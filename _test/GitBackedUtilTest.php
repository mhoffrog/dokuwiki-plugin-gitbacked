<?php

namespace dokuwiki\plugin\gitbacked\test;

//use DokuWikiTest;
use Mailer;

use dokuwiki\test\mock\MailerMock;

use dokuwiki\plugin\gitbacked\test\core\AGitBackedTest;

use woolfg\dokuwiki\plugin\gitbacked\GitBackedUtil;

/**
 * GitBackedUtil tests for the gitbacked plugin
 *
 * @group plugin_gitbacked
 * @group plugins
 */
//class GitBackedUtilTest extends DokuWikiTest
class GitBackedUtilTest extends AGitBackedTest
{
    /**
     * Simple test to make sure the plugin.info.txt is in correct format
     */
    public function testDebugOutput(): void
    {
        global $conf;

        $confDir = DOKU_CONF . '';
        fwrite(STDERR, sprintf("DOKU_CONF: %s\n", $confDir));
        fwrite(STDERR, sprintf("datadir: %s\n", $conf['datadir']));
        fwrite(STDERR, sprintf("mediadir: %s\n", $conf['mediadir']));
        $user = $GLOBALS['auth']->getUserData('testuser');
        fwrite(STDERR, print_r($user, true));
        $this->assertTrue(true);
    }

    /**
     * Test the common setup.
     */
    public function testGitBackedSetUp(): void
    {
        $this->assertTrue(is_dir(self::DOKU_GITBACKED_DATA . 'pages'));
        $this->assertTrue(is_dir(self::DOKU_GITBACKED_DATA . 'media'));
    }

    /**
     * Test the common setup.
     */
    public function testIsUnitTest(): void
    {
        $this->assertTrue(GitBackedUtil::isUnitTest());
    }

    /**
     * Test the common setup.
     */
    public function testMailerClass(): void
    {
        $mailer = GitBackedUtil::newMailer();
        $this->assertTrue(
            $mailer instanceof Mailer,
            '$mailer (class=' . get_class($mailer) . ') is NOT intance of Mailer::class'
        );
        $this->assertFalse($mailer instanceof MailerMock, '$mailer must NOT be instance of MailerMock::class');
        GitBackedUtil::setMailerClass(MailerMock::class);
        $mailer = GitBackedUtil::newMailer();
        $this->assertTrue(
            $mailer instanceof Mailer,
            '$mailer (class=' . get_class($mailer) . ') is NOT intance of Mailer::class'
        );
        $this->assertTrue($mailer instanceof MailerMock, '$mailer is NOT intance of MailerMock::class');
    }
}
