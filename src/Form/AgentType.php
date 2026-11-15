<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Creation ou modification d'un agent par un admin. Le mot de passe n'est obligatoire qu'a
 * la creation ; en modification, le laisser vide garde l'ancien.
 */
final class AgentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, ['label' => 'Email de connexion'])
            ->add('name', TextType::class, ['label' => 'Nom affiché', 'required' => false])
            ->add('plainPassword', PasswordType::class, [
                'label' => $options['creation'] ? 'Mot de passe' : 'Nouveau mot de passe (laisser vide pour le garder)',
                'mapped' => false,
                'required' => $options['creation'],
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => [
                    ...($options['creation'] ? [new Assert\NotBlank(message: 'Le mot de passe est obligatoire.')] : []),
                    new Assert\Length(min: 8, max: 4096, minMessage: 'Le mot de passe doit faire au moins {{ limit }} caractères.'),
                ],
            ])
            ->add('admin', CheckboxType::class, [
                'label' => 'Administrateur (gère les agents et la distribution des départements)',
                'required' => false,
                // un admin ne peut pas se retirer ses propres droits
                'disabled' => $options['soi_meme'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'creation' => false,
            'soi_meme' => false,
        ]);
        $resolver->setAllowedTypes('creation', 'bool');
        $resolver->setAllowedTypes('soi_meme', 'bool');
    }
}
