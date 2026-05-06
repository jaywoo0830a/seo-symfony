<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Author;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AuthorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('slug', TextType::class, [
                'label' => '슬러그',
                'help' => 'URL용 식별자.',
            ])
            ->add('realName', TextType::class, [
                'label' => '이름',
            ])
            ->add('credentials', TextareaType::class, [
                'label' => '자격·경력',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('bio', TextareaType::class, [
                'label' => '소개',
                'required' => false,
                'attr' => ['rows' => 4],
            ])
            ->add('photoUrl', UrlType::class, [
                'label' => '사진 URL',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Author::class,
        ]);
    }
}
