<?php

namespace CanalTP\ScheduleBundle\Processor;

use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;

use CanalTP\FrontCoreBundle\Form\DataTransformer\DatetimeToIsoTransformer;
use CanalTP\FrontCoreBundle\Form\Bridge\ErrorsBridge;

class LineFormProcessor extends AbstractProcessor
{
    /**
     * @var Form
     */
    protected $form;

    /**
     * @var FormTypeInterface
     */
    protected $formType;

    /**
     * @var FormView
     */
    protected $formView;

    protected $routes;

    /**
     * @var string
     */
    protected $timezone = null;

    /**
     * setFormType
     *
     * Defini le type de formulaire
     *
     * @param FormTypeInterface $formType Type de formulaire
     */
    public function setFormType(FormTypeInterface $formType)
    {
        $this->formType = $formType;
    }

    /**
     * createForm
     *
     * Créer le formulaire lié a l'entité de recherche
     *
     * @return Form
     */
    protected function createForm()
    {
        $this->entity = $this->api->generateRequest();
        $timezone = null;
        if (!empty($this->timezone)) {
            $timezone = new \DateTimeZone($this->timezone);
        }
        $datetime = new \DateTime('now', $timezone);
        $iso = $datetime->format(DatetimeToIsoTransformer::ISO8601_BASE);
        $this->entity->setFromDatetime($iso);
        $this->form = $this->container
            ->get('form.factory')
            ->create($this->formType, $this->entity);
        return $this->form;
    }

    /**
     * getFormView
     *
     * Renvoie la vue du formulaire
     *
     * @return FormView
     */
    public function getFormView()
    {
        return $this->formView;
    }

    /**
     * processForm
     *
     * Création du formulaire et ajout des données depuis la requete
     * Validation des donnée de la requete et ajout des erreurs au formulaire
     * Appel navitia pour recuper le resultat depuis la requete
     *
     * @param Request $request Requete http
     */
    public function processForm(Request $request)
    {
        $form = $this->createForm();
        $requestArray = $request->query->all();
        $formName = $this->form->getName();
        if (array_key_exists($formName, $requestArray)) {
            $this->form->submit($request);
            $this->formView = $form->createView();
            if (isset($requestArray[$formName]['route'])) {
                if ($form->has('route')) {
                    $this->routes = array_flip($form->get('route')->getConfig()->getOption('choices'));
                }
                // Change time to 0 at 4 AM
                $datetime = new \DateTime($this->entity->getFromDatetime());
                $datetime->setTime(4, 0, 0);
                $iso = $datetime->format(DatetimeToIsoTransformer::ISO8601_BASE);
                $this->entity->setFromDatetime($iso);

                $this->result = $this->api->callApi($this->entity);
                $this->responseManagerAlert($requestArray[$formName]);
                $responseManager = $this->getResponseManager();
                $responseManager->importResult($this->result);
                $result = $responseManager->getResult();
                if ($result->hasErrors()) {
                    $errors = $result->getErrors();
                    $bridge = new ErrorsBridge();
                    $this->formView = $bridge->attach($errors, $this->formView, $this->form);
                }
            }
        } else {
            $this->formView = $form->createView();
        }
    }

    /**
     * Function to add uris to alert object
     * @param type $requestArray
     */
    public function responseManagerAlert($requestArray)
    {
        $result = $this->result->getResult();
        if (isset($result->route_schedules)) {
            foreach ($result->route_schedules as $route_schedule) {
                if (isset($route_schedule->alert_datas)) {
                    $route_schedule->alert_datas['networks'][0]['uri'] = $requestArray['network'];
                    $route_schedule->alert_datas['lines'][0]['uri'] = $requestArray['line'];
                }
            }
        }
    }

    public function getRoutes()
    {
        return $this->routes;
    }

    public function setTimezone($timezone = null)
    {
        $this->timezone = $timezone;
    }
}
