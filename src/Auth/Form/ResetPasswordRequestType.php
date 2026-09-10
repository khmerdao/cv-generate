<?php

declare(strict_types=1);

namespace App\Auth\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<mixed>
 */
final class ResetPasswordRequestType extends AbstractType
{
    /** @param array<string, mixed> $options */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, ['label' => 'auth.email', 'constraints' => [new Assert\NotBlank(), new Assert\Email()]])
            ->add('submit', SubmitType::class, ['label' => 'auth.send']);
    }

    public function getBlockPrefix(): string
    {
        return 'reset_password_request';
    }
}
