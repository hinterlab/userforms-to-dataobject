<?php

namespace Internetrix\UserFormsToDataObject;

use SilverStripe\Core\Convert;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;

class UserDefinedFormExtension extends DataExtension {
	
	private static $db = array(
		'EnableUF2DO' => 'Boolean'
	);
	
	private static $defaults = array(
		'EnableUF2DO' => false
	);
	
	private static $has_many = array(
		'SubmissionConfig' => UF2DOConfig::class
	);

	public function updateCMSFields(FieldList $fields) {
		
        $gfConfig = GridFieldConfig_RelationEditor::create(10)
            ->removeComponentsByType(GridFieldAddExistingAutocompleter::class)
            ->removeComponentsByType(GridFieldDeleteAction::class)
            ->addComponent(new GridFieldDeleteAction(false));

        $fields->addFieldsToTab('Root.Mapping', [
            CheckboxField::create('EnableUF2DO', 'Save submitted data into a data record'),
            GridField::create( 'SubmissionConfig','Data Object Configurations', $this->owner->SubmissionConfig(), $gfConfig )
        ]);

		if($this->owner->EnableUF2DO){
            $fields->addFieldToTab('Root.FormFields', LiteralField::create('remapwarning', '<span class="message notice" style="margin-top: 30px;">If you add/delete any formfields, dont forget to remap them under the mapping tab</span>') );
//                $fields->addFieldToTab('Root.Main', LiteralField::create('JSFieldsChecking', $this->jsCodeDisableFormFieldDeleteButton()));
		}
	}

	public function getActiveSubmissionConfigs(){
		return $this->owner->SubmissionConfig()->filter('Active', true);
	}

	//TODO this or similar needs to be upgraded/done for SS4
	public function jsCodeDisableFormFieldDeleteButton(){
		$submissionConfigs = $this->owner->getActiveSubmissionConfigs();	

		$dataMappingJS = '';
		
		//only apply js when there is active submission config
		if($submissionConfigs && $submissionConfigs->Count()){
			//check through all configs
			$formFieldsIDsToBeDisabled = array();
			foreach ($submissionConfigs as $config){
				$array = $config->getAssignedFormFieldsIDs();
				if( ! empty($array)){
					$formFieldsIDsToBeDisabled = array_merge($formFieldsIDsToBeDisabled, $array);
				}
			}

			if(count($formFieldsIDsToBeDisabled)){
				$selectedFormFieldIDsStrings = Convert::array2json(
					array_values($formFieldsIDsToBeDisabled)
				);
				
				$dataMappingJS = "
					<script>
						;jQuery(function($) {
							$(document).ready(function(){
								var ffIDs = {$selectedFormFieldIDsStrings};
								
								ffIDs.forEach(function(v) {
								var ffTitleHolder = $('input#Fields-'+v+'-Title');
									if(ffTitleHolder.length){
										var deleteButton = ffTitleHolder.closest('li.EditableFormField').find('.fieldActions a.delete');
										if(deleteButton.length){ 
											$('<span>This field is reserved. Please check <strong>Advanced Submission Configuration</span>').insertAfter(deleteButton);
											deleteButton.hide();
										}
									}else{
										console.log('cant get user form field of #' + v);
									}
								});
							});
						});
					</script>
				";
			}
		}
	
		return $dataMappingJS;
	}

}