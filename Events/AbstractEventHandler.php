<?php declare(strict_types=1);

namespace Piwik\Plugins\RebelAuditLog\Events;

use Piwik\Plugins\RebelAuditLog\AuditService;
use Piwik\Plugins\RebelAuditLog\Utility;
use Piwik\Plugins\RebelAuditLog\Events\EventHandlerInterface;

abstract class AbstractEventHandler implements EventHandlerInterface
{
    protected AuditService $auditService;
    protected Utility $utility;

    public function __construct(AuditService $auditService, Utility $utility)
    {
        $this->auditService = $auditService;
        $this->utility = $utility;
    }

    /**
     * Extracts event details from the parameters.
     */
    protected function extractEventDetails(array $params): array
    {
        return $this->utility->extractEventDetails($params);
    }

    /**
     * Helper method to log an audit entry.
     */
    protected function logAudit(
        string $eventBase,
        string $eventTask,
        string $log,
        array $details = null,
        string $actingUser = null
    ): void {
        if ($actingUser) {
            $user = $actingUser;
        } else {
            $user = $this->utility->getUser();
        }
        $this->auditService->logAudit(
            $eventBase,
            $eventTask,
            $user,
            $this->utility->getIP(),
            $this->utility->session(),
            $log,
            $details
        );
    }

    /**
     * Abstract method: Force child classes to declare subscribed events.
     */
    abstract public static function getSubscribedEvents(): array;

    /**
     * Event-specific logic is implemented in child classes using __invoke.
     */
    abstract public function __invoke(...$params): void;
}
