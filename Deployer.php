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

    public function bootstrap($app) {
        if ($app instanceof \yii\console\Application) {
            $app->controllerMap[$this->id] = [
                'class' => 'gpayo\deployer\commands\DeployerController',
                'module' => $this,
            ];
        }
    }

}
