<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Author;
use App\Entity\ContentNode;
use App\Entity\Enum\BodyTemplate;
use App\Entity\Enum\ContentStatus;
use App\Entity\Region;
use App\Entity\Theme;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContentNodeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('theme', EntityType::class, [
                'label' => '테마',
                'class' => Theme::class,
                'choice_label' => fn (Theme $t) => sprintf('%s (/%s/)', $t->getName(), $t->getSlug()),
                'placeholder' => '— 테마 선택 —',
            ])
            ->add('region', EntityType::class, [
                'label' => '지역',
                'class' => Region::class,
                'choice_label' => fn (Region $r) => sprintf('%s · depth %d', $r->getName(), $r->getDepth()),
                'required' => false,
                'placeholder' => '— 테마 허브 (지역 없음) —',
                'help' => '비워두면 테마 허브(/{theme}/) 노드가 됩니다.',
            ])
            ->add('author', EntityType::class, [
                'label' => '작성자',
                'class' => Author::class,
                'choice_label' => fn (Author $a) => $a->getRealName() . ($a->isVerified() ? ' · 인증' : ' · 미인증'),
                'required' => false,
                'placeholder' => '— 미지정 —',
                'help' => '발행 시 인증된 작성자가 필요합니다.',
            ])
            ->add('status', EnumType::class, [
                'label' => '상태',
                'class' => ContentStatus::class,
                'choice_label' => fn (ContentStatus $s) => match ($s) {
                    ContentStatus::Draft => '초안 (draft) — 작업 중',
                    ContentStatus::Live => '발행 (live) — 공개됨',
                    ContentStatus::Noindex => '비색인 (noindex) — 발행 게이트 미달',
                    ContentStatus::Dead => '폐기 (dead) — 자동 301 리다이렉트',
                },
            ])
            ->add('introText', TextareaType::class, [
                'label' => '인트로 텍스트',
                'required' => false,
                'attr' => ['rows' => 4],
                'help' => '2~3문장의 직접 답변. 발행 시 필수.',
            ])
            ->add('bodyTemplate', EnumType::class, [
                'label' => '본문 템플릿',
                'class' => BodyTemplate::class,
                'choice_label' => fn (BodyTemplate $t) => match ($t) {
                    BodyTemplate::Hub => '허브 (hub) — 매트릭스 자동',
                    BodyTemplate::Sido => '시도 (sido) — 매트릭스 자동',
                    BodyTemplate::Sigungu => '시군구 (sigungu) — 매트릭스 자동',
                    BodyTemplate::Dong => '동 (dong) — 매트릭스 자동',
                    BodyTemplate::GuideLongform => '가이드 — 심층 (guide_longform)',
                    BodyTemplate::GuideComparison => '가이드 — 비교 (guide_comparison)',
                    BodyTemplate::GuideFaq => '가이드 — FAQ (guide_faq)',
                    BodyTemplate::Essay => '에세이 (essay)',
                    BodyTemplate::Report => '데이터 리포트 (report)',
                    BodyTemplate::CaseStudy => '사례 연구 (case_study)',
                },
                'required' => false,
                'placeholder' => '— 좌표로 자동 결정 (지역 깊이 기반) —',
                'help' => 'guide_*/essay/report/case_study를 선택하면 본문은 Markdown으로 렌더링됩니다. 발행 시 변종마다 다른 게이트 적용 (가이드는 FAQ 3개+, 리포트는 5,000자+ 등).',
            ])
            ->add('bodyMarkdown', TextareaType::class, [
                'label' => '본문 (Markdown)',
                'required' => false,
                'attr' => ['rows' => 20, 'style' => 'font-family: ui-monospace, monospace;'],
                'help' => '가이드 템플릿 전용. 발행 시 가시 텍스트 3,000자 이상 + 검증된 FAQ DataPoint 3개 이상 필요. 매트릭스 템플릿(hub/sido/sigungu/dong)에서는 무시됩니다.',
            ])
            ->add('lastReviewAt', DateTimeType::class, [
                'label' => '마지막 검토',
                'required' => false,
                'widget' => 'single_text',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContentNode::class,
        ]);
    }
}
