<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Tests\Fixtures\Form;

use ChamberOrchestra\OpenApiDocBundle\Tests\Fixtures\Enum\Priority;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Mirrors the pattern of forms that use backed enums as choices
 * with Count constraints (e.g. UpdateFlashcardSettingsForm in dicty/api).
 */
class EnumChoiceFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'constraints' => [new NotBlank(), new Length(min: 1, max: 255)],
            ])
            ->add('priorities', ChoiceType::class, [
                'multiple'     => true,
                'choices'      => Priority::cases(),
                'choice_value' => 'value',
                'constraints'  => [new NotBlank(), new Count(min: 1, max: 3)],
            ])
            ->add('primaryPriority', ChoiceType::class, [
                'choices'      => Priority::cases(),
                'choice_value' => 'value',
            ]);
    }
}
