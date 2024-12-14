<?php

namespace Piwik\Plugins\RebelAuditLog\Events;

interface EventHandlerInterface
{
    /**
     * Return the event name(s) this handler listens to.
     *
     * Example: return ['Login.authenticate.successful'];
     *
     * @return string[]
     */
    public static function getSubscribedEvents(): array;

    /**
     * Invoked when the event is triggered.
     *
     * @param mixed $params Event parameters sent by Matomo.
     */
    public function __invoke(...$params): void;
}
