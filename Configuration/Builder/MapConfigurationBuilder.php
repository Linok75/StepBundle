<?php

/**
 * @author:  Thomas Prelot <tprelot@gmail.com>
 * @license: MIT
 */

namespace IDCI\Bundle\StepBundle\Configuration\Builder;

use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use IDCI\Bundle\StepBundle\Map\MapBuilderFactoryInterface;
use IDCI\Bundle\StepBundle\Configuration\Worker\ConfigurationWorkerRegistryInterface;

class MapConfigurationBuilder implements MapConfigurationBuilderInterface
{
    /**
     * The map builder factory.
     *
     * @var MapBuilderFactoryInterface
     */
    protected $mapBuilderFactory;

    /**
     * @var \Twig_Environment
     */
    private $merger;

    /**
     * @var SecurityContextInterface
     */
    private $securityContext;

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * The configuration worker registry.
     *
     * @var ConfigurationWorkerRegistryInterface
     */
    protected $workerRegistry;

    /**
     * Constructor.
     *
     * @param MapBuilderFactoryInterface           $mapBuilderFactory The map builder factory.
     * @param ConfigurationWorkerRegistryInterface $workerRegistry    The configuration worker registry.
     * @param Twig_Environment                     $merger            The twig merger.
     * @param SecurityContextInterface             $securityContext   The security context.
     * @param SessionInterface                     $session           The session.
     */
    public function __construct(
        MapBuilderFactoryInterface $mapBuilderFactory,
        ConfigurationWorkerRegistryInterface $workerRegistry,
        \Twig_Environment $merger,
        SecurityContextInterface $securityContext,
        SessionInterface $session
    )
    {
        $this->mapBuilderFactory = $mapBuilderFactory;
        $this->workerRegistry    = $workerRegistry;
        $this->merger            = $merger;
        $this->securityContext   = $securityContext;
        $this->session           = $session;
    }

    /**
     * {@inheritdoc}
     */
    public function build(array $parameters = array())
    {
        $builder = $this
            ->mapBuilderFactory
            ->createNamedBuilder(
                $parameters['name'],
                isset($parameters['data']) ? $parameters['data'] : array(),
                $this->buildOptions($parameters)
            )
        ;

        if (isset($parameters['steps'])) {
            foreach ($parameters['steps'] as $name => $step) {
                $builder->addStep(
                    $name,
                    $step['type'],
                    $this->buildOptions($this->merge($step))
                );
            }
        }

        if (isset($parameters['paths'])) {
            foreach ($parameters['paths'] as $name => $path) {
                $builder->addPath(
                    $path['type'],
                    $this->buildOptions($path)
                );
            }
        }

        return $builder->getMap();
    }

    /**
     * Build options.
     *
     * @param array       $options      The options.
     * @param string|null $optionField  The option sub field to build if given.
     *
     * @return array The built options.
     */
    protected function buildOptions(array $options, $optionField = 'options')
    {
        if (null !== $optionField) {
            $options = isset($options[$optionField]) ? $options[$optionField] : array();
        }

        foreach ($options as $key => $option) {
            // Case of a worker.
            if ('@' === substr($key, 0, 1)) {
                $worker = $this->workerRegistry->getWorker($option['worker']);

                unset($options[$key]);
                $options[substr($key, 1)] = $worker->work($option['parameters']);
            // Case of an embedded array.
            } else if (is_array($option)) {
                $options[$key] = $this->buildOptions($option, null);
            }
        }

        return $options;
    }

    /**
     * Merge options with the SecurityContext (user)
     * and the session (session).
     *
     * @param array $options The options.
     *
     * @return array
     */
    protected function merge(array $options = array())
    {
        $user = null;
        if (null !== $this->securityContext->getToken()) {
            $user = $this->securityContext->getToken()->getUser();
        }

        foreach ($options as $k => $v) {
            // Do not merge events parameters.
            if ($k == 'events') {
                continue;
            }

            // If ending with '|raw'
            if (substr($k, -4) == '|raw') {
                $options[substr($k, 0, -4)] = $options[$k];
                unset($options[$k]);
                continue;
            }

            $options[$k] = json_decode(
                $this->merger->render(
                    json_encode($v, JSON_UNESCAPED_UNICODE),
                    array(
                        'user'    => $user,
                        'session' => $this->session->all(),
                    )
                ),
                true
            );
        }

        return $options;
    }
}
