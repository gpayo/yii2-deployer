<?php

namespace gpayo\deployer;

use Yii;
use yii\base\Module;
use yii\base\InvalidConfigException;
use yii\base\BootstrapInterface;

class Deployer extends Module implements BootstrapInterface {
    /**
     * @var string[] array of production servers for this project
     * As in ["10.0.1.1", "example.com"]
     */
    public $production_servers = [];

    /**
     * @var string The root of the Yii2 project in the production servers
     * Ex. "/var/www/project/"
     */
    public $production_root = null;

    /**
     * @var string Whole path to the rsync command
     */
    public $rsync_bin = 'rsync';

    /**
     * @var string Whole path to the git command
     */
    public $git_bin = 'git';

    /**
     * @var string Whole path to the composer.phar command
     */
    public $composer_bin = 'composer.phar';

    /**
     * @var string[] Additional directories to clear. Base will be @app
     */
    public $runtime_directories = [];

    public function init() {
        if (count($this->production_servers) == 0) {
            throw new InvalidConfigException('No production servers defined');
        }
        if (empty($this->production_root)) {
            throw new InvalidConfigException('No production root defined');
        }
        if (!is_array($this->runtime_directories)) {
            throw new InvalidConfigException('runtime_directories is not an array');
        }

        parent::init();
    }

    public function bootstrap($app) {
        if ($app instanceof \yii\console\Application) {
            $app->controllerMap[$this->id] = [
                'class' => 'gpayo\deployer\console\DeployerController',
                'module' => $this,
            ];
        }
    }

}
