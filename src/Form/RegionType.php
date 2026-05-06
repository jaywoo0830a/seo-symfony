<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Region;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RegionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $forEdit = $options['for_edit'];

        $builder
            ->add('slug', TextType::class, [
                'label' => '슬러그',
                'help' => 'URL용 영문 식별자 (예: seoul, gangnam-gu).',
            ])
            ->add('name', TextType::class, [
                'label' => '이름',
                'help' => '한글 표기 (예: 서울특별시, 강남구).',
            ])
            ->add('parent', EntityType::class, [
                'label' => '부모 지역',
                'class' => Region::class,
                'choice_label' => fn (Region $r) => sprintf('%s · depth %d', $r->getName(), $r->getDepth()),
                'query_builder' => fn ($repo) => $repo->createQueryBuilder('r')
                    ->where('r.depth < 3')
                    ->orderBy('r.depth', 'ASC')
                    ->addOrderBy('r.name', 'ASC'),
                'required' => false,
                'placeholder' => '— 최상위 (전국) —',
                'disabled' => $forEdit,
                'help' => $forEdit ? '생성 후에는 변경할 수 없습니다.' : 'depth는 부모로부터 자동 계산됩니다.',
            ])
            ->add('adminCode', TextType::class, [
                'label' => '행정코드',
                'required' => false,
                'help' => '행정안전부 법정동 코드.',
            ])
            ->add('lat', NumberType::class, [
                'label' => '위도',
                'required' => false,
                'scale' => 6,
            ])
            ->add('lng', NumberType::class, [
                'label' => '경도',
                'required' => false,
                'scale' => 6,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Region::class,
            'for_edit' => false,
        ]);
        $resolver->setAllowedTypes('for_edit', 'bool');
    }
}
