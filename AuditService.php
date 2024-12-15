<?php

namespace Piwik\Plugins\RebelAuditLog;

use Piwik\Common;
use Piwik\Db;
use Piwik\Plugins\RebelAuditLog\Utility;

class AuditService
{
    private function getDb()
    {
        return Db::get();
    }

    public function logAudit(string $eventBase, string $eventTask, string $user, string $ip, ?string $session, string $auditLog, ?array $details): void
    {
        $query = "INSERT INTO `" . Common::prefixTable('rebel_audit') . "`
            (event_base, event_task, user, ip, session, audit_log) VALUES (?,?,?,?,?,?)";
        $params = [$eventBase, $eventTask, $user, $ip, $session, $auditLog];

        $db = $this->getDb();
        $db->query($query, $params);
        $lastId = (int) $db->lastInsertId();

        if (is_array($details)) {
            foreach ($details as $key => $value) {
                $query = "INSERT INTO `" . Common::prefixTable('rebel_audit_details') . "`
                (base_id, `key`, `value`) VALUES (?,?,?)";
                $params = [$lastId, $key, $value];

                //$out = $query . " " . implode(",",$params);
                //$utility = new Utility();
                //$logger = $utility->logger();
                //$logger->error($out);


                $db->query($query, $params);
            }

        }


    }
}
