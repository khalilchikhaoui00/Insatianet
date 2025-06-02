<?php

namespace App\Form;

use App\Entity\Order;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class OrderTypeForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('shippingAddress', TextareaType::class, [
                'label' => 'Adresse de livraison',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Entrez votre adresse de livraison complète'
                ],
                'required' => true
            ])
            ->add('cardNumber', TextType::class, [
                'label' => 'Numéro de carte',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer un numéro de carte',
                    ]),
                    new Length([
                        'min' => 16,
                        'max' => 16,
                        'exactMessage' => 'Le numéro de carte doit contenir exactement {{ limit }} chiffres',
                    ]),
                    new Regex([
                        'pattern' => '/^[0-9]{16}$/',
                        'message' => 'Le numéro de carte doit contenir uniquement des chiffres',
                    ]),
                ],
            ])
            ->add('cardExpiry', TextType::class, [
                'label' => 'Date d\'expiration (MM/YYYY)',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer une date d\'expiration',
                    ]),
                    new Regex([
                        'pattern' => '/^(0[1-9]|1[0-2])\/20[2-9][0-9]$/',
                        'message' => 'Format de date invalide (MM/YYYY)',
                    ]),
                ],
            ])
            ->add('cardCvv', TextType::class, [
                'label' => 'CVV',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer le CVV',
                    ]),
                    new Length([
                        'min' => 3,
                        'max' => 3,
                        'exactMessage' => 'Le CVV doit contenir exactement {{ limit }} chiffres',
                    ]),
                    new Regex([
                        'pattern' => '/^[0-9]{3}$/',
                        'message' => 'Le CVV doit contenir uniquement des chiffres',
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Order::class,
        ]);
    }
}
