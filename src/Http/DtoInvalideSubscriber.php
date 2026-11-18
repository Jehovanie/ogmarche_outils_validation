<?php

namespace App\Http;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Reponse JSON {ok:false, message, violations:[{propertyPath, title}]} attendue par validation.js. */
final class DtoInvalideSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => 'onException'];
    }

    public function onException(ExceptionEvent $event): void
    {
        $e = $event->getThrowable();
        if ($e instanceof DtoInvalideException) {
            $event->setResponse(new JsonResponse([
                'ok' => false,
                'message' => $e->getMessage(),
                'violations' => $e->getViolations(),
            ], 422));
        }
    }
}
