<?php
namespace QuasarAdmin\View\Helper;

use Cake\Datasource\ConnectionManager;
use Bake\View\Helper\BakeHelper as BaseHelper;

/**
 * Bake helper
 */
class BakeHelper extends BaseHelper
{
    public function checkTableExistsByAlias($alias) {
        $exists = false;
        $connection = ConnectionManager::get('default');
        $tables = $connection->execute('show tables;')->fetchAll('assoc');
        foreach (array_values($tables) as $key => $tableName) {
            if ($alias === array_values($tableName)[0]) {
                $exists = true;
            }
        }
        return $exists;
    }
}
