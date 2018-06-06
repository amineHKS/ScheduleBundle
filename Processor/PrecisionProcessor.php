<?php

namespace CanalTP\ScheduleBundle\Processor;

use Symfony\Component\HttpFoundation\Request;

class PrecisionProcessor extends FormProcessor
{
    protected function createForm()
    {
        if (is_null($this->entity)) {
             $this->entity = $this->api->generateEntity();
        }
        $this->form = $this->container
            ->get('form.factory')
            ->createNamed('schedule', $this->formType, $this->entity);
        return $this->form;
    }

    public function processForm(Request $request)
    {
        $form = $this->createForm();
        $this->formView = $form->createView();
    }
}
