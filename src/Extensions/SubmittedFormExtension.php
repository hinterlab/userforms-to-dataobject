<?php

namespace Internetrix\UserFormsToDataObject;

use SilverStripe\Control\Controller;
use SilverStripe\ORM\DataExtension;

class SubmittedFormExtension extends DataExtension {
	
	public function updateAfterProcess() {
		$submittedForm = $this->owner;
		
		//get userform data object.
		$userFormPage = $submittedForm->Parent();
		if( !$userFormPage){
			$userFormPage = Controller::curr();
		}

		//only save it enabled
		if($userFormPage->EnableUF2DO){
            //get active submission config
            $submissionConfigs = $userFormPage->getActiveSubmissionConfigs();
            if($submissionConfigs && $submissionConfigs->Count()){
                $submittedValues = $submittedForm->Values();

                foreach ($submissionConfigs as $config){
                    $config->saveSubmittedFormValuesIntoDataObject($submittedValues);
                }
            }
        }
	}
	
}