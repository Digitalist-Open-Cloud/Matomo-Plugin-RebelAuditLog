<?php

namespace Piwik\Plugins\RebelAuditLog;

use Piwik\Plugins\RebelAuditLog\Events\EventHandlerInterface;
use ReflectionClass;

class EventHandlerFactory
{
    private const EVENT_DIRECTORY = __DIR__ . '/Events';
    private const EVENT_NAMESPACE = 'Piwik\\Plugins\\RebelAuditLog\\Events';

    private AuditService $auditService;
    private Utility $utility;

    public function __construct(AuditService $auditService, Utility $utility)
    {
        $this->auditService = $auditService;
        $this->utility = $utility;
    }

    public function discoverEventHandlers(): array
    {
        $eventHandlers = [];
        $files = glob(self::EVENT_DIRECTORY . '/*.php'); // Step 1: Scan only relevant directory

        foreach ($files as $file) {
            $className = self::EVENT_NAMESPACE . '\\' . basename($file, '.php'); // Namespace + Class Name
            if (class_exists($className)) {
                $reflection = new ReflectionClass($className);
                if ($reflection->isAbstract() || !$reflection->implementsInterface(EventHandlerInterface::class)) {
                    continue;
                }
                if ($reflection->implementsInterface(EventHandlerInterface::class)) {
                    $handler = $reflection->newInstanceArgs([$this->auditService, $this->utility]);
                    foreach ($className::getSubscribedEvents() as $event) {
                        $eventHandlers[$event] = $handler;
                    }
                }
            }
        }

        return $eventHandlers;
    }
}
