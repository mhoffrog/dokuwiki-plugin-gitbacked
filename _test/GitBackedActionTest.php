<?php

namespace dokuwiki\plugin\gitbacked\test;

use dokuwiki\plugin\gitbacked\test\core\AGitBackedTest;

use action_plugin_gitbacked_editcommit;
use woolfg\dokuwiki\plugin\gitbacked\GitRepo;
use woolfg\dokuwiki\plugin\gitbacked\Git;

/**
 * GitRepo tests for the gitbacked plugin
 *
 * @group plugin_gitbacked
 * @group plugins
 */
class GitBackedActionTest extends AGitBackedTest
{
    /**
     * Test registered action plugin instance.
     */
    public function testRegisteredInstance(): void
    {
        $this->assertInstanceOf(
            action_plugin_gitbacked_editcommit::class,
            action_plugin_gitbacked_editcommit::getRegisteredInstance()
        );
    }

    /**
     * Test init repo.
     */
    public function testInitRepo(): void
    {
        $this->assertFileDoesNotExist(self::DOKU_GITBACKED_REPO . '/.git');
        /** @var GitRepo $repo */
        $repo = action_plugin_gitbacked_editcommit::getRegisteredInstance()->initRepo();
        $this->assertInstanceOf(GitRepo::class, $repo);
        $this->assertFileExists(self::DOKU_GITBACKED_REPO . '/.git');
        $this->assertTrue($repo->testGit());
        $this->assertEquals(
            (isWindows() ? 'git.exe' : 'git') . ' -c user.name="Arthur Dent" -c user.email="<arthur@example.com>"',
            Git::getBin()
        );
    }

    /**
     * Test after init repo.
     */
    public function testAfterInitRepo(): void
    {
        $this->assertFileExists(self::DOKU_GITBACKED_REPO . '/.git');
    }
}
