<?php

/**
 * @author:  Thomas Prelot <tprelot@gmail.com>
 * @license: MIT
 */

namespace IDCI\Bundle\StepBundle\Step;

use IDCI\Bundle\StepBundle\Step\StepInterface;

interface StepInterface
{
    /**
     * Get the configuration.
     *
     * @return array The configuration.
     */
    public function getConfiguration();

    /**
     * Returns a boolean to indicate That this step was define as a first step
     *
     * @return boolean.
     */
    public function isFirst();

    /**
     * Returns the step types used to construct the step.
     *
     * @return StepTypeInterface The step's type.
     */
    public function getType();

    /**
     * Returns the form pre step content.
     *
     * @return string|null The content.
     */
    public function getPreStepContent();
}
