<?php

/**
 * Description of DeployerCommand
 *
 * @author gpayo
 */

namespace gpayo\deployer\console;

use yii\console\Controller;
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
    
    /**
     * Releases the master branch of this project into the production servers
     *
     */
    public function actionDeploy() {
        var_dump($this->module->production_servers);
    }
    
    /**
     * Releases the vendor directory for this project into the production servers
     *
     */
    public function actionDeployVendor() {

    }
    
    protected function checkNeededCommands() {
        
    }
}
