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
     * @var bool Shall composer dumo-autoload --optimize be executed before deploying Composer packages?
     */
    public $optimize_composer_autoloader = false;

    /**
     * @var bool Whether to use composer's cached packages
     * composer.phar install --profile --prefer-dist
     */
    public $use_cached_composer_packages = false;

    public function bootstrap($app) {
        if ($app instanceof \yii\console\Application) {
            $app->controllerMap[$this->id] = [
                'class' => 'gpayo\deployer\console\DeployerController',
                'module' => $this,
            ];
        }
    }

}
