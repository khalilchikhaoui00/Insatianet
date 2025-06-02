<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240326201500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename admin table to client and update foreign key references';
    }

    public function up(Schema $schema): void
    {
        // Create new client table
        $this->addSql('CREATE TABLE `client` LIKE `admin`');
        $this->addSql('INSERT INTO `client` SELECT * FROM `admin`');
        
        // Update foreign key references
        $this->addSql('UPDATE `shop_order` o JOIN `client` c ON o.user_id = c.id SET o.user_id = c.id');
        $this->addSql('UPDATE `payment_method` p JOIN `client` c ON p.user_id = c.id SET p.user_id = c.id');
        $this->addSql('UPDATE `reset_password_request` r JOIN `client` c ON r.user_id = c.id SET r.user_id = c.id');
        
        // Drop old table
        $this->addSql('DROP TABLE `admin`');
    }

    public function down(Schema $schema): void
    {
        // Create old admin table
        $this->addSql('CREATE TABLE `admin` LIKE `client`');
        $this->addSql('INSERT INTO `admin` SELECT * FROM `client`');
        
        // Update foreign key references
        $this->addSql('UPDATE `shop_order` o JOIN `admin` a ON o.user_id = a.id SET o.user_id = a.id');
        $this->addSql('UPDATE `payment_method` p JOIN `admin` a ON p.user_id = a.id SET p.user_id = a.id');
        $this->addSql('UPDATE `reset_password_request` r JOIN `admin` a ON r.user_id = a.id SET r.user_id = a.id');
        
        // Drop new table
        $this->addSql('DROP TABLE `client`');
    }
} 