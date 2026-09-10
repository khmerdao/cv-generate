<?php

declare(strict_types=1);

namespace App\Shared\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<mixed>
 */
final class DeleteAccountType extends AbstractType
{
    /** @param array<string, mixed> $options */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $word = $options['word'];
        \assert(is_string($word));

        $builder
            ->add('confirmation', TextType::class, [
                'label' => 'account.delete_confirmation_label',
                'label_translation_parameters' => ['word' => $word],
                'constraints' => [new Assert\EqualTo(value: $word, message: 'account.delete_confirmation_mismatch')],
            ])
            ->add('submit', SubmitType::class, ['label' => 'account.delete', 'attr' => ['class' => 'bg-red-600 hover:bg-red-700']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('word')->setAllowedTypes('word', 'string');
    }

    public function getBlockPrefix(): string
    {
        return 'delete_account';
    }
}
