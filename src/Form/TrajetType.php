<?php

namespace App\Form;

use App\Document\Trajet;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class TrajetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('villeDepart', TextType::class, [
                'label'       => 'Ville de départ',
                'constraints' => [new Assert\NotBlank(message: 'La ville de départ est obligatoire.')],
                'attr'        => ['placeholder' => 'Ex: Tunis'],
            ])
            ->add('villeArrivee', TextType::class, [
                'label'       => "Ville d'arrivée",
                'constraints' => [new Assert\NotBlank(message: "La ville d'arrivée est obligatoire.")],
                'attr'        => ['placeholder' => 'Ex: Kélibia'],
            ])
            ->add('pointRendezVous', TextType::class, [
                'label'    => 'Point de rendez-vous (optionnel)',
                'required' => false,
                'attr'     => ['placeholder' => 'Ex: Devant la poste, Station de bus...'],
            ])
            ->add('dateDepart', DateType::class, [
                'label'       => 'Date de départ',
                'widget'      => 'single_text',
                'input'       => 'datetime',
                'constraints' => [new Assert\NotNull(message: 'La date est obligatoire.')],
            ])
            ->add('heureDepart', TextType::class, [
                'label'       => 'Heure de départ',
                'constraints' => [
                    new Assert\NotBlank(message: "L'heure est obligatoire."),
                    new Assert\Regex(pattern: '/^\d{2}:\d{2}$/', message: 'Format HH:MM requis.'),
                ],
                'attr' => ['placeholder' => 'HH:MM', 'pattern' => '[0-9]{2}:[0-9]{2}'],
            ])
            ->add('nbPlacesTotal', IntegerType::class, [
                'label'       => 'Nombre de places',
                'constraints' => [new Assert\Range(min: 1, max: 8)],
                'attr'        => ['min' => 1, 'max' => 8],
            ])
            ->add('prix', NumberType::class, [
                'label'       => 'Prix par place (DT)',
                'scale'       => 2,
                'constraints' => [new Assert\PositiveOrZero(message: 'Le prix ne peut pas être négatif.')],
                'attr'        => ['min' => 0, 'max' => 500, 'step' => 0.5, 'placeholder' => '12'],
            ])
            ->add('messagePassagers', TextareaType::class, [
                'label'    => 'Message pour les passagers (optionnel)',
                'required' => false,
                'attr'     => ['rows' => 3, 'placeholder' => 'Ex: Départ ponctuel. Bagages légers uniquement.'],
            ])
            // Coordonnées GPS : champs cachés côté HTML, optionnels
            ->add('lngDepart', NumberType::class, [
                'label'    => 'Longitude départ (optionnel)',
                'required' => false,
                'mapped'   => false,
                'scale'    => 6,
                'attr'     => ['step' => 'any'],
            ])
            ->add('latDepart', NumberType::class, [
                'label'    => 'Latitude départ (optionnel)',
                'required' => false,
                'mapped'   => false,
                'scale'    => 6,
                'attr'     => ['step' => 'any'],
            ])
            ->add('lngArrivee', NumberType::class, [
                'label'    => 'Longitude arrivée (optionnel)',
                'required' => false,
                'mapped'   => false,
                'scale'    => 6,
                'attr'     => ['step' => 'any'],
            ])
            ->add('latArrivee', NumberType::class, [
                'label'    => 'Latitude arrivée (optionnel)',
                'required' => false,
                'mapped'   => false,
                'scale'    => 6,
                'attr'     => ['step' => 'any'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => Trajet::class,
            'csrf_protection'    => true,
            'csrf_field_name'    => '_token',
            'csrf_token_id'      => 'trajet_form',
        ]);
    }
}
