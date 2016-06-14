<?php

/**
 * Description of DeployerCommand
 *
 * @author gpayo
 */

namespace gpayo\deployer\console;

use yii\console\Controller;
use yii\helpers\Console;
use yii;

/**
 * Handles deploys of this project into production servers and some maintenance tasks
 *
 */
class DeployerController extends Controller {
    public $defaultAction = 'deploy';

    public function options($action) {
        $opt = [
            'deploy' => ['dryrun'],
            'deploy-vendor' => ['dryrun'],
        ];

        $result = [];

        if (isset($opt[$action])) {
            $result = $opt[$action];
        }

        return $result;
    }

    public function optionAliases() {
        return ['n' => 'dryrun'];
    }

    /**
     * Whether to run real actions (false) or not (true)
     */
    public $dryrun = false;

    protected $temporal_dir = null;

    /**
     * Releases the master branch of this project into the production servers
     *
     */
    public function actionDeploy() {
        $result = $this->checkNeededCommands();

        if (!$result) {
            $this->writeError("\nExecution stopped due previous errors.... exiting");
            return Controller::EXIT_CODE_ERROR;
        }

        $this->writeMessage('Clonning repository... ');
        $git_command = $this->buildGitCommand();

        echo "\n\n";
        echo $git_command;
        echo "\n\n";

        self::deleteDir($this->temporal_dir);
    }

    /**
     * Releases the vendor directory for this project into the production servers
     *
     */
    public function actionDeployVendor() {
    }

    protected function checkNeededCommands() {
        if (!$this->command_exists('rsync')) {
            $this->writeError("Err. Command rsync doesn't exists");
            return false;
        }

        if (!$this->command_exists('git')) {
            $this->writeError("Err. Command git doesn't exists");
            return false;
        }

        return true;
    }

    protected function command_exists($command) {
        return !empty(shell_exec("which $command"));
    }

    protected function writeError($message) {
        $this->stderr($message . "\n", Console::FG_RED | Console::BOLD);
    }

    protected function writeMessage($message) {
        $this->stdout($message);
    }

    protected function writeMessageNL($message) {
        $this->stdout($message . "\n");
    }

    protected function buildGitCommand() {
        $result  = 'git clone ';
        $result .= escapeshellarg(yii::getAlias('@app'));

        $tempfile=tempnam(sys_get_temp_dir(), '');
        if (file_exists($tempfile)) {
            unlink($tempfile);
        }
        @mkdir($tempfile);

        if (!is_dir($tempfile)) {
            $this->writeError('It was impossible to create temporal dir "' . $tempfile . '"');
            @unlink($tempfile);
            die(1);
        }

        $this->temporal_dir = $tempfile;

        $result .= ' ' . $this->temporal_dir;

        return $result;
    }

    protected static function deleteDir($dir) {
        $it = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($it,  \RecursiveIteratorIterator::CHILD_FIRST);
        foreach($files as $file) {
            if ($file->isDir()){
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($dir);
    }
}
