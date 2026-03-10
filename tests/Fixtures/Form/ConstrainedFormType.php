<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Tests\Fixtures\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Url;

class ConstrainedFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', TextType::class, [
                'constraints' => [new Email(), new Length(min: 3, max: 255)],
            ])
            ->add('website', TextType::class, [
                'required' => false,
                'constraints' => [new Url()],
            ])
            ->add('username', TextType::class, [
                'constraints' => [new Regex(pattern: '/^[a-z]+$/')],
            ])
            ->add('score', NumberType::class, [
                'constraints' => [new Range(min: 0, max: 100)],
            ])
            ->add('count', IntegerType::class, [
                'constraints' => [new Positive()],
            ]);
    }
}
