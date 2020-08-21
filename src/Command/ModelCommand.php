<?php
declare(strict_types=1);

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

use Bake\Command\ModelCommand as BaseModelCommand;
use Bake\Utility\TemplateRenderer;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Core\Configure;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;

/**
 * Command for generating model files.
 */
class ModelCommand extends BaseModelCommand
{
    /**
     * Override to add in extension baking for models, controllers and views
     */
    public function bake(string $name, Arguments $args, ConsoleIo $io): void
    {
        $table = $this->getTable($name, $args);
        $tableObject = $this->getTableObject($name, $table);
        $data = $this->getTableContext($tableObject, $table, $name, $args, $io);
        $this->bakeTable($tableObject, $data, $args, $io);
        $this->bakeEntity($tableObject, $data, $args, $io);
        $this->bakeFixture($tableObject->getAlias(), $tableObject->getTable(), $args, $io);
        $this->bakeTest($tableObject->getAlias(), $args, $io);
        $this->bakeTableExtension($tableObject, $data, $args, $io);
        $this->bakeTableMain($tableObject, $data, $args, $io);
    }

    /**
     * Override to add in Search plugin
     */
    public function getBehaviors(Table $model): array
    {
        $behaviors = [];
        $schema = $model->getSchema();
        $fields = $schema->columns();
        if (empty($fields)) {
            return [];
        }
        if (in_array('created', $fields) || in_array('modified', $fields)) {
            $behaviors['Timestamp'] = [];
        }

        if (
            in_array('lft', $fields)
            && $schema->getColumnType('lft') === 'integer'
            && in_array('rght', $fields)
            && $schema->getColumnType('rght') === 'integer'
            && in_array('parent_id', $fields)
        ) {
            $behaviors['Tree'] = [];
        }

        $counterCache = $this->getCounterCache($model);
        if (!empty($counterCache)) {
            $behaviors['CounterCache'] = $counterCache;
        }
        if (\Cake\Core\Plugin::isLoaded('Search')) {
            $behaviors['Search.Search'] = [];
        }

        return $behaviors;
    }

    /**
     * Override in order to:
     * 1. add in searchPluginLoaded and searchField values
     * 2. alter the write path for baked models
     */
    public function bakeTable(Table $model, array $data, Arguments $args, ConsoleIo $io): void
    {
        if ($args->getOption('no-table')) {
            return;
        }

        $namespace = Configure::read('App.namespace');
        $pluginPath = '';
        if ($this->plugin) {
            $namespace = $this->_pluginNamespace($this->plugin);
        }

        $name = $model->getAlias();
        $entity = $this->_entityName($model->getAlias());
        $schema = $model->getSchema();
        $searchField = null;
        if (in_array('name', $schema->columns())) {
            $searchField = 'name';
        }
        if (in_array('title', $schema->columns())) {
            $searchField = 'title';
        }
        $searchPluginLoaded = \Cake\Core\Plugin::isLoaded('Search');

        $data += [
            'plugin' => $this->plugin,
            'pluginPath' => $pluginPath,
            'namespace' => $namespace,
            'name' => $name,
            'entity' => $entity,
            'associations' => [],
            'primaryKey' => 'id',
            'displayField' => null,
            'table' => null,
            'validation' => [],
            'rulesChecker' => [],
            'behaviors' => [],
            'connection' => $this->connection,
            'searchPluginLoaded' => $searchPluginLoaded,
            'searchField' => $searchField,
        ];

        $renderer = new TemplateRenderer($this->theme);
        $renderer->set($data);
        $out = $renderer->generate('Model/baked');

        $path = $this->getPath($args);
        $filename = $path . 'Table' . DS . 'Baked' . DS . $name . 'Table.php';
        $io->out("\n" . sprintf('Baking table class for %s...', $name), 1, ConsoleIo::QUIET);
        $io->createFile($filename, $out);

        // Work around composer caching that classes/files do not exist.
        // Check for the file as it might not exist in tests.
        if (file_exists($filename)) {
            require_once $filename;
        }
        TableRegistry::getTableLocator()->clear();

        $emptyFile = $path . 'Table' . DS . 'Baked' . DS . 'empty';
        $this->deleteEmptyFile($emptyFile, $io);
    }

    public function bakeTableMain(Table $model, array $data, Arguments $args, ConsoleIo $io): void
    {
        if ($args->getOption('no-table')) {
            return;
        }

        $namespace = Configure::read('App.namespace');
        $pluginPath = '';
        if ($this->plugin) {
            $namespace = $this->_pluginNamespace($this->plugin);
        }

        $name = $model->getAlias();
        $entity = $this->_entityName($model->getAlias());
        $schema = $model->getSchema();
        $searchField = null;
        if (in_array('name', $schema->columns())) {
            $searchField = 'name';
        }
        if (in_array('title', $schema->columns())) {
            $searchField = 'title';
        }
        $searchPluginLoaded = \Cake\Core\Plugin::isLoaded('Search');

        $data += [
            'plugin' => $this->plugin,
            'pluginPath' => $pluginPath,
            'namespace' => $namespace,
            'name' => $name,
            'entity' => $entity,
            'associations' => [],
            'primaryKey' => 'id',
            'displayField' => null,
            'table' => null,
            'validation' => [],
            'rulesChecker' => [],
            'behaviors' => [],
            'connection' => $this->connection,
            'searchPluginLoaded' => $searchPluginLoaded,
            'searchField' => $searchField,
        ];

        $renderer = new TemplateRenderer($this->theme);
        $renderer->set($data);
        $out = $renderer->generate('Model/main');

        $path = $this->getPath($args);
        $filename = $path . 'Table' . DS . $name . 'Table.php';

        // Don't overwrite existing, so as not to lose custom code
        $exists = file_exists($filename);
        if ($exists) {
            return;
        }

        $io->out("\n" . sprintf('Baking table class for %s...', $name), 1, ConsoleIo::QUIET);
        $io->createFile($filename, $out);

        // Work around composer caching that classes/files do not exist.
        // Check for the file as it might not exist in tests.
        if (file_exists($filename)) {
            require_once $filename;
        }
        TableRegistry::getTableLocator()->clear();

        $emptyFile = $path . 'Table' . DS . 'empty';
        $this->deleteEmptyFile($emptyFile, $io);
    }

    public function bakeTableExtension(Table $model, array $data, Arguments $args, ConsoleIo $io): void
    {
        if ($args->getOption('no-table')) {
            return;
        }

        $namespace = Configure::read('App.namespace');
        $pluginPath = '';
        if ($this->plugin) {
            $namespace = $this->_pluginNamespace($this->plugin);
        }

        $name = $model->getAlias();
        $entity = $this->_entityName($model->getAlias());
        $schema = $model->getSchema();
        $searchField = null;
        if (in_array('name', $schema->columns())) {
            $searchField = 'name';
        }
        if (in_array('title', $schema->columns())) {
            $searchField = 'title';
        }
        $searchPluginLoaded = \Cake\Core\Plugin::isLoaded('Search');

        $data += [
            'plugin' => $this->plugin,
            'pluginPath' => $pluginPath,
            'namespace' => $namespace,
            'name' => $name,
            'entity' => $entity,
            'associations' => [],
            'primaryKey' => 'id',
            'displayField' => null,
            'table' => null,
            'validation' => [],
            'rulesChecker' => [],
            'behaviors' => [],
            'connection' => $this->connection,
            'searchPluginLoaded' => $searchPluginLoaded,
            'searchField' => $searchField,
        ];

        $renderer = new TemplateRenderer($this->theme);
        $renderer->set($data);
        $out = $renderer->generate('Model/extension');

        $path = $this->getPath($args);
        $filename = $path . 'Table' . DS . 'Extension' . DS . $name . 'Table.php';
        $io->out("\n" . sprintf('Baking table class for %s...', $name), 1, ConsoleIo::QUIET);

        if (!file_exists($filename)) {
            $io->createFile($filename, $out);
        }

        // Work around composer caching that classes/files do not exist.
        // Check for the file as it might not exist in tests.
        if (file_exists($filename)) {
            require_once $filename;
        }
        TableRegistry::getTableLocator()->clear();

        $emptyFile = $path . 'Table' . DS . 'Extension' . DS . 'empty';
        $this->deleteEmptyFile($emptyFile, $io);
    }

    /**
     * Override to only add in the belongsTo association if it is found, preventing errors when it's not.
     */
    public function findBelongsTo(Table $model, array $associations): array
    {
        $schema = $model->getSchema();
        foreach ($schema->columns() as $fieldName) {
            if (!preg_match('/^.+_id$/', $fieldName) || ($schema->getPrimaryKey() === [$fieldName])) {
                continue;
            }

            if ($fieldName === 'parent_id') {
                $className = $this->plugin ? $this->plugin . '.' . $model->getAlias() : $model->getAlias();
                $assoc = [
                    'alias' => 'Parent' . $model->getAlias(),
                    'className' => $className,
                    'foreignKey' => $fieldName,
                ];
                $found = true;
            } else {
                $tmpModelName = $this->_modelNameFromKey($fieldName);
                if (!in_array(Inflector::tableize($tmpModelName), $this->_tables, true)) {
                    $found = $this->findTableReferencedBy($schema, $fieldName);
                    if ($found) {
                        $tmpModelName = Inflector::camelize($found);
                    }
                } else {
                    $found = true;
                }
                $assoc = [
                    'alias' => $tmpModelName,
                    'foreignKey' => $fieldName,
                ];
                if ($schema->getColumn($fieldName)['null'] === false) {
                    $assoc['joinType'] = 'INNER';
                }
            }

            if ($this->plugin && empty($assoc['className'])) {
                $assoc['className'] = $this->plugin . '.' . $assoc['alias'];
            }

            // don't add associations for tables that don't exist
            if ($found) {
                $associations['belongsTo'][] = $assoc;
            }
        }

        return $associations;
    }

    /**
     * Override to detect self-referencing M2M
     */
    public function findBelongsToMany(Table $model, array $associations): array
    {
        $schema = $model->getSchema();
        $tableName = $schema->name();
        $foreignKey = $this->_modelKey($tableName);

        $tables = $this->listAll();
        foreach ($tables as $otherTableName) {
            $assocTable = null;
            $offset = strpos($otherTableName, $tableName . '_');
            $otherOffset = strpos($otherTableName, '_' . $tableName);

            if ($offset !== false) {
                $assocTable = substr($otherTableName, strlen($tableName . '_'));
            } elseif ($otherOffset !== false) {
                $assocTable = substr($otherTableName, 0, $otherOffset);
            }
            // detect self-referencing many-to-many
            if ($offset !== false && $otherOffset !== false) {
                $model = $this->getTableObject($this->_camelize($otherTableName), $otherTableName);
                $assocTable = false;
                $schema = $model->getSchema();

                foreach ($model->getSchema()->columns() as $key => $column) {
                    if ($assocTable) {
                        break;
                    }
                    if ($column !== 'id' && $column !== $tableName . '_id') {
                        $assocTable = Inflector::pluralize(substr($column, 0, strpos($column, '_')));
                        $habtmName = $this->_camelize($assocTable);
                        $assoc = [
                            'alias' => $habtmName,
                            'className' => $this->_camelize($tableName),
                            'foreignKey' => $foreignKey,
                            'targetForeignKey' => $this->_modelKey($habtmName),
                            'joinTable' => $otherTableName,
                        ];
                        if ($assoc && $this->plugin) {
                            $assoc['className'] = $this->plugin . '.' . $assoc['alias'];
                        }
                        $associations['belongsToMany'][] = $assoc;
                    }
                }
            }

            if ($assocTable && in_array($assocTable, $tables)) {
                $habtmName = $this->_camelize($assocTable);
                $assoc = [
                    'alias' => $habtmName,
                    'foreignKey' => $foreignKey,
                    'targetForeignKey' => $this->_modelKey($habtmName),
                    'joinTable' => $otherTableName,
                ];
                if ($this->plugin) {
                    $assoc['className'] = $this->plugin . '.' . $assoc['alias'];
                }
                $associations['belongsToMany'][] = $assoc;
            }
        }

        return $associations;
    }
}
