<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Theme;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ThemeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $forEdit = $options['for_edit'];

        $builder
            ->add('slug', TextType::class, [
                'label' => '슬러그',
                'help' => 'URL에 사용 (예: tutoring, academy).',
            ])
            ->add('name', TextType::class, [
                'label' => '이름',
            ])
            ->add('parent', EntityType::class, [
                'label' => '부모 테마',
                'class' => Theme::class,
                'choice_label' => fn (Theme $t) => sprintf('%s · depth %d', $t->getName(), $t->getDepth()),
                'query_builder' => fn ($repo) => $repo->createQueryBuilder('t')
                    ->where('t.depth < 2')
                    ->orderBy('t.depth', 'ASC')
                    ->addOrderBy('t.name', 'ASC'),
                'required' => false,
                'placeholder' => '— 루트 테마 —',
                'disabled' => $forEdit,
                'help' => $forEdit ? '생성 후에는 변경할 수 없습니다.' : 'depth는 부모로부터 자동 계산됩니다.',
            ])
            ->add('description', TextareaType::class, [
                'label' => '설명',
                'required' => false,
                'attr' => ['rows' => 3],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Theme::class,
            'for_edit' => false,
        ]);
        $resolver->setAllowedTypes('for_edit', 'bool');
    }
}
