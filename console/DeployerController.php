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
    public $module;

    public $defaultAction = 'deploy';

    public function options($action) {
        $opt = [
            'deploy' => ['dryrun', 'verbose'],
            'deploy-vendor' => ['dryrun', 'use_cached', 'optimize', 'verbose'],
        ];

        $result = [];

        if (isset($opt[$action])) {
            $result = $opt[$action];
        }

        return $result;
    }

    public function optionAliases() {
        return [
            'n' => 'dryrun',
            'o' => 'optimize',
            'c' => 'use_cached',
            'v' => 'verbose',
        ];
    }

    /**
     * @var bool Whether to run real actions (false) or not (true)
     */
    public $dryrun = false;

    /**
     * @var bool Shall composer dump-autoload --optimize be executed before deploying Composer packages?
     */
    public $optimize = false;

    /**
    * @var bool Show status messages
    */
    public $verbose = false;

    /**
     * @var bool Whether to use composer's cached packages
     * composer.phar install --profile --prefer-dist
     */
    public $use_cached = false;

    protected $temporal_dir = null;

    public function init() {
        $result = $this->checkNeededCommands();

        if (!$result) {
            $this->writeError("\nExecution stopped due previous errors.... exiting");
            return Controller::EXIT_CODE_ERROR;
        }
    }

    /**
     * Releases the master branch of this project into the production servers
     *
     */
    public function actionDeploy() {
        $this->writeMessage('Clonning repository... ');
        $git_command = $this->buildGitCommand();

        if ($this->verbose) {
            $this->writeMessageNL('INFO Issuing: ');
            $this->writeMessageNL($git_command);
        }

        shell_exec($git_command);

        $this->writeMessageNL(' done!');

        $rsync_command_template = $this->buildRsyncCommand();

        foreach ($this->module->production_servers as $production_server) {
            $rsync_command = str_replace('{production_server}', $production_server, $rsync_command_template);
            $this->writeMessageNL('Copying data to ' . $production_server);

            if ($this->verbose) {
                $this->writeMessageNL('INFO Issuing: ');
                $this->writeMessageNL($rsync_command);
            }

            $result = shell_exec($rsync_command);

            echo $result;

        }

        self::deleteDir($this->temporal_dir);
    }

    /**
     * Releases the vendor directory for this project into the production servers
     *
     */
    public function actionDeployVendor() {
        echo $this->getUniqueID();
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
        $result  = 'git clone -q ';
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

    protected function buildRsyncCommand() {
        $result  = 'rsync -i --filter=\':- .gitignore\' -vazc --no-g --no-t --no-p ';

        if (PHP_OS == 'Darwin') {
            $result .= '--iconv=utf-8-mac,utf-8 ';
        }

        if ($this->dryrun) {
            $result .= '-n ';
        }

        $result .= ' -e ssh ' . $this->temporal_dir . '/ ';

        $result .= '{production_server}:' . $this->module->production_root . ' ';

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
