<?php

namespace Cinovic\Otplogin\Setup;

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
        $tableName = $installer->getTable('user_otp');
        if ($installer->getConnection()->isTableExists($tableName) != true) {
            $table = $installer->getConnection()
                ->newTable($tableName)
                ->addColumn(
                    'entity_id',
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
                    'customer',
                    Table::TYPE_TEXT,
                    'customer'
                )
                ->addColumn(
                    'otp',
                    Table::TYPE_TEXT,
                    'otp'
                )
                ->addColumn(
                    'status',
                    Table::TYPE_INTEGER,
                    'status'
                )->addColumn(
                    'created_at',
                    Table::TYPE_DATETIME,
                    null,
                    ['nullable' => false],
                    'Created At'
                );
            $installer->getConnection()->createTable($table);
        }

        $installer->endSetup();
    }
}
