<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Tests\Fixtures\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Form that contains a CollectionType of itself — tests cycle detection.
 */
class RecursiveFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class)
            ->add('children', CollectionType::class, [
                'entry_type'    => self::class,
                'entry_options' => ['required' => false],
            ]);
    }
}
