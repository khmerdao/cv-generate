<?php

declare(strict_types=1);

namespace App\Auth\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<mixed>
 */
final class RegistrationType extends AbstractType
{
    /** @param array<string, mixed> $options */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'auth.email',
                'constraints' => [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 180)],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'auth.password',
                'mapped' => false,
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(min: 8, max: 4096),
                    new Assert\NotCompromisedPassword(),
                ],
            ])
            ->add('submit', SubmitType::class, ['label' => 'auth.register']);
    }

    public function getBlockPrefix(): string
    {
        return 'registration';
    }
}
