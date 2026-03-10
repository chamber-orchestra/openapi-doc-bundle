<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Tests\Fixtures\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

class ChoiceFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('status', ChoiceType::class, [
                'choices' => ['Active' => 'active', 'Inactive' => 'inactive'],
            ])
            ->add('tags', ChoiceType::class, [
                'multiple' => true,
                'choices'  => ['PHP' => 'php', 'Python' => 'python'],
            ]);
    }
}
