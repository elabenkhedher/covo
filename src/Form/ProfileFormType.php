<?php

namespace App\Form;

use App\Document\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProfileFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('prenom', TextType::class, [
                'constraints' => [new NotBlank(message: 'Le prénom est requis.')],
            ])
            ->add('nom', TextType::class, [
                'constraints' => [new NotBlank(message: 'Le nom est requis.')],
            ])
            ->add('email', EmailType::class, [
                'constraints' => [new NotBlank(message: 'L\'email est requis.')],
            ])
            ->add('telephone', TextType::class, [
                'required' => false,
            ])
            ->add('bio', TextareaType::class, [
                'required' => false,
            ])
            ->add('prefNonFumeur', CheckboxType::class, [
                'required' => false,
            ])
            ->add('prefMusique', CheckboxType::class, [
                'required' => false,
            ])
            ->add('prefAnimaux', CheckboxType::class, [
                'required' => false,
            ])
            ->add('prefDiscussion', CheckboxType::class, [
                'required' => false,
            ])
            ->add('currentPassword', PasswordType::class, [
                'mapped' => false,
                'required' => false,
            ])
            ->add('newPassword', PasswordType::class, [
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Length(
                        min: 8,
                        minMessage: 'Le mot de passe doit faire au moins {{ limit }} caractères.'
                    ),
                ],
            ])
            ->add('confirmPassword', PasswordType::class, [
                'mapped' => false,
                'required' => false,
            ])
            ->add('marqueVehicule', TextType::class, [
                'required' => false,
            ])
            ->add('immatriculation', TextType::class, [
                'required' => false,
            ])
            ->add('couleur', TextType::class, [
                'required' => false,
            ])
            ->add('nbPlacesVehicule', IntegerType::class, [
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
