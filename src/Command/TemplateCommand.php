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

use Bake\Command\TemplateCommand as BaseTemplateCommand;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Utility\Inflector;

/**
 * Task class for creating view template files.
 */
class TemplateCommand extends BaseTemplateCommand
{
    public function getTemplatePath(Arguments $args, ?string $container = null): string
    {
        $path = parent::getTemplatePath($args, $container);
        $path = str_replace('templates', 'templates/Baked/', $path);
        return $path;
    }

    /**
     * Override to also write to Extension path
     */
    public function bake(
        Arguments $args,
        ConsoleIo $io,
        string $template,
        $content = '',
        ?string $outputFile = null
    ): void {
        if ($outputFile === null) {
            $outputFile = $template;
        }
        if ($content === true) {
            $content = $this->getContent($args, $io, $template);
        }
        if (empty($content)) {
            $io->err("<warning>No generated content for '{$template}.php', not generating template.</warning>");

            return;
        }
        $path = $this->getTemplatePath($args);
        $filename = $path . Inflector::underscore($outputFile) . '.php';

        $io->out("\n" . sprintf('Baking `%s` view template file...', $outputFile), 1, ConsoleIo::QUIET);
        $io->createFile($filename, $content, $args->getOption('force'));

        $filename = str_replace('Baked', 'Extension', $path) . Inflector::underscore($outputFile) . '.php';
        if (!file_exists($filename)) {
            $extendedContent = <<<EOD
<?php
    include str_replace('Extension', 'Baked', __FILE__);
?>            
EOD;
            $io->createFile($filename, $extendedContent, $args->getOption('force'));
        }
    }
}
