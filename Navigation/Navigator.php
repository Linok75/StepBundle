<?php

/**
 * @author:  Gabriel BONDAZ <gabriel.bondaz@idci-consulting.fr>
 * @author:  Thomas Prelot <tprelot@gmail.com>
 * @license: MIT
 */

namespace IDCI\Bundle\StepBundle\Navigation;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormFactoryInterface;
use IDCI\Bundle\StepBundle\Map\MapInterface;
use IDCI\Bundle\StepBundle\Flow\FlowInterface;
use IDCI\Bundle\StepBundle\Flow\Flow;
use IDCI\Bundle\StepBundle\Flow\FlowData;
use IDCI\Bundle\StepBundle\Flow\DataStore\FlowDataStoreInterface;

class Navigator implements NavigatorInterface
{
    /**
     * The current form.
     *
     * @var FormInterface
     */
    protected $form;

    /**
     * The current form view.
     *
     * @var FormViewInterface
     */
    protected $formView;

    /**
     * The form factory.
     *
     * @var FormFactoryInterface
     */
    protected $formFactory;

    /**
     * @var MapInterface
     */
    protected $map;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var FlowDataStoreInterface
     */
    protected $flowDataStore;

    /**
     * @var NavigationLoggerInterface
     */
    protected $logger;

    /**
     * @var FlowInterface
     */
    protected $flow;

    /**
     * @var boolean
     */
    protected $hasNavigated;

    /**
     * @var boolean
     */
    protected $hasFinished;

    /**
     * Constructor
     *
     * @param FormFactoryInterface       $formFactory       The form factory.
     * @param Request                    $request           The HTTP request.
     * @param MapInterface               $map               The map to navigate.
     * @param array                      $data              The navigation data.
     * @param FlowDataStoreInterface     $flowDataStore     The flow data store using to keep the flow.
     * @param NavigationLoggerInterface  $logger            The logger.
     */
    public function __construct(
        FormFactoryInterface       $formFactory,
        Request                    $request,
        MapInterface               $map,
        array                      $data = array(),
        FlowDataStoreInterface     $flowDataStore,
        NavigationLoggerInterface  $logger = null
    )
    {
        $this->formView      = null;
        $this->formFactory   = $formFactory;
        $this->request       = $request;
        $this->map           = $map;
        $this->flowDataStore = $flowDataStore;
        $this->logger        = $logger;
        $this->hasNavigated  = false;
        $this->hasFinished   = false;

        $this->initFlow($data);

        $this->navigate();
    }

    /**
     * Init the flow
     *
     * @param array $data The default data.
     */
    protected function initFlow(array $data = array())
    {
        $this->flow = $this->flowDataStore->get(
            $this->map,
            $this->request
        );

        if (null === $this->flow) {
            $this->flow = new Flow();
            $this->flow->setCurrentStep($this->map->getFirstStep());

            if (!empty($data)) {
                foreach ($data as $stepName => $stepData) {
                    $this->flow->setStepData($this->map->getStep($stepName), $stepData, true);
                }

                $this->save();
            }
        }
    }

    /**
     * Returns the navigation form builder.
     *
     * @return Symfony\Component\Form\FormBuilderInterface
     */
    protected function getFormBuilder()
    {
        $data = $this->getCurrentNormalizedStepData();

        return $this->formFactory->createBuilder(
            new NavigatorType(),
            !empty($data) ? array('_data' => $data) : null,
            array('navigator' => $this)
        );
    }

    /**
     * Returns the navigation form.
     *
     * @return FormInterface The form.
     */
    protected function getForm()
    {
        if (null === $this->form) {
            $this->form = $this->getFormBuilder()->getForm();
        }

        return $this->form;
    }

    /**
     * Retrieve the choosen path.
     *
     * @return PathInterface
     */
    protected function getChosenPath()
    {
        foreach ($this->getAvailablePaths() as $i => $path) {
            if ($this->getForm()->get(sprintf('_path#%d', $i))->isClicked()) {
                $this->getFlow()->takePath($path, $i);

                return $path;
            }
        }

        throw new \LogicException(sprintf(
            'The taken path seems to disapear magically'
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function navigate()
    {
        if ($this->hasNavigated()) {
            throw new \LogicException('The navigation has already been done');
        }

        if ($this->logger) {
            $this->logger->startNavigation();
        }

        if ($this->request->isMethod('POST')) {
            $destinationStep = null;
            $form = $this->getForm();
            $form->handleRequest($this->request);

            if ($form->has('_back') && $form->get('_back')->isClicked()) {
                $destinationStep = $this
                    ->getMap()
                    ->getStep($this->getFlow()->getPreviousStepName())
                ;

                $this->getFlow()->retraceTo($destinationStep);
            } elseif ($form->isValid()) {
                $path = $this->getChosenPath();
                $destinationStep = $path->resolveDestination($this);

                if (null === $destinationStep) {
                    $this->hasFinished = true;
                }
            }

            if (null !== $destinationStep) {
                $this->hasNavigated = true;
                $this->getFlow()->setCurrentStep($destinationStep);
            }

            if ($this->hasNavigated || $this->hasFinished) {
                $this->save();
                // Reset the current form.
                $this->form = null;
            }
        }

        if ($this->logger) {
            $this->logger->stopNavigation($this);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getMap()
    {
        return $this->map;
    }

    /**
     * {@inheritdoc}
     */
    public function getFlow()
    {
        return $this->flow;
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentStep()
    {
        return $this->getMap()->getStep($this->getFlow()->getCurrentStepName());
    }

    /**
     * {@inheritdoc}
     */
    public function getPreviousStep()
    {
        $previousStepName = $this->getFlow()->getPreviousStepName();

        return $previousStepName
            ? $this->getMap()->getStep($previousStepName)
            : null
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentStepData()
    {
        return $this->getFlow()->getStepData($this->getCurrentStep());
    }

    /**
     * {@inheritdoc}
     */
    public function setCurrentStepData(array $data)
    {
        $this->getFlow()->setStepData(
            $this->getCurrentStep(),
            $data
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentNormalizedStepData()
    {
        $step = $this->getCurrentStep();
        $builder = $this->formFactory->createBuilder();
        $configuration = $step->getConfiguration();

        $step->getType()->buildNavigationStepForm(
            $builder,
            $configuration['options']
        );

        $form = $builder->getForm();

        if ($form->has('_data')) {
            $form->submit(array('_data' => $this->getCurrentStepData()));

            return $form->get('_data')->getData();
        }

        return array();
    }

    /**
     * {@inheritdoc}
     */
    public function getAvailablePaths()
    {
        return $this->getMap()->getPaths($this->getFlow()->getCurrentStepName());
    }

    /**
     * {@inheritdoc}
     */
    public function getTakenPaths()
    {
        return $this->getFlow()->getTakenPaths();
    }

    /**
     * {@inheritdoc}
     */
    public function hasNavigated()
    {
        return $this->hasNavigated;
    }

    /**
     * {@inheritdoc}
     */
    public function hasFinished()
    {
        return $this->hasFinished;
    }

    /**
     * {@inheritdoc}
     */
    public function save()
    {
        $this->flowDataStore->set(
            $this->map,
            $this->request,
            $this->flow
        );
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->flowDataStore->clear(
            $this->map,
            $this->request
        );
    }

    /**
     * {@inheritdoc}
     */
    public function createStepView()
    {
        return $this->getForm()->createView();
    }

    /**
     * {@inheritdoc}
     */
    public function getFormView()
    {
        if (null === $this->formView) {
            $this->formView = $this->createStepView();
        }

        return $this->formView;
    }
}
