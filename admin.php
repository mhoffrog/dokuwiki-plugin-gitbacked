<?php

/**
 * Admin Plugin for gitbacked
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author     Markus Hoffrogge
 */
// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

use dokuwiki\Form\Form;
use dokuwiki\Form\Element;
use dokuwiki\Form\InputElement;

use dokuwiki\extension\AdminPlugin;

class admin_plugin_gitbacked extends AdminPlugin {

    /**
     * Array of 2 elements:
     * [0] = Message text about a request run result
     * [1] = level: -1 = error, 0 = info, 1 = success, 2 = notify
     *
     * @var array
     */
    private $reqResult;

    /**
     * @inheritdoc
     */
    function getMenuText($language) {
        //return ''; // this will hide the menu item
        //The default implementation will getLang('menu').
        return parent::getMenuText($language);
    }

    /**
     * return sort order for position in admin menu
     */
    function getMenuSort() {
        return 300;
    }

    /**
     * handle user request
     */
    function handle() {
        global $INPUT;

        $this->reqResult = '';
        $msg = '';
        if ($INPUT->arr('run')) {
            foreach ($INPUT->arr('run') as $key => $value) {
                $msg .= $key . '=' . $value . '\n';
                break;
            }
        }
        $run = $INPUT->extract('run')->str('run');
        if (!$run) return;
        if (!checkSecurityToken()) return;
        $run = 'run'.ucfirst($run); // make first char upper case
        if (method_exists($this, $run)) {
            $this->$run();
        } else {
            // $this->reqResult = array($this->getPluginName() . ': The form request command="' . $run . '" is not yet handled!', -1);
            $msg = 'The form request command="' . $run . '" is not yet handled!\n' . $msg;
            $msg .= 'Please try again later!';
            $this->reqResult = array($msg, 2);
        }

        /*
        // make sure debugging is on;
        $conf['plugin']['smtp']['debug'] = 1;

        // send a mail
        $mail = new Mailer();
        if ($INPUT->str('to')) $mail->to($INPUT->str('to'));
        if ($INPUT->str('cc')) $mail->cc($INPUT->str('cc'));
        if ($INPUT->str('bcc')) $mail->bcc($INPUT->str('bcc'));
        $mail->subject('DokuWiki says hello');
        $mail->setBody("Hi @USER@\n\nThis is a test from @DOKUWIKIURL@");
        $ok = $mail->send();

        // check result
        if ($ok) {
            msg('Message was sent. SMTP seems to work.', 1);
        } else {
            msg('Message wasn\'t sent. SMTP seems not to work properly.', -1);
        }
        */
    }

    function runQuit() {
        global $ID;
        send_redirect(wl($ID, array('do' => 'admin'), true, '&'));
    }

    /**
     * Output HTML form
     */
    function html() {
        //global $INPUT;
        //global $conf;

        /*
        $legacyRepoPathConfigured = $this->getConf('repoPath');
        if (!empty($legacyRepoPathConfigured) && is_dir($legacyRepoPathConfigured . '/.git')) {
            echo $this->locale_xhtml('existing_intro');
            msg($this->getLang('repo_configured') . ' (' . $legacyRepoPathConfigured . ')', 0);
            return;
        }
        */

        echo $this->locale_xhtml('init_intro');

        if ($this->reqResult) msg(str_replace('\n', '<br>', $this->reqResult[0]), $this->reqResult[1]);

        /*
        $form = new Doku_Form(array());
        $form->startFieldset('Repository');

        $openFieldSetPos = $form->findElementByType('openfieldset');
        $openFieldSetElement = $form->getElementAt($openFieldSetPos);
        $openFieldSetElement['style'] = 'width: auto;';
        $form->replaceElement($openFieldSetPos, $openFieldSetElement);

        $form->addHidden('send', true);
        // @patch mhoffrog
        // $form->addElement(form_makeField('text', 'to', $INPUT->str('to'), 'To:', '', 'block'));
        $inputFieldStyle = 'width: 85%;';
        $form->addElement(form_makeField('text', 'remoteURL', $INPUT->str('remoteURL'), 'Remote URL:', '', 'block', array('size' => '', 'style' => $inputFieldStyle)));
        //$form->addElement(form_makeField('text', 'cc', $INPUT->str('cc'), 'Cc:', '', 'block', array('size' => '', 'style' => $inputFieldStyle)));
        //$form->addElement(form_makeField('text', 'bcc', $INPUT->str('bcc'), 'Bcc:', '', 'block', array('size' => '', 'style' => $inputFieldStyle)));
        $form->addElement(form_makeButton('submit', '', 'Init repository', array('id' => 'initRepo', 'name' => 'run[initRepo]')));
        $form->addElement(form_makeButton('submit', '', 'Quit', array('id' => 'quit', 'name' => 'run[quit]')));
        //$form->addElement(form_makeButton('submit', '', 'Quit', array('id' => 'quit', 'name' => 'run[quit]', 'hidden' => 'hidden')));
        //$form->addElement('<script type="text/javascript">setTimeout(function() {document.getElementById(\'quit\').click();}, 0);</script>');
        $form->printForm();
        */

        $form = new Form(array());
        // Enable our plugins CSS:
        $form->addHTML('<div id="plugin__gitbacked">');
         // TODO: Form:
         // - addFieldsetOpen
         //   par: opt: CSS id to wrap with div for CSS enablement plugin__.$this.getPluginName()
         // - addFieldsetClose close div, if fieldset has div set.
         //   inner: 
        $form->addFieldsetOpen('Repository');
        // TODO addTextInput:
        // - par: option for title (tooltip)
        // - inner: add class 'block'
        // CSS label: margin-bottom: 1em
        $e = $form->addTextInput('remoteURL', "Remote URL:"); $e->addClass('block'); $e->attr('title', 'This is my TIP of the day!');
        //$form->addHTML('<br>'); // managed by CSS margin-bottom now
        // TODO addButton:
        // - par: id
        // - par: name
        // - par: content (text)
        // - par: value = ''
        // - par: bool isPrimary
        // - inner: type = "submit"
        // CSS button: margin-bottom: 1em
        $b = $form->addButton('run[initRepo]', 'Init repository'); $b->id('__initRepo'); $b->addClass('primary');
        $b = $form->addButton('run[quit]', 'Quit'); $b->id('__quit');
        // TODO addAutoClickForButton:
        // - button ID
        // - delay
        //$form->addHTML('<script type="text/javascript">setTimeout(function() {document.getElementById(\'__quit\').click();}, 5000);</script>');
        $form->addHTML('</div>');
        echo $form->toHTML();

    }
}
