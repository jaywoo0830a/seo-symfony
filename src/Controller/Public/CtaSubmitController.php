<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Form\Cta\ConsultFormType;
use App\Notifier\CompositeCtaNotifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * CTA 폼 제출 처리. 자체 DB 저장 X — 알림 채널(텔레그램/디스코드)로 fan-out 후 끝.
 *
 * thank-you 페이지는 X-Robots-Tag: noindex로 색인 차단 (얇은 페이지 양산 방지).
 *
 * NOTE: 운영 전환 시 framework.rate_limiter 추가 필요 (IP/세션당 분당 N회 제한).
 *       현재는 honeypot + CSRF로만 1차 차단.
 */
final class CtaSubmitController extends AbstractController
{
    /** form 이름 → FormType 매핑. 새 폼 추가 시 여기 + Twig CtaExtension에 등록. */
    private const FORM_TYPES = [
        'consult' => ConsultFormType::class,
    ];

    #[Route(
        '/cta/submit/{form}',
        name: 'cta_submit',
        requirements: ['form' => '[a-z][a-z0-9_-]*'],
        methods: ['POST'],
    )]
    public function submit(
        string $form,
        Request $request,
        CompositeCtaNotifier $notifier,
    ): Response {
        $formType = self::FORM_TYPES[$form] ?? null;
        if ($formType === null) {
            throw $this->createNotFoundException();
        }

        $f = $this->createForm($formType);
        $f->handleRequest($request);

        // honeypot — 봇이 채웠으면 조용히 성공 페이지로 (스팸에 답을 주지 않음)
        if ($f->isSubmitted() && $f->has('hp') && $f->get('hp')->getData()) {
            return $this->redirectToRoute('cta_thank_you');
        }

        if (!$f->isSubmitted() || !$f->isValid()) {
            // 폼 검증 실패 — 세션 플래시 후 referer로 돌려보냄
            $this->addFlash('cta_error', '입력값을 확인해 주세요.');

            return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('public_home'));
        }

        /** @var array<string, mixed> $raw */
        $raw = (array) $f->getData();
        $payload = [];
        foreach (['name', 'phone', 'region', 'memo'] as $key) {
            if (isset($raw[$key]) && $raw[$key] !== '') {
                $payload[$key] = $raw[$key];
            }
        }

        $notifier->send($form, $payload);

        return $this->redirectToRoute('cta_thank_you');
    }

    #[Route('/cta/thank-you', name: 'cta_thank_you', methods: ['GET'])]
    public function thankYou(): Response
    {
        $response = $this->render('public/cta/thank_you.html.twig');
        // 색인 차단 — 폼 제출 결과 페이지가 검색 결과에 노출되면 SEO에 해롭다.
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Cache-Control', 'no-store, max-age=0');

        return $response;
    }
}
