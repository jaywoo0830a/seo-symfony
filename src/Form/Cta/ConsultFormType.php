<?php

declare(strict_types=1);

namespace App\Form\Cta;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * 문의 폼 — 가이드/리포트/사례 페이지 final CTA에 임베드.
 * 제출 시 텔레그램/디스코드로 알림. 자체 DB 저장 X.
 *
 * 'hp' 필드는 honeypot — 봇이 채우면 서버는 제출을 무시.
 */
final class ConsultFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => '이름',
                'attr' => ['placeholder' => '홍길동', 'autocomplete' => 'name'],
                'constraints' => [new NotBlank(), new Length(max: 60)],
            ])
            ->add('phone', TelType::class, [
                'label' => '연락처',
                'attr' => ['placeholder' => '010-1234-5678', 'autocomplete' => 'tel'],
                'constraints' => [new NotBlank(), new Length(max: 30)],
            ])
            ->add('region', TextType::class, [
                'label' => '관심 지역',
                'required' => false,
                'constraints' => [new Length(max: 60)],
            ])
            ->add('memo', TextareaType::class, [
                'label' => '문의 내용',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => '문의 내용을 자유롭게 적어주세요.'],
                'constraints' => [new Length(max: 1000)],
            ])
            // honeypot — 사용자 눈엔 안 보이도록 CSS로 숨김. 봇이 채우면 무시.
            ->add('hp', TextType::class, [
                'label' => false,
                'required' => false,
                'mapped' => false,
                'attr' => ['autocomplete' => 'off', 'tabindex' => '-1'],
                'row_attr' => ['class' => 'cta-form__honeypot'],
            ])
            ->add('agree', CheckboxType::class, [
                'label' => '개인정보 수집·이용에 동의합니다.',
                'mapped' => false,
                'constraints' => [new IsTrue(message: '동의가 필요합니다.')],
            ])
            ->add('submit', SubmitType::class, [
                'label' => '문의 보내기',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'cta_consult',
        ]);
    }
}
