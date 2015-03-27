<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\WorkspaceUsersBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class WorkspaceUserEditionType extends AbstractType
{
    private $langs;
    private $authenticationDrivers;

    public function __construct(
        array $langs,
        $authenticationDrivers = null
    )
    {
        $this->authenticationDrivers = $authenticationDrivers;
        $this->langs = empty($langs) ?
            array('en' => 'en', 'fr' => 'fr') :
            $langs;
    }
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder->add('firstName', 'text', array('label' => 'First name', 'required' => true));
        $builder->add('lastName', 'text', array('label' => 'Last name', 'required' => true));
        $builder->add('username', 'text', array('label' => 'User name', 'required' => true));
        $builder->add(
            'administrativeCode',
            'text',
            array(
                'required' => false, 'label' => 'administrative_code'
            )
        );
        $builder->add('mail', 'email', array('required' => true, 'label' => 'email'));
        $builder->add('phone', 'text', array('required' => false, 'label' => 'phone'));
        $builder->add(
            'locale',
            'choice',
            array(
                'choices' => $this->langs,
                'required' => false,
                'label' => 'Language'
            )
        );
        $builder->add(
            'authentication',
            'choice',
            array(
                'choices' => $this->authenticationDrivers,
                'required' => false,
                'label' => 'authentication'
            )
        );
    }

    public function getName()
    {
        return 'profile_form_creation';
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array('translation_domain' => 'platform'));
    }
}
