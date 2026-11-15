<?php

namespace App\Form;

use App\Entity\User;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Repartition automatique : departements et agents coches. "Apercu" montre la repartition
 * proposee sans rien changer, "Appliquer" l'enregistre.
 */
final class RepartitionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $lignes = $options['lignes'];

        $builder
            ->add('departements', ChoiceType::class, [
                'label' => 'Départements',
                'choices' => array_combine(array_keys($lignes), array_map('strval', array_keys($lignes))),
                'choice_label' => static fn (string $dep): string => \sprintf('%s (%d)', $dep, $lignes[$dep] ?? 0),
                'multiple' => true,
                'expanded' => true,
                'constraints' => [new Assert\Count(min: 1, minMessage: 'Sélectionnez au moins un département.')],
            ])
            ->add('agents', EntityType::class, [
                'label' => 'Agents',
                'class' => User::class,
                'choice_label' => static fn (User $u): string => $u->getLibelle(),
                'query_builder' => static fn (EntityRepository $r) => $r->createQueryBuilder('u')->orderBy('u.email'),
                'multiple' => true,
                'expanded' => true,
                'constraints' => [new Assert\Count(min: 1, minMessage: 'Sélectionnez au moins un agent.')],
            ])
            ->add('apercu', SubmitType::class, ['label' => 'Aperçu de la répartition', 'attr' => ['class' => 'secondary']])
            ->add('appliquer', SubmitType::class, ['label' => 'Répartition automatique']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // code du departement => nombre de lignes (fiches), affiche a cote de chaque case
        $resolver->setRequired('lignes');
        $resolver->setAllowedTypes('lignes', 'array');
    }
}
