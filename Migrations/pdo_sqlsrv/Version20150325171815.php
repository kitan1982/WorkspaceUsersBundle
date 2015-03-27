<?php

namespace Claroline\WorkspaceUsersBundle\Migrations\pdo_sqlsrv;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated migration based on mapping information: modify it with caution
 *
 * Generation date: 2015/03/25 05:18:16
 */
class Version20150325171815 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $this->addSql("
            CREATE TABLE claro_workspace_user (
                id INT IDENTITY NOT NULL, 
                workspace_id INT NOT NULL, 
                user_id INT NOT NULL, 
                created BIT NOT NULL, 
                registration_date DATETIME2(6) NOT NULL, 
                PRIMARY KEY (id)
            )
        ");
        $this->addSql("
            CREATE INDEX IDX_C95C9D5A82D40A1F ON claro_workspace_user (workspace_id)
        ");
        $this->addSql("
            CREATE INDEX IDX_C95C9D5AA76ED395 ON claro_workspace_user (user_id)
        ");
        $this->addSql("
            CREATE UNIQUE INDEX workspace_users_unique_workspace_user ON claro_workspace_user (user_id, workspace_id) 
            WHERE user_id IS NOT NULL 
            AND workspace_id IS NOT NULL
        ");
        $this->addSql("
            ALTER TABLE claro_workspace_user 
            ADD CONSTRAINT FK_C95C9D5A82D40A1F FOREIGN KEY (workspace_id) 
            REFERENCES claro_workspace (id) 
            ON DELETE CASCADE
        ");
        $this->addSql("
            ALTER TABLE claro_workspace_user 
            ADD CONSTRAINT FK_C95C9D5AA76ED395 FOREIGN KEY (user_id) 
            REFERENCES claro_user (id) 
            ON DELETE CASCADE
        ");
    }

    public function down(Schema $schema)
    {
        $this->addSql("
            DROP TABLE claro_workspace_user
        ");
    }
}