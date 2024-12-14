<?php

namespace Piwik\Plugins\RebelAuditLog;

use Piwik\Common;
use Piwik\Db;

class AuditService
{
    private function getDb()
    {
        return Db::get();
    }

    public function logAudit(string $eventBase, string $eventTask, string $user, string $ip, ?string $session, string $auditLog): void
    {
        $query = "INSERT INTO `" . Common::prefixTable('rebel_audit') . "`
            (event_base, event_task, user, ip, session, audit_log) VALUES (?,?,?,?,?,?)";
        $params = [$eventBase, $eventTask, $user, $ip, $session, $auditLog];

        $db = $this->getDb();
        $db->query($query, $params);
    }
}
