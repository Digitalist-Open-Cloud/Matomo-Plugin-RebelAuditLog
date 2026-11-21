<?php

/**
 * The Rebel Audit Log plugin for Matomo.
 *
 * Copyright (C) 2024 Digitalist Open Cloud <cloud@digitalist.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace Piwik\Plugins\RebelAuditLog\Commands;

use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\RebelAuditLog\ExternalLog;

class ExternalDb extends ConsoleCommand
{
    protected function configure()
    {
        $this->setName('rebelauditlog:external-db');
        $this->setDescription('Checks the external database of the audit log, and creates its tables.');
        $this->setHelp(
            "The external database is set in the [RebelAuditLog] section of config/config.ini.php.\n"
            . "Without options the command connects and tells how many entries the database has.\n\n"
            . "  ./console rebelauditlog:external-db --create-tables\n"
            . '  ./console rebelauditlog:external-db --print-sql'
        );
        $this->addNoValueOption('create-tables', null, 'Create the tables, unless they exist.');
        $this->addNoValueOption('print-sql', null, 'Print the statements that create the tables, without connecting.');
    }

    protected function doExecute(): int
    {
        $external = new ExternalLog();
        $output = $this->getOutput();

        if (!$external->isConfigured()) {
            $output->writeln('<error>No external database is configured: external_dsn in the [RebelAuditLog] section is empty.</error>');
            return self::FAILURE;
        }

        if ($this->getInput()->getOption('print-sql')) {
            $output->writeln(implode(";\n\n", $external->getTableDefinitions()) . ';');
            return self::SUCCESS;
        }

        if ($this->getInput()->getOption('create-tables')) {
            $external->createTables();
            $output->writeln('<info>The tables exist.</info>');
        }

        $output->writeln(sprintf('<info>Connected (%s). Entries in the external database: %d.</info>', $external->getDriver(), $external->count()));

        return self::SUCCESS;
    }
}
