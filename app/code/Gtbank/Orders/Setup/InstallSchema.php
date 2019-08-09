<?php

namespace Gtbank\Orders\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;

class InstallSchema implements InstallSchemaInterface
{
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();
        $tableName = $installer->getTable('cancel_order');
		
        if ($installer->getConnection()->isTableExists($tableName) != true) {
            $table = $installer->getConnection()
                ->newTable($tableName)
                ->addColumn(
                    'id',
                    Table::TYPE_INTEGER,
                    null,
                    [
                        'identity' => true,
                        'unsigned' => true,
                        'nullable' => false,
                        'primary' => true
                    ],
                    'ID'
                )
                ->addColumn(
                    'order_id',
                    Table::TYPE_INTEGER,
                    255,
                    ['nullable' => false, 'default' => 0],
                    'order_id'
                )
                ->addColumn(
                    'item_id',
                    Table::TYPE_INTEGER,
                    255,
                    ['nullable' => false, 'default' => 0],
                    'item_id'
                )
                ->addColumn(
                    'seller_id',
                    Table::TYPE_INTEGER,
                    11,
                    ['nullable' => true,'default' => 0],
                    'seller_id'
                )->addColumn(
                    'reason',
                    Table::TYPE_TEXT,
                    null,
                    ['nullable' => true],
                    'reason'
                )->addColumn(
                    'created_at',
                    Table::TYPE_DATETIME,
                    null,
                    ['nullable' => false],
                    'Created At'
                )->setOption('type', 'InnoDB')
                 ->setOption('charset', 'utf8');
            $installer->getConnection()->createTable($table);
        }

        $installer->endSetup();
    }
}
