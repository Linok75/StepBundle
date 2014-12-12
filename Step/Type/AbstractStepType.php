<?php

/**
 * @author:  Gabriel BONDAZ <gabriel.bondaz@idci-consulting.fr>
 * @license: MIT
 */
 
namespace IDCI\Bundle\StepBundle\Step\Type;

use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use IDCI\Bundle\StepBundle\Step\StepInterface;
use IDCI\Bundle\StepBundle\Map\MapInterface;

abstract class AbstractStepType implements StepTypeInterface
{
    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver
            ->setDefaults(array(
                'title'       => null,
                'description' => null
            ))
            ->setAllowedTypes(array(
                'title'         => array('null', 'string'),
                'description'   => array('null', 'string'),
            ))
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function buildStep(StepInterface $step, MapInterface $map, array $options = array())
    {
        return $step;
    }
}
