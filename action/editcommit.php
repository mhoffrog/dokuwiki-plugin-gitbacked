<?php

/**
 * DokuWiki Plugin gitbacked (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Wolfgang Gassler <wolfgang@gassler.org>
 */

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');

require_once __DIR__ . '/../loader.php';

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;
use dokuwiki\Search\Exception\SearchException;
use dokuwiki\Search\Indexer;

use woolfg\dokuwiki\plugin\gitbacked\Git;
use woolfg\dokuwiki\plugin\gitbacked\GitRepo;
use woolfg\dokuwiki\plugin\gitbacked\GitBackedUtil;

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps
class action_plugin_gitbacked_editcommit extends ActionPlugin
{
    /**
     * Singleton instance
     *
     * @var action_plugin_gitbacked_editcommit
     */
    private static $REGISTERED_INSTANCE;

    /**
     * Temporary directory for this gitbacked plugin.
     *
     * @var string
     */
    private $temp_dir;

    /**
     * Get the instance that has been recently registered by EventHandler.
     *
     * @return action_plugin_gitbacked_editcommit|null
     *
     */
    public static function getRegisteredInstance(): ?action_plugin_gitbacked_editcommit
    {
        return self::$REGISTERED_INSTANCE;
    }

    public function __construct()
    {
        $this->temp_dir = GitBackedUtil::getTempDir();
    }

    /**
     * Called by DokuWiki plugin framework
     *
     * @param EventHandler $controller
     *
     * @return void
     *
     */
    public function register(EventHandler $controller): void
    {
        $controller->register_hook('IO_WIKIPAGE_WRITE', 'AFTER', $this, 'handleIOWikiPageWrite');
        $controller->register_hook('MEDIA_UPLOAD_FINISH', 'AFTER', $this, 'handleMediaUpload');
        $controller->register_hook('MEDIA_DELETE_FILE', 'AFTER', $this, 'handleMediaDeletion');
        $controller->register_hook('DOKUWIKI_DONE', 'AFTER', $this, 'handlePeriodicPull');
        self::$REGISTERED_INSTANCE = $this;
    }

    /**
     * Init repo if needed and Git::getBin() core command according to plugin config.
     *
     * @return GitRepo
     *
     */
    public function initRepo(): GitRepo
    {
        //get path to the repo root (by default DokuWiki's savedir)
        $repoPath = GitBackedUtil::getEffectivePath($this->getConf('repoPath'));
        $gitPath = trim($this->getConf('gitPath'));
        if ($gitPath !== '') {
            Git::setBin($gitPath);
        }
        //init the repo and create a new one if it is not present
        io_mkdir_p($repoPath);
        $repo = new GitRepo($repoPath, $this, true, true);
        //set git working directory (by default DokuWiki's savedir)
        $repoWorkDir = $this->getConf('repoWorkDir');
        if (!empty($repoWorkDir)) {
            $repoWorkDir = GitBackedUtil::getEffectivePath($repoWorkDir);
        }
        Git::setBin(empty($repoWorkDir) ? Git::getBin()
            : Git::getBin() . ' --work-tree ' . escapeshellarg($repoWorkDir));
        $params = str_replace(
            ['%mail%', '%user%'],
            [$this->getAuthorMail(), $this->getAuthor()],
            $this->getConf('addParams')
        );
        if ($params) {
            Git::setBin(Git::getBin() . ' ' . $params);
        }
        return $repo;
    }

    /**
     * Check if $filePath is configured to be ignored.
     *
     * @param string $filePath
     *
     * @return bool
     *
     */
    private function isIgnored($filePath): bool
    {
        $ignore = false;
        $ignorePaths = trim($this->getConf('ignorePaths'));
        if ($ignorePaths !== '') {
            $paths = explode(',', $ignorePaths);
            foreach ($paths as $path) {
                if (strstr($filePath, $path)) {
                    $ignore = true;
                }
            }
        }
        return $ignore;
    }

    /**
     * Commit $filePath with log message $message.
     *
     * @param string $filePath
     * @param string $message
     *
     * @return void
     *
     */
    private function commitFile($filePath, $message): void
    {
        if (!$this->isIgnored($filePath)) {
            $message = str_replace(
                ['%user%', '%mail%', '\n'],
                [$this->getAuthor(), $this->getAuthorMail(), "\n"],
                $message
            );

            try {
                $repo = $this->initRepo();

                //add the changed file and set the commit message
                $repo->add($filePath);
                $repo->commit($message);

                //if the push after Commit option is set we push the active branch to origin
                if ($this->getConf('pushAfterCommit')) {
                    $repo->push('origin', $repo->activeBranch());
                }
            } catch (Exception $e) {
                if (!$this->isNotifyByEmailOnGitCommandError()) {
                    throw new Exception('Git committing or pushing failed: ' . $e->getMessage(), 1, $e);
                }
                return;
            }
        }
    }

    /**
     * Return value from $GLOBALS['USERINFO'] by $key.
     *
     * @param string $key
     *
     * @return string value from $GLOBALS['USERINFO'] or empty string
     *
     */
    private function getUserInfo($key): string
    {
        return $GLOBALS['USERINFO'][$key] ?? '';
    }

    /**
     * Return current user name as commit author.
     *
     * @return string current user name or empty string
     *
     */
    private function getAuthor(): string
    {
        return $this->getUserInfo('name');
    }

    /**
     * Return current user eMail as commit eMail.
     *
     * @return string current user eMail or empty string
     *
     */
    private function getAuthorMail(): string
    {
        return $this->getUserInfo('mail');
    }

    private function computeLocalPath(): string
    {
        global $conf;
        $repoPath = str_replace('\\', '/', realpath(GitBackedUtil::getEffectivePath($this->getConf('repoPath'))));
        $datadir = $conf['datadir']; // already normalized
        if (!str_starts_with($datadir, $repoPath)) {
            throw new Exception('Datadir not inside repoPath ??');
        }
        return substr($datadir, strlen($repoPath) + 1);
    }

    private function updatePage($page): void
    {
        if (method_exists(Indexer::class, 'addPage')) {
            // Newer DokuWiki: the indexer reports failures as exceptions
            try {
                $success = (new Indexer())->addPage($page, false);
            } catch (SearchException $e) {
                $success = $e->getMessage();
            }
        } elseif (function_exists('idx_addPage')) {
            $success = idx_addPage($page, false, false);
        } else {
            // Failed to index the page. Your DokuWiki is older than release 2011-05-25 "Rincewind"
            $success = false;
        }

        echo "Update $page: $success <br/>";
    }

    public function handlePeriodicPull(Event &$event, $param): void
    {
        if ($this->getConf('periodicPull')) {
            $enableIndexUpdate = $this->getConf('updateIndexOnPull');
            $lastPullFile = $this->temp_dir . '/lastpull.txt';
            //check if the lastPullFile exists
            if (is_file($lastPullFile)) {
                $lastPull = unserialize(file_get_contents($lastPullFile));
            } else {
                $lastPull = 0;
            }
            //calculate time between pulls in seconds
            $timeToWait = $this->getConf('periodicMinutes') * 60;
            $now = time();

            //if it is time to run a pull request
            if ($lastPull + $timeToWait < $now) {
                // Throttle by last attempt, not just last success, to avoid retrying
                // on every HTTP request while the remote is unavailable.
                file_put_contents($lastPullFile, serialize($now));
                try {
                    $repo = $this->initRepo();
                    if ($enableIndexUpdate) {
                        $localPath = $this->computeLocalPath();

                        // store current revision id
                        $revBefore = $repo->run('rev-parse HEAD');
                    }

                    //execute the pull request
                    $repo->pull('origin', $repo->activeBranch());

                    if ($enableIndexUpdate) {
                        // store new revision id
                        $revAfter = $repo->run('rev-parse HEAD');

                        if (strcmp($revBefore, $revAfter) != 0) {
                            // if there were some changes, get the list of all changed files
                            $changedFilesPage = $repo->run('diff --name-only ' . $revBefore . ' ' . $revAfter);
                            $changedFiles = preg_split("/\r\n|\n|\r/", $changedFilesPage);

                            foreach ($changedFiles as $cf) {
                                // check if the file is inside localPath, that is, it's a page
                                if (str_starts_with($cf, $localPath)) {
                                    // convert from relative filename to page name
                                    // for example: local/path/dir/subdir/test.txt -> dir:subdir:test
                                    // -4 removes .txt
                                    $page = str_replace('/', ':', substr($cf, strlen($localPath) + 1, -4));

                                    // update the page
                                    $this->updatePage($page);
                                } else {
                                    echo "Page NOT to update: $cf <br/>";
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    if (!$this->isNotifyByEmailOnGitCommandError()) {
                        throw new Exception('Git command failed to perform periodic pull: ' . $e->getMessage(), 2, $e);
                    }
                    return;
                }
            }
        }
    }

    public function handleMediaDeletion(Event &$event, $param): void
    {
        $mediaPath = $event->data['path'];
        $mediaName = $event->data['name'];

        $message = str_replace(
            ['%media%'],
            [$mediaName],
            $this->getConf('commitMediaMsgDel')
        );

        $this->commitFile($mediaPath, $message);
    }

    public function handleMediaUpload(Event &$event, $param)
    {
        $mediaPath = $event->data[1];
        $mediaName = $event->data[2];

        $message = str_replace(
            ['%media%'],
            [$mediaName],
            $this->getConf('commitMediaMsg')
        );

        $this->commitFile($mediaPath, $message);
    }

    public function handleIOWikiPageWrite(Event &$event, $param): void
    {
        /** @var Input $INPUT */
        /** @var string $SUM */
        global $INPUT, $SUM;
        dbglog("GitBacked - handleIOWikiPageWrite: Event={" . print_r($event, true) . "}");
        dbglog("GitBacked - handleIOWikiPageWrite: SUM={" . print_r($SUM, true) . "}");
        //dbglog("GitBacked - handleIOWikiPageWrite: INPUT={" . print_r($INPUT, true) . "}");

        $rev = $event->data[3]; // bool false or timestamp as long
        if ($rev) {
            // Archiving former revision of the page - nothing to do.
            return;
        }

        /* On update to an existing page this event is called twice,
         * once for the transfer of the old version to the attic (rev will have a value)
         * and once to write the new version of the page into the wiki (rev is false)
         */
        $pagePath = $event->data[0][0]; // Absolute full path name of the page file
        $nameSpace = $event->data[1]; // Namespace of the page or empty
        $pageName = $event->data[2]; // Page name as managed by DokuWiki
        $pageContent = $event->data[0][1]; // Page content

        // Get the summary directly from the global $SUM variable.
        // This is available since DokuWiki commit 514144657a25baed120871a6cee217d67da99b38
        // from Jun 24, 2012 - within DokuWiki Release 2012-10-13 "Adora Belle"
        //$editSummary = $INPUT->str('summary');
        $editSummary = $SUM;

        // empty content indicates a page deletion
        if ($pageContent == '') {
            // get the commit text for deletions
            $msgTemplate = $this->getConf('commitPageMsgDel');

            // bad hack as DokuWiki deletes the file after this event
            // thus, let's delete the file by ourselves, so git can recognize the deletion
            // DokuWiki uses @unlink as well, so no error should be thrown if we delete it twice
            @unlink($pagePath);
        } else {
            //get the commit text for edits
            $msgTemplate = $this->getConf('commitPageMsg');
        }

        $message = str_replace(
            [
                '%ns:page%',
                '%page%',
                '%summary%',
                '%ns%'
            ],
            [
                ($nameSpace) ? $nameSpace . ':' . $pageName : $pageName,
                $pageName,
                $editSummary,
                $nameSpace
            ],
            $msgTemplate
        );

        $this->commitFile($pagePath, $message);
    }

    // ====== Error notification helpers ======
    /**
     * Notifies error on create_new
     *
     * @access  public
     * @param   string  repository path
     * @param   string  reference path / remote reference
     * @param   string  error message
     * @return  bool
     */
    public function notifyCreateNewError($repo_path, $reference, $error_message): bool
    {
        $template_replacements = [
            'GIT_REPO_PATH' => $repo_path,
            'GIT_REFERENCE' => (empty($reference) ? 'n/a' : $reference),
            'GIT_ERROR_MESSAGE' => $error_message
        ];
        return $this->notifyByMail('mail_create_new_error_subject', 'mail_create_new_error', $template_replacements);
    }

    /**
     * Notifies error on setting repo path
     *
     * @access  public
     * @param   string  repository path
     * @param   string  error message
     * @return  bool
     */
    public function notifyRepoPathError($repo_path, $error_message): bool
    {
        $template_replacements = [
            'GIT_REPO_PATH' => $repo_path,
            'GIT_ERROR_MESSAGE' => $error_message
        ];
        return $this->notifyByMail('mail_repo_path_error_subject', 'mail_repo_path_error', $template_replacements);
    }

    /**
     * Notifies error on git command
     *
     * @access  public
     * @param   string  repository path
     * @param   string  current working dir
     * @param   string  command line
     * @param   int     exit code of command (status)
     * @param   string  error message
     * @return  bool
     */
    public function notifyCommandError($repo_path, $cwd, $command, $status, $error_message): bool
    {
        $template_replacements = [
            'GIT_REPO_PATH' => $repo_path,
            'GIT_CWD' => $cwd,
            'GIT_COMMAND' => $command,
            'GIT_COMMAND_EXITCODE' => $status,
            'GIT_ERROR_MESSAGE' => $error_message
        ];
        return $this->notifyByMail('mail_command_error_subject', 'mail_command_error', $template_replacements);
    }

    /**
     * Notifies success on git command
     *
     * @access  public
     * @param   string  repository path
     * @param   string  current working dir
     * @param   string  command line
     * @return  bool
     */
    public function notifyCommandSuccess($repo_path, $cwd, $command): bool
    {
        if (!$this->getConf('notifyByMailOnSuccess')) {
            return false;
        }
        $template_replacements = [
            'GIT_REPO_PATH' => $repo_path,
            'GIT_CWD' => $cwd,
            'GIT_COMMAND' => $command
        ];
        return $this->notifyByMail('mail_command_success_subject', 'mail_command_success', $template_replacements);
    }

    /**
     * Send an eMail, if eMail address is configured
     *
     * @access  public
     * @param   string  lang id for the subject
     * @param   string  lang id for the template(.txt)
     * @param   array   array of replacements
     * @return  bool
     */
    public function notifyByMail($subject_id, $template_id, $template_replacements): bool
    {
        $ret = false;
        //dbglog("GitBacked - notifyByMail: [subject_id=" . $subject_id
        //    . ", template_id=" . $template_id
        //    . ", template_replacements=" . $template_replacements . "]");
        if (!$this->isNotifyByEmailOnGitCommandError()) {
            return $ret;
        }
        //$template_text = rawLocale($template_id); // this works for core artifacts only - not for plugins
        $template_filename = $this->localFN($template_id);
        $template_text = file_get_contents($template_filename);
        $template_html = $this->render_text($template_text);

        $mailer = GitBackedUtil::newMailer();
        $mailer->to($this->getEmailAddressOnErrorConfigured());
        //dbglog("GitBacked - lang check['".$subject_id."']: ".$this->getLang($subject_id));
        //dbglog("GitBacked - template text['".$template_id."']: ".$template_text);
        //dbglog("GitBacked - template html['".$template_id."']: ".$template_html);
        $mailer->subject($this->getLang($subject_id));
        $mailer->setBody($template_text, $template_replacements, null, $template_html);

        $ret = $mailer->send();

        return $ret;
    }

    /**
     * Check, if eMail is to be sent on a Git command error.
     *
     * @access  public
     * @return  bool
     */
    public function isNotifyByEmailOnGitCommandError(): bool
    {
        $emailAddressOnError = $this->getEmailAddressOnErrorConfigured();
        return !empty($emailAddressOnError);
    }

    /**
     * Get the eMail address configured for notifications.
     *
     * @access  public
     * @return  string
     */
    public function getEmailAddressOnErrorConfigured(): string
    {
        $emailAddressOnError = trim($this->getConf('emailAddressOnError'));
        return $emailAddressOnError;
    }
}
// phpcs:enable Squiz.Classes.ValidClassName.NotCamelCaps
// phpcs:enable PSR1.Classes.ClassDeclaration.MissingNamespace

// vim:ts=4:sw=4:et:
