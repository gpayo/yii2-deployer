<?php
namespace gpayo\deployer;

use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;

class Deployer extends Component {
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
}