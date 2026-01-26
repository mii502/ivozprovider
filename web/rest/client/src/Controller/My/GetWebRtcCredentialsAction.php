<?php

namespace Controller\My;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Ivoz\Provider\Domain\Model\RetailAccount\RetailAccountRepository;

class GetWebRtcCredentialsAction
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private RetailAccountRepository $retailAccountRepository
    ) {}

    public function __invoke(int $id): JsonResponse
    {
        $admin = $this->tokenStorage->getToken()?->getUser();
        if (!$admin) {
            throw new NotFoundHttpException('User not found');
        }

        $company = $admin->getCompany();
        if (!$company) {
            throw new NotFoundHttpException('Company not found');
        }

        $retailAccount = $this->retailAccountRepository->find($id);

        // Verify ownership
        if (!$retailAccount || $retailAccount->getCompany()->getId() !== $company->getId()) {
            throw new NotFoundHttpException('RetailAccount not found');
        }

        // Get SIP domain from RetailAccount's Domain entity (per-brand, NOT hardcoded)
        $domain = $retailAccount->getDomain();
        $sipDomain = $domain ? $domain->getDomain() : null;

        if (!$sipDomain) {
            throw new \RuntimeException('No SIP domain configured for this account');
        }

        // Build WSS server URL from domain (assumes /ws-sip path convention)
        $wsServer = 'wss://' . $sipDomain . '/ws-sip';

        // Note: We include Google's public STUN server to handle IPv6 candidates quickly.
        // Without an IPv6-capable STUN server, browsers wait ~40 seconds for IPv6 STUN
        // responses that will never come (our coturn only listens on IPv4).
        return new JsonResponse([
            'sipUser' => $retailAccount->getName(),
            'sipPassword' => $retailAccount->getPassword(),
            'domain' => $sipDomain,
            'displayName' => $retailAccount->getDescription() ?? $retailAccount->getName(),
            'wsServer' => $wsServer,
            'stunServers' => [
                'stun:' . $sipDomain . ':3478',
                'stun:stun.l.google.com:19302'  // IPv6-capable fallback
            ],
            'turnServers' => [[
                'urls' => 'turn:' . $sipDomain . ':3478',
                'username' => 'webrtc',
                'credential' => 'Wh8K3mNpQrStUvXyZ9AaBbCcDdEe'
            ]]
        ]);
    }
}
