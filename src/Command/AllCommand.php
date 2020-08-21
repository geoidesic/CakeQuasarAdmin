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
 * @since         2.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace QuasarAdmin\Command;

use Bake\Utility\TableScanner;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Datasource\ConnectionManager;
use Bake\Command\AllCommand as BaseAllCommand;
use QuasarAdmin\Command\ModelCommand;
use QuasarAdmin\Command\ControllerCommand;
use QuasarAdmin\Command\TemplateCommand;

/**
 * Command for `bake all`
 */
class AllCommand extends BaseAllCommand
{
    /**
     * All commands to call.
     * @var string[]
     */
    protected $commands = [
        ModelCommand::class,
        ControllerCommand::class,
        TemplateCommand::class,
    ];

    /**
     * Execute the command.
     * @todo: remove once PR lands: https://github.com/cakephp/bake/pull/647
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return null|int The exit code or null for success
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $this->extractCommonProperties($args);
        $name = $args->getArgument('name') ?? '';
        $name = $this->_getName($name);

        $io->out('Bake All');
        $io->hr();

        $connection = ConnectionManager::get($this->connection);
        $scanner = new TableScanner($connection);
        if (empty($name) && !$args->getOption('everything')) {
            $io->out('Choose a table to generate from the following:');
            foreach ($scanner->listUnskipped() as $table) {
                $io->out('- ' . $this->_camelize($table));
            }

            return static::CODE_SUCCESS;
        }
        if ($args->getOption('everything')) {
            $tables = $scanner->listUnskipped();
        } else {
            $tables = [$name];
        }

        foreach ($this->commands as $commandName) {
            /** @var \Cake\Command\Command $command */
            $command = new $commandName();
            foreach ($tables as $table) {
                $subArgs = new Arguments([$table], $args->getOptions(), ['name']);
                $command->execute($subArgs, $io);
            }
        }

        $io->out('<success>Bake All complete.</success>', 1, ConsoleIo::QUIET);

        return static::CODE_SUCCESS;
    }
}
