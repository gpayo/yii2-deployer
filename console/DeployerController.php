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
    /** @var \gpayo\Deployer */
    public $module;

    public $defaultAction = 'deploy';

    public function options($action) {
        $opt = [
            'deploy' => ['dryrun', 'verbose', 'clearRuntime', 'noCompactCssJs'],
            'deploy-vendor' => ['dryrun', 'noUseCached', 'noOptimize', 'verbose'],
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
            'o' => 'noOptimize',
            'c' => 'noUseCached',
            'v' => 'verbose',
            'y' => 'noCompactCssJs',
        ];
    }

    /**
     * @var bool Whether to run real actions (false) or not (true)
     */
    public $dryrun = false;

    /**
     * @var bool Shall composer dump-autoload --optimize be executed before deploying Composer packages?
     */
    public $noOptimize = false;

    /**
    * @var bool Show status messages
    */
    public $verbose = false;

    /**
     * @var bool Whether to use composer's cached packages
     * composer.phar install --profile --prefer-dist
     */
    public $noUseCached = false;

    /**
     * @var bool Issues `yii cache/flush-all` after the release
     */
    public $clearRuntime = false;


    /**
     * @var bool Avoid default compact of CSS and JS files in @webroot/css and @webroot/js
     */
    public $noCompactCssJs = false;

    protected $temporalDir = null;

    protected $lessFilesFound = false;

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
        $this->cloneRepository();

        if (!$this->noCompactCssJs) {
            if ($this->verbose) {
                $this->writeMessageNL('INFO compacting CSS');
            }
            if (!$this->dryrun) {
                $less_files_command = 'find ' . $this->temporalDir . '/web/css/*.less';
                $less_files = shell_exec($less_files_command);
                $less_files = explode("\n", $less_files);
                foreach ($less_files as $less_file) {
                    if (empty($less_file)) {
                        continue;
                    }
                    $this->lessFilesFound = true;
                    $less_command = 'lessc --include-path='. escapeshellarg(yii::getAlias('@app')) .'/vendor/bower/bootstrap/less/ ' . $less_file;
                    $css_content = shell_exec($less_command);
                    if (!empty($css_content)) {
                        $css_file = preg_replace('/\.less/', '', $less_file) . '.css';
                        file_put_contents($css_file, $css_content);
                    }
                }

                $files = $this->temporalDir . '/web/css/*.css';
                $command = $this->module->java_bin . ' -jar vendor/bin/yuicompressor.jar -o ".css$:.css" ' . $files ;

                echo shell_exec($command);
            }
            if ($this->verbose) {
                $this->writeMessageNL('INFO compacting JS');
            }
            if (!$this->dryrun) {
                $files = $this->temporalDir . '/web/js/*.js';
                $command = $this->module->java_bin . ' -jar vendor/bin/yuicompressor.jar -o ".js$:.js" ' . $files;

                echo shell_exec($command);
            }
        }

        $rsync_command_template = $this->buildRsyncCommand();

        $this->rsyncRepository($rsync_command_template);

        if ($this->clearRuntime) {
            $command_template = 'ssh {production_server} {command}';
            foreach ($this->module->production_servers as $production_server) {
                $command = str_replace('{production_server}', $production_server, $command_template);
                $command = str_replace('{command}', escapeshellarg($this->module->production_root . '/yii cache/flush-all'), $command);

                echo shell_exec($command);

                foreach ($this->module->runtime_directories as $dir) {
                    $command = str_replace('{production_server}', $production_server, $command_template);
                    $command = str_replace('{command}', escapeshellarg('rm -fr ' . $this->module->production_root . '/runtime/' . $dir), $command);

                    echo shell_exec($command);
                }
            }
        }

        echo $this->temporalDir;
        return;

        self::deleteDir($this->temporalDir);
    }

    /**
     * Releases the vendor directory for this project into the production servers
     *
     */
    public function actionDeployVendor() {
        $this->cloneRepository();

        $this->writeMessage('Getting composer packages... ');
        $remove_command = $this->module->composer_bin . ' -q remove --dev gpayo/yii2-deployer -d=' . escapeshellarg($this->temporalDir) . ' ';
        echo shell_exec($remove_command);

        $command  = $this->module->composer_bin . ' -q install -d=' . escapeshellarg($this->temporalDir) . ' ';
        $command .= '--no-dev ';

        if (!$this->noUseCached) {
            $command .= '--profile --prefer-dist ';
        }

        if (!$this->noOptimize) {
            $command .= '-o ';
        }

        if ($this->dryrun) {
            $command .= '--dry-run ';
        }

        if ($this->verbose) {
            $this->writeMessageNL('INFO Issuing: ' . $command);
        }

        echo shell_exec($command);

        $this->writeMessageNL('done!');

        $rsync_command_template = $this->buildRsyncCommand('vendor/');

        $this->rsyncRepository($rsync_command_template);

        self::deleteDir($this->temporalDir);
    }

    protected function rsyncRepository($rsync_command_template) {
        foreach ($this->module->production_servers as $production_server) {
            $rsync_command = str_replace('{production_server}', $production_server, $rsync_command_template);
            $this->writeMessageNL('Copying data to ' . $production_server);

            if ($this->verbose) {
                $this->writeMessageNL();
                $this->writeMessage('INFO Issuing: ');
                $this->writeMessageNL($rsync_command);
                $this->writeMessageNL();
            }

            $result = shell_exec($rsync_command);

            // Removing the directories
            $result = preg_replace('!^.+/ *\n!m', '', $result);

            $this->writeMessage($result);

            if ($this->clearRuntime) {
                $this->runClearRuntime();
            }

        }
    }

    protected function cloneRepository() {
        $this->writeMessage('Clonning repository... ');
        $git_command = $this->buildGitCommand();

        if ($this->verbose) {
            $this->writeMessageNL('INFO Issuing: ');
            $this->writeMessageNL($git_command);
        }

        shell_exec($git_command);

        $this->writeMessageNL(' done!');
    }

    protected function checkNeededCommands() {
        if (!$this->command_exists($this->module->rsync_bin)) {
            $this->writeError("Err. Command rsync doesn't exists");
            return false;
        }

        if (!$this->command_exists($this->module->git_bin)) {
            $this->writeError("Err. Command git doesn't exists");
            return false;
        }

        if (!$this->command_exists($this->module->composer_bin)) {
            $this->writeError("Err. Command composer.phar doesn't exists");
            return false;
        }

        if (!$this->command_exists($this->module->java_bin)) {
            $this->writeError("Err. Command java doesn't exists");
            return false;
        }

        return true;
    }

    protected function command_exists($command) {
        return !empty(shell_exec("which $command"));
    }

    protected function writeError($message='') {
        $this->stderr($message . "\n", Console::FG_RED | Console::BOLD);
    }

    protected function writeMessage($message='') {
        $this->stdout($message);
    }

    protected function writeMessageNL($message = '') {
        $this->stdout($message . "\n");
    }

    protected function buildGitCommand() {
        $result  = $this->module->git_bin . ' clone -q ';
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

        $this->temporalDir = $tempfile;

        $result .= ' ' . $this->temporalDir;

        return $result;
    }

    protected function buildRsyncCommand($dir='') {
        $result  = $this->module->rsync_bin . ' -i ';

        if ($this->lessFilesFound) {
            $result .= '--include=*.css ';
        }

        $result .= '--filter=\':- .gitignore\' -Cvazc --no-g --no-t --no-p ';

        if (PHP_OS == 'Darwin') {
            $result .= '--iconv=utf-8-mac,utf-8 ';
        }

        if ($this->dryrun) {
            $result .= '-n ';
        }

        $result .= ' -e ssh ' . $this->temporalDir . "/$dir ";

        $result .= '{production_server}:' . $this->module->production_root . "/$dir ";

        return $result;
    }

    protected static function deleteDir($dir) {
        $it = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($it,  \RecursiveIteratorIterator::CHILD_FIRST);

        foreach($files as $file) {
            if ($file->isDir()){
                rmdir($file->getRealPath());
            } else {
                if ($file->isLink()) {
                    unlink($file->getPathName());
                } else {
                    unlink($file->getRealPath());
                }
            }
        }
        rmdir($dir);
    }

    protected function runClearRuntime() {
        $fullPathToYii = escapeshellarg($this->module->production_root . '/yii cache/flush-all');

        foreach ($this->module->production_servers as $production_server) {
            $command = 'ssh ' . $production_server . ' ' . $fullPathToYii;

            if ($this->verbose) {
                $this->writeMessageNL('INFO Issuing: ' . $command);
            }

            if (!$this->dryrun) {
                shell_exec($command);
            }
        }

        foreach ($this->module->runtime_directories as $runtime_dir) {
            $dir = $this->module->production_root . '/runtime/' . $runtime_dir;

            $command = 'ssh ' . $production_server . ' ' . escapeshellarg('rm -fr ' . $dir);

            if ($this->verbose) {
                $this->writeMessageNL('INFO Issuing: ' . $command);
            }

            if (!$this->dryrun) {
                shell_exec($command);
            }
        }
    }
}
