<?php

declare(strict_types=1);

namespace App\Service;

use BlackfireProbe;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use function array_key_exists;

final class BlackfireHandler implements EventSubscriberInterface
{
    private ?BlackfireProbe $blackfireProbe = null;

    public function __construct(private LoggerInterface $logger)
    {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$this->blackfireProbe && array_key_exists('x-blackfire-query', $request->headers->all())) {
            $this->logger->debug('Enabling blackfire probe');
            $this->blackfireProbe = new BlackfireProbe($request->headers->get('x-blackfire-query'));
            $enabled = $this->blackfireProbe->enable();
            $this->logger->debug(sprintf('Blackfire probe %s', $enabled ? 'enabled' : 'not enabled. Destructing the object'));
            if (!$enabled) {
                $this->blackfireProbe = null;
            }
        } else {
            $this->blackfireProbe = null;
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        if ($this->blackfireProbe && BlackfireProbe::isEnabled()) {
            $this->logger->debug('Blackfire probe exists. Closing it');
            $closed = $this->blackfireProbe->close();
            $this->logger->debug(sprintf('Blackfire probe %s', $closed ? 'closed' : 'not closed'));
            [$probeHeaderName, $probeHeaderValue] = explode(':', $this->blackfireProbe->getResponseLine(), 2);
            $this->logger->debug(sprintf('Blackfire response line: %s', $this->blackfireProbe->getResponseLine()));
            $this->logger->debug('Setting blackfire response header');
            $response->headers->set(strtolower(sprintf('x-%s', $probeHeaderName)), trim($probeHeaderValue));
        }

        $this->blackfireProbe = null;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 1_000_000]],
            KernelEvents::RESPONSE => [['onKernelResponse', -1_000_000]],
        ];
    }
}
