<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\DataPoint;
use App\Entity\Enum\DataPointKind;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DataPointType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('kind', EnumType::class, [
                'label' => '종류',
                'class' => DataPointKind::class,
                'choice_label' => fn (DataPointKind $k) => match ($k) {
                    DataPointKind::Quantitative => '정량 (quantitative) — 숫자·통계',
                    DataPointKind::Qualitative => '정성 (qualitative) — 목록·분포',
                    DataPointKind::Comparison => '비교 (comparison) — 차별 인사이트',
                    DataPointKind::CaseStudy => '사례 (case) — 매칭 사례·후기',
                    DataPointKind::Faq => 'FAQ (faq) — 질문·답변',
                },
            ])
            ->add('title', TextType::class, [
                'label' => '제목',
            ])
            ->add('value', TextareaType::class, [
                'label' => '값 (JSON)',
                'attr' => ['rows' => 5],
                'help' => '예: 12 / "서울고등학교" / {"q":"...","a":"..."} / ["대치동","역삼동"]',
            ])
            ->add('source', TextType::class, [
                'label' => '출처',
                'required' => false,
            ]);

        $builder->get('value')->addModelTransformer(new CallbackTransformer(
            transform: fn ($value) => $value === null ? '' : json_encode($value, \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT),
            reverseTransform: function ($string) {
                if ($string === null || trim($string) === '') {
                    return null;
                }
                try {
                    return json_decode($string, associative: true, flags: \JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw new TransformationFailedException('유효한 JSON이 아닙니다.', previous: $e);
                }
            },
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DataPoint::class,
        ]);
    }
}
