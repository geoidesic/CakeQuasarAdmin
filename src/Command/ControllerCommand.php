<?php
declare (strict_types = 1);

/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         0.1.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace QuasarAdmin\Command;

use Bake\Command\ControllerCommand as BaseControllerCommand;
use Bake\Utility\TemplateRenderer;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;

/**
 * Task class for creating and updating controller files.
 */
class ControllerCommand extends BaseControllerCommand
{
    /**
     * Path fragment for generated code.
     *
     * @var string
     */
    public $pathFragment = 'Controller/Baked/';

    public function getControllerContext(string $controllerName, Arguments $args, ConsoleIo $io): array
    {
        $io->quiet(sprintf('Baking controller class for %s...', $controllerName));

        $actions = [];
        if (!$args->getOption('no-actions') && !$args->getOption('actions')) {
            $actions = ['index', 'view', 'add', 'edit', 'delete'];
        }
        if ($args->getOption('actions')) {
            $actions = array_map('trim', explode(',', $args->getOption('actions')));
            $actions = array_filter($actions);
        }

        $helpers = $this->getHelpers($args);
        $components = $this->getComponents($args);

        $prefix = $this->getPrefix($args);
        if ($prefix) {
            $prefix = '\\' . str_replace('/', '\\', $prefix);
        }

        $namespace = Configure::read('App.namespace');
        if ($this->plugin) {
            $namespace = $this->_pluginNamespace($this->plugin);
        }

        $currentModelName = $controllerName;
        $plugin = $this->plugin;
        if ($plugin) {
            $plugin .= '.';
        }

        if (TableRegistry::getTableLocator()->exists($plugin . $currentModelName)) {
            $modelObj = TableRegistry::getTableLocator()->get($plugin . $currentModelName);
        } else {
            $modelObj = TableRegistry::getTableLocator()->get($plugin . $currentModelName, [
                'connectionName' => $this->connection,
            ]);
        }

        $pluralName = $this->_variableName($currentModelName);
        $singularName = $this->_singularName($currentModelName);
        $singularHumanName = $this->_singularHumanName($controllerName);
        $pluralHumanName = $this->_variableName($controllerName);

        $defaultModel = sprintf('%s\Model\Table\%sTable', $namespace, $controllerName);
        if (!class_exists($defaultModel)) {
            $defaultModel = null;
        }
        $entityClassName = $this->_entityName($modelObj->getAlias());

        $data = compact(
            'actions',
            'components',
            'currentModelName',
            'defaultModel',
            'entityClassName',
            'helpers',
            'modelObj',
            'namespace',
            'plugin',
            'pluralHumanName',
            'pluralName',
            'prefix',
            'singularHumanName',
            'singularName'
        );
        $data['name'] = $controllerName;
        return $data;
    }
    /**
     * Assembles and writes a Controller file
     *
     * @param string $controllerName Controller name already pluralized and correctly cased.
     * @param \Cake\Console\Arguments $args The console arguments
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return void
     */
    public function bake(string $controllerName, Arguments $args, ConsoleIo $io): void
    {
        $data = $this->getControllerContext($controllerName, $args, $io);
        $data['crudPluginLoaded'] = \Cake\Core\Plugin::isLoaded('Crud');
        $this->bakeController($controllerName, $data, $args, $io);
        $this->bakeTest($controllerName, $args, $io);
        $options = ['template' => 'api', 'folder' => 'Api', 'onlyIfNotExists' => true];
        $this->bakeControllerToFolder($controllerName, $data, $args, $io, $options);
    }

    /**
     * Writes additionally to a folder of choice
     *
     * @param string $controllerName The name of the controller.
     * @param array $data The data to turn into code.
     * @return string The generated controller file.
     */
    public function bakeControllerToFolder(string $controllerName, array $data, Arguments $args, ConsoleIo $io, array $options): void
    {
        $data += [
            'name' => null,
            'namespace' => null,
            'prefix' => null,
            'actions' => null,
            'helpers' => null,
            'components' => null,
            'plugin' => null,
            'pluginPath' => null,
        ];

        $renderer = new TemplateRenderer($this->theme);
        $renderer->set($data);

        $contents = $renderer->generate('Controller/' . $options['template']);

        $path = $this->getPath($args);
        $filename = $path . $controllerName . 'Controller.php';
        $filename = str_replace('Baked', $options['folder'], $filename);

        if ($options['onlyIfNotExists'] && !file_exists($filename)) {
            $io->createFile($filename, $contents);
        }
    }
}
