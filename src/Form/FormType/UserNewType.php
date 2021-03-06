<?php

namespace Bolt\Form\FormType;

use Symfony\Component\Form\FormBuilderInterface;

/**
 * Bolt new user editing form type.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class UserNewType extends AbstractUserType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this
            ->addUserName($builder)
            ->addPassword($builder, ['required' => true])
            ->addEmail($builder)
            ->addDisplayName($builder)
            ->addSave($builder, ['label' => 'Create the first user'])
        ;
    }
}
