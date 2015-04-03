<?php

/**
 * @author:  Gabriel BONDAZ <gabriel.bondaz@idci-consulting.fr>
 * @license: MIT
 */

namespace IDCI\Bundle\StepBundle\Path\Type;

use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\OptionsResolver\Options;
use IDCI\Bundle\StepBundle\Path\Path;
use IDCI\Bundle\StepBundle\Map\MapInterface;
use IDCI\Bundle\StepBundle\Navigation\NavigatorInterface;

abstract class AbstractPathType implements PathTypeInterface
{
    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver
            ->setDefaults(array(
                'type'         => 'submit',
                'next_options' => array(
                    'label' => 'Next >'
                ),
                'events'       => array(),
            ))
            ->setAllowedTypes(array(
                'type'         => array('string'),
                'next_options' => array('array'),
                'events'       => array('array'),
            ))
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function buildPath(MapInterface $map, array $options = array())
    {
        // TODO: Use a PathConfig as argument instead of an array.
        return new Path(array(
            'type'    => $this,
            'options' => $options
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function resolveDestination(array $options, NavigatorInterface $navigator)
    {
        return null;
    }
}
