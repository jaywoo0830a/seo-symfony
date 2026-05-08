<?php

declare(strict_types=1);

namespace App\Twig;

use App\Cta\Cta;
use App\Cta\CtaResolver;
use App\Cta\CtaSlot;
use App\Entity\Enum\BodyTemplate;
use App\Form\Cta\ConsultFormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Twig\Attribute\AsTwigFunction;

/**
 * Twig 함수 노출:
 *   - cta(slot, body_template?): Cta — 매핑 테이블에서 적절한 CTA 결정
 *   - cta_form_view(form_name): FormView — 폼 CTA 렌더에 필요한 FormView
 */
final class CtaExtension
{
    public function __construct(
        private readonly CtaResolver $resolver,
        private readonly FormFactoryInterface $forms,
    ) {}

    #[AsTwigFunction('cta')]
    public function cta(string $slot, ?BodyTemplate $template = null): Cta
    {
        return $this->resolver->resolve(CtaSlot::from($slot), $template);
    }

    #[AsTwigFunction('cta_form_view')]
    public function ctaFormView(string $formName): FormView
    {
        $type = match ($formName) {
            'consult' => ConsultFormType::class,
            default => throw new \InvalidArgumentException(sprintf('Unknown CTA form: %s', $formName)),
        };

        return $this->forms->create($type)->createView();
    }
}
