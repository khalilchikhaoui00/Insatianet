<?php

namespace App\Form;

use App\Entity\PaymentMethod;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Form\CallbackTransformer;

class PaymentMethodType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('cardholderName', TextType::class, [
                'label' => 'Nom du titulaire',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer le nom du titulaire',
                    ]),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'JEAN DUPONT'
                ]
            ])
            ->add('cardNumber', TextType::class, [
                'label' => 'Numéro de carte',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer un numéro de carte',
                    ]),
                    new Length([
                        'min' => 16,
                        'max' => 19, // Including spaces
                        'minMessage' => 'Le numéro de carte doit contenir 16 chiffres',
                        'maxMessage' => 'Le numéro de carte doit contenir 16 chiffres',
                    ]),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '1234 5678 9012 3456',
                    'maxlength' => 19
                ]
            ])
            ->add('expiryDate', TextType::class, [
                'label' => 'Date d\'expiration',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer une date d\'expiration',
                    ]),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'MM/YYYY',
                    'maxlength' => 7
                ]
            ])
            ->add('cvc', TextType::class, [
                'label' => 'Code de sécurité (CVC)',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer le code de sécurité',
                    ]),
                    new Length([
                        'min' => 3,
                        'max' => 3,
                        'exactMessage' => 'Le code de sécurité doit contenir exactement {{ limit }} chiffres',
                    ]),
                    new Regex([
                        'pattern' => '/^[0-9]{3}$/',
                        'message' => 'Le code de sécurité doit contenir uniquement des chiffres',
                    ]),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '123',
                    'maxlength' => 3
                ]
            ])
            ->add('isDefault', CheckboxType::class, [
                'label' => 'Définir comme carte par défaut',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'label_attr' => [
                    'class' => 'form-check-label'
                ]
            ])
        ;

        // Add data transformer for card number to remove spaces before saving
        $builder->get('cardNumber')->addModelTransformer(new CallbackTransformer(
            function ($cardNumberFromDatabase) {
                // Transform from database to form
                return $cardNumberFromDatabase;
            },
            function ($cardNumberFromForm) {
                // Transform from form to database
                return str_replace(' ', '', $cardNumberFromForm);
            }
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PaymentMethod::class,
        ]);
    }
} 